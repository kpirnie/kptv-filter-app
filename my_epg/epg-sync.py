#!/usr/bin/env python3
"""
KPTV EPG Sync
Fetches EPG from all IPTV providers, HDHomeRun device, and any extra URLs,
then merges everything into a single epg.xml.

Usage: python3 epg-sync.py --config /path/to/config.json --output /path/to/output/dir
       python3 epg-sync.py --config /path/to/config.json --output /path/to/output/dir --hdhomerun-host 192.168.1.x
       python3 epg-sync.py --config /path/to/config.json --output /path/to/output/dir --extra-epg https://example.com/epg
"""

import sys
import os
import json
import logging
import argparse
import tempfile
import concurrent.futures
import datetime
import ssl
import urllib.error
import urllib.request
from pathlib import Path
from typing import Optional
from xml.etree import ElementTree as ET

import mysql.connector
import pytz
import requests
from tzlocal import get_localzone

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s: %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
log = logging.getLogger(__name__)

# Initialize local timezone with fallback to UTC
try:
    LOCAL_TZ = get_localzone()
except Exception:
    log.warning('Could not detect local timezone, falling back to UTC')
    LOCAL_TZ = pytz.UTC

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
FETCH_TIMEOUT = 60
MAX_WORKERS   = 4
EPG_OUTPUT    = 'epg.xml'


def load_config(config_path: str) -> dict:
    with open(config_path) as f:
        return json.load(f)


def get_db(config: dict):
    db = config['database']
    return mysql.connector.connect(
        host=db['server'],
        database=db['schema'],
        user=db['username'],
        password=db['password'],
        charset=db.get('charset', 'utf8mb4'),
        autocommit=True,
    )


# ---------------------------------------------------------------------------
# Database helpers
# ---------------------------------------------------------------------------
def get_users(conn, user_id: Optional[int] = None) -> list[dict]:
    cursor = conn.cursor(dictionary=True)
    if user_id:
        cursor.execute('SELECT id FROM kptv_users WHERE id = %s AND u_active = 1', (user_id,))
    else:
        cursor.execute('SELECT id FROM kptv_users WHERE u_active = 1')
    return cursor.fetchall()


def get_providers(conn, user_id: int) -> list[dict]:
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        '''SELECT sp_domain, sp_username, sp_password
           FROM kptv_stream_providers
           WHERE u_id = %s''',
        (user_id,)
    )
    return cursor.fetchall()


def get_active_stream_identifiers(conn, user_id: int) -> set[str]:
    """
    Returns a lowercase set of all non-empty identifiers from active streams:
    s_tvg_id, s_name, s_orig_name — mirrors the PHP allow-list logic.
    """
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        '''SELECT s_tvg_id, s_name, s_orig_name
           FROM kptv_streams
           WHERE u_id = %s AND s_active = 1''',
        (user_id,)
    )
    allowed = set()
    for row in cursor.fetchall():
        for col in ('s_tvg_id', 's_name', 's_orig_name'):
            val = (row.get(col) or '').strip()
            if val:
                allowed.add(val.lower())
    return allowed


# ---------------------------------------------------------------------------
# EPG URL builder  (mirrors PHP buildEpgUrl)
# ---------------------------------------------------------------------------
def build_epg_url(provider: dict) -> str:
    domain   = (provider['sp_domain'] or '').strip().rstrip('/')
    username = (provider['sp_username'] or '').strip()
    password = (provider['sp_password'] or '').strip()

    url = domain + '/xmltv.php'
    if username or password:
        url += f'?username={requests.utils.quote(username)}&password={requests.utils.quote(password)}'
    return url


# ---------------------------------------------------------------------------
# Provider fetch
# ---------------------------------------------------------------------------
def fetch_provider_epg(url: str) -> Optional[bytes]:
    try:
        resp = requests.get(
            url,
            timeout=FETCH_TIMEOUT,
            headers={'User-Agent': 'Mozilla/5.0'},
            verify=True,
        )
        if resp.status_code == 200:
            return resp.content
        log.warning(f'HTTP {resp.status_code} for {url}')
    except Exception as e:
        log.error(f'Fetch failed for {url}: {e}')
    return None


# ---------------------------------------------------------------------------
# XML filtering and merge
# ---------------------------------------------------------------------------
def filter_and_merge(raw_blobs: list[bytes], allowed: set[str]) -> Optional[bytes]:
    """
    Filter IPTV provider blobs to only allowed stream identifiers,
    returning merged XML as bytes for inclusion in the final merge.
    Mirrors the PHP pass-1 / pass-2 allow-list logic.
    """
    seen_channels    = set()
    kept_channel_ids = set()
    channel_elements   = []
    programme_elements = []

    for blob in raw_blobs:
        if not blob:
            continue
        try:
            root = ET.fromstring(blob)
        except ET.ParseError as e:
            log.error(f'XML parse error: {e}')
            continue

        # --- Pass 1: channels ---
        for ch in root.findall('channel'):
            ch_id = (ch.get('id') or '').strip().lower()
            match = ch_id in allowed
            if not match:
                for dn in ch.findall('display-name'):
                    if (dn.text or '').strip().lower() in allowed:
                        match = True
                        break
            if not match or ch_id in seen_channels:
                continue
            seen_channels.add(ch_id)
            kept_channel_ids.add(ch_id)
            channel_elements.append(ch)

        # --- Pass 2: programmes ---
        for prog in root.findall('programme'):
            if (prog.get('channel') or '').strip().lower() in kept_channel_ids:
                programme_elements.append(prog)

    if not channel_elements:
        return None

    tv = ET.Element('tv')
    for ch in channel_elements:
        tv.append(ch)
    for prog in programme_elements:
        tv.append(prog)

    return ET.tostring(tv, encoding='unicode').encode('utf-8')


def merge_epg_blobs(blobs: list[bytes]) -> str:
    """Final merge of all blobs (filtered IPTV + HDHomeRun + extra-epg) into one XML."""
    seen_channels = set()
    tv = ET.Element('tv')
    tv.set('generator-info-name', 'KPTV EPG Sync')

    for blob in blobs:
        if not blob:
            continue
        try:
            root = ET.fromstring(blob)
        except ET.ParseError as e:
            log.error(f'XML parse error: {e}')
            continue
        for ch in root.findall('channel'):
            ch_id = (ch.get('id') or '').strip().lower()
            if ch_id in seen_channels:
                continue
            seen_channels.add(ch_id)
            tv.append(ch)
        for prog in root.findall('programme'):
            tv.append(prog)

    ET.indent(tv, space='  ')
    return '<?xml version="1.0" encoding="utf-8"?>\n' + ET.tostring(tv, encoding='unicode')


# ---------------------------------------------------------------------------
# HDHomeRun helpers
# ---------------------------------------------------------------------------
def hd_discover_device_auth(host: str) -> str:
    """Discover HDHomeRun device auth token."""
    try:
        log.info(f'Fetching HDHomeRun device auth from {host}')
        with urllib.request.urlopen(f'http://{host}/discover.json') as response:
            data = json.loads(response.read().decode())
            if 'DeviceAuth' in data:
                log.info(f'Discovered device auth: {data["DeviceAuth"]}')
                return data['DeviceAuth']
        log.error('HDHomeRun: no DeviceAuth in discover response')
        return ''
    except Exception as e:
        log.error(f'HDHomeRun discover failed: {e}')
        return ''


def hd_fetch_channels(host: str) -> list:
    """Fetch channel lineup from HDHomeRun device."""
    try:
        log.info(f'Fetching HDHomeRun lineup from {host}')
        with urllib.request.urlopen(f'http://{host}/lineup.json') as response:
            return json.loads(response.read().decode())
    except Exception as e:
        log.error(f'HDHomeRun lineup fetch failed: {e}')
        return []


def hd_fetch_epg_data(device_auth: str, channels: list, days: int, hours: int) -> dict:
    """Fetch EPG guide data from HDHomeRun cloud API."""
    epg_data   = {'channels': [], 'programmes': []}
    url        = f'https://api.hdhomerun.com/api/guide.php?DeviceAuth={device_auth}'
    next_start = datetime.datetime.now(pytz.UTC)
    end_time   = next_start + datetime.timedelta(days=days)
    context    = ssl._create_unverified_context()

    while next_start < end_time:
        try:
            req = urllib.request.Request(f'{url}&Start={int(next_start.timestamp())}')
            log.debug(f'Fetching HDHomeRun EPG from {next_start:%Y-%m-%d %H:%M:%S}')
            with urllib.request.urlopen(req, context=context) as response:
                segment = json.loads(response.read().decode())
                log.info(f'Processing EPG segment from {next_start:%Y-%m-%d %H:%M:%S}')
                for ch_seg in segment:
                    channel = next((c for c in channels if c.get('GuideNumber') == ch_seg['GuideNumber']), None)
                    if channel is None:
                        log.debug(f'Skipping untuned channel {ch_seg["GuideNumber"]}')
                        continue
                    for prog in ch_seg.get('Guide', []):
                        # Skip duplicates from overlapping requests
                        if any(
                            p['StartTime']   == prog['StartTime'] and
                            p['Title']       == prog['Title'] and
                            p['GuideNumber'] == ch_seg['GuideNumber']
                            for p in epg_data['programmes']
                        ):
                            log.debug(f'Skipping duplicate: {prog["Title"]} @ {prog["StartTime"]}')
                            continue
                        if not any(c.get('GuideNumber') == ch_seg['GuideNumber'] for c in epg_data['channels']):
                            channel['ImageURL'] = ch_seg.get('ImageURL', '')
                            epg_data['channels'].append(channel)
                        prog['GuideNumber'] = ch_seg['GuideNumber']
                        epg_data['programmes'].append(prog)
        except urllib.error.HTTPError as e:
            if e.code == 400:
                # 400 means the API has no more guide data beyond this point
                log.debug(f'HDHomeRun guide data exhausted at {next_start:%Y-%m-%d %H:%M:%S}')
                break
            log.error(f'HDHomeRun EPG fetch failed at {next_start}: {e}')
            break
        except Exception as e:
            log.error(f'HDHomeRun EPG fetch failed at {next_start}: {e}')
            break
        next_start += datetime.timedelta(hours=hours)

    return epg_data


def hd_create_channel(channel_data: dict, xmltv_root: ET.Element) -> None:
    """Create XMLTV <channel> element."""
    channel = ET.SubElement(xmltv_root, 'channel', id=channel_data.get('GuideNumber', ''))
    ET.SubElement(channel, 'display-name').text = channel_data.get('GuideName', 'Unknown')
    ET.SubElement(channel, 'icon', src=channel_data.get('ImageURL', ''))
    log.debug(f'Created channel: {channel_data.get("GuideName", "Unknown")}')


def hd_create_programme(prog_data: dict, channel_number: str, xmltv_root: ET.Element) -> None:
    """Create XMLTV <programme> element."""
    try:
        start_time = datetime.datetime.fromtimestamp(prog_data['StartTime'], tz=pytz.UTC).astimezone(LOCAL_TZ)
        duration   = prog_data.get('EndTime', prog_data['StartTime']) - prog_data['StartTime']
        end_time   = start_time + datetime.timedelta(seconds=duration)

        programme = ET.SubElement(
            xmltv_root, 'programme',
            start=start_time.strftime('%Y%m%d%H%M%S %z'),
            stop=end_time.strftime('%Y%m%d%H%M%S %z'),
            channel=channel_number,
        )

        # NOTE: XMLTV elements in DTD order; not all used due to HDHomeRun data limitations
        # <title>
        ET.SubElement(programme, 'title', lang='en').text = prog_data.get('Title')
        # <sub-title>
        if 'EpisodeTitle' in prog_data:
            ET.SubElement(programme, 'sub-title', lang='en').text = prog_data['EpisodeTitle']
        # <desc>
        if 'Synopsis' in prog_data:
            ET.SubElement(programme, 'desc', lang='en').text = prog_data['Synopsis']
        # <category>
        if 'Filter' in prog_data:
            for f in prog_data['Filter']:
                ET.SubElement(programme, 'category', lang='en').text = f
        # <icon>
        if 'ImageURL' in prog_data:
            ET.SubElement(programme, 'icon', src=prog_data['ImageURL'])
        # <episode-num>
        if 'EpisodeNumber' in prog_data:
            try:
                ep_num = prog_data['EpisodeNumber']
                if 'S' in ep_num and 'E' in ep_num:
                    series  = int(ep_num[ep_num.index('S') + 1:ep_num.index('E')]) - 1
                    episode = int(ep_num[ep_num.index('E') + 1:]) - 1
                    ET.SubElement(programme, 'episode-num', system='onscreen').text = ep_num
                    ET.SubElement(programme, 'episode-num', system='xmltv_ns').text = f'{series}.{episode}.0/0'
            except (ValueError, TypeError):
                log.warning(f'Invalid episode data for {prog_data.get("Title")}')
        # <previously-shown>
        if 'OriginalAirdate' in prog_data:
            air_date   = datetime.datetime.fromtimestamp(prog_data['OriginalAirdate'], tz=pytz.UTC).astimezone(LOCAL_TZ)
            start_date = start_time.replace(hour=0, minute=0, second=0, microsecond=0)
            if air_date != start_date:
                ET.SubElement(programme, 'previously-shown').set('start', air_date.strftime('%Y%m%d%H%M%S'))
            elif prog_data.get('First') is not True:
                ET.SubElement(programme, 'previously-shown')
        # <new>
        if prog_data.get('First') is True:
            ET.SubElement(programme, 'new')

        log.debug(f'Created programme: {prog_data.get("Title")}')
    except Exception as e:
        log.error(f'HDHomeRun programme creation failed for {prog_data.get("Title", "unknown")}: {e}')


def hd_build_epg_blob(host: str, days: int, hours: int) -> Optional[bytes]:
    """Fetch HDHomeRun EPG and return as raw XML bytes for merging."""
    device_auth = hd_discover_device_auth(host)
    if not device_auth:
        return None

    channels = hd_fetch_channels(host)
    if not channels:
        log.error('HDHomeRun: no channels retrieved')
        return None

    log.info('HDHomeRun EPG extraction started')
    epg_data = hd_fetch_epg_data(device_auth, channels, days, hours)
    log.info('HDHomeRun EPG extraction completed')

    xmltv_root = ET.Element('tv')
    xmltv_root.set('source-info-name', 'HDHomeRun')
    xmltv_root.set('generator-info-name', 'KPTV EPG Sync')

    log.info('HDHomeRun XMLTV transformation started')
    for ch in epg_data.get('channels', []):
        hd_create_channel(ch, xmltv_root)
    for ch in epg_data.get('channels', []):
        guide_number = ch.get('GuideNumber', '')
        for prog in epg_data.get('programmes', []):
            if prog.get('GuideNumber') == guide_number:
                hd_create_programme(prog, guide_number, xmltv_root)
    log.info('HDHomeRun XMLTV transformation completed')

    return ET.tostring(xmltv_root, encoding='unicode').encode('utf-8')


# ---------------------------------------------------------------------------
# Ownership helper
# ---------------------------------------------------------------------------
def chown_to_parent(path: Path) -> None:
    """chown the file to match the uid:gid of its parent directory."""
    try:
        stat = path.parent.stat()
        os.chown(path, stat.st_uid, stat.st_gid)
        log.info(f'chown {stat.st_uid}:{stat.st_gid} -> {path}')
    except Exception as e:
        log.warning(f'chown failed for {path}: {e}')


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    parser = argparse.ArgumentParser(description='KPTV EPG Sync — IPTV providers + HDHomeRun')
    parser.add_argument('--config',          required=True,               help='Path to config.json')
    parser.add_argument('--output',          required=True,               help='Output directory for epg.xml')
    parser.add_argument('--hdhomerun-host',  default=None,                help='HDHomeRun host or IP address (omit to skip)')
    parser.add_argument('--hdhomerun-days',  type=int, default=7,         help='Days of HDHomeRun EPG to fetch (default: 7)')
    parser.add_argument('--hdhomerun-hours', type=int, default=3,         help='Hours per HDHomeRun guide iteration (default: 3)')
    parser.add_argument('--user-id',         type=int, default=None,      help='Process a single IPTV user ID only')
    parser.add_argument('--extra-epg',       action='append', default=[], help='Additional EPG URL(s) to include (can repeat)', metavar='URL')
    args = parser.parse_args()

    config_path = os.path.abspath(args.config)
    if not os.path.isfile(config_path):
        log.error(f'config.json not found at {config_path}')
        sys.exit(1)

    config    = load_config(config_path)
    cache_dir = Path(os.path.abspath(args.output))
    cache_dir.mkdir(parents=True, exist_ok=True)

    start = datetime.datetime.now()
    blobs = []

    # --- IPTV provider EPG (credentials pulled from DB per user) ---
    conn  = get_db(config)
    users = get_users(conn, args.user_id)

    if not users:
        log.warning('No active IPTV users found')
    else:
        for user in users:
            providers = get_providers(conn, user['id'])
            if not providers:
                log.info(f'No providers for user {user["id"]}, skipping')
                continue

            allowed = get_active_stream_identifiers(conn, user['id'])
            if not allowed:
                log.warning(f'No active streams for user {user["id"]}, skipping')
                continue

            urls = [build_epg_url(p) for p in providers]
            log.info(f'User {user["id"]}: fetching {len(urls)} provider(s) in parallel...')
            with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_WORKERS) as pool:
                raw = list(pool.map(fetch_provider_epg, urls))

            fetched = sum(1 for b in raw if b)
            log.info(f'User {user["id"]}: {fetched}/{len(urls)} providers fetched, filtering...')
            filtered = filter_and_merge(raw, allowed)
            if filtered:
                blobs.append(filtered)
            else:
                log.warning(f'User {user["id"]}: no channels matched active streams')

    conn.close()

    # --- Extra EPG URLs ---
    if args.extra_epg:
        log.info(f'Fetching {len(args.extra_epg)} extra EPG URL(s)')
        with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_WORKERS) as pool:
            blobs.extend(b for b in pool.map(fetch_provider_epg, args.extra_epg) if b)

    # --- HDHomeRun EPG ---
    if args.hdhomerun_host:
        blob = hd_build_epg_blob(args.hdhomerun_host, args.hdhomerun_days, args.hdhomerun_hours)
        if blob:
            blobs.append(blob)

    if not blobs:
        log.error('No EPG data retrieved from any source')
        sys.exit(1)

    # --- Merge and write atomically ---
    log.info('Merging all EPG sources...')
    xml_output = merge_epg_blobs(blobs)

    out_path = cache_dir / EPG_OUTPUT
    tmp_fd, tmp_path = tempfile.mkstemp(dir=cache_dir, suffix='.xml.tmp')
    try:
        with os.fdopen(tmp_fd, 'w', encoding='utf-8') as f:
            f.write(xml_output)
        os.replace(tmp_path, out_path)
        log.info(f'EPG written to {out_path}')
    except Exception as e:
        log.error(f'Write failed: {e}')
        try:
            os.unlink(tmp_path)
        except OSError:
            pass
        sys.exit(1)

    chown_to_parent(out_path)

    elapsed = (datetime.datetime.now() - start).total_seconds()
    log.info(f'Done in {elapsed:.1f}s')


if __name__ == '__main__':
    main()