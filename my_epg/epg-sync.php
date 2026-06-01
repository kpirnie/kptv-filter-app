<?php

/**
 * epg-sync.php
 *
 * KPTV EPG Sync — fetches EPG from all IPTV providers, HDHomeRun, and extra
 * URLs, then merges everything into a single epg.xml.
 *
 * Hit via URL: /my_epg/epg-sync.php
 *
 * @since 1.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KPTV
 */

// ---------------------------------------------------------------------------
// Configuration — edit these before use
// ---------------------------------------------------------------------------

/** HDHomeRun host/IP; set to null to skip */
const HDHOMERUN_HOST  = '192.168.2.25';       // e.g. '192.168.1.x'

/** Days of HDHomeRun EPG to fetch */
const HDHOMERUN_DAYS  = 7;

/** Hours per HDHomeRun guide iteration */
const HDHOMERUN_HOURS = 3;

/** Only process this user ID; set to null for all active users */
const USER_ID         = 1001;       // e.g. 1

/** Additional EPG URLs to merge in; empty array to skip */
const EXTRA_EPG_URLS  = ['https://fast.kptv.im/epg'];         // e.g. ['https://example.com/epg.xml']

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$appPath = dirname(__DIR__) . '/';
require_once $appPath . 'vendor/autoload.php';

$_config    = json_decode(file_get_contents($appPath . 'config.json'), false);
$_db        = $_config->database;

// Boot logger (errors only; flip to true for debug output)
new \KPT\Logger(false);
\KPT\Logger::setLogFile(__DIR__ . '/epg-sync.log');

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

$db = \KPT\Database::configure($_db);

// ---------------------------------------------------------------------------
// DB helpers
// ---------------------------------------------------------------------------

function get_users(\KPT\Database $db): array
{
    $sql = 'SELECT id FROM kptv_users WHERE u_active = 1';
    if (USER_ID !== null) {
        $sql .= ' AND id = ?';
        return $db->query($sql)->bind([USER_ID])->many()->asArray()->fetch() ?: [];
    }
    return $db->query($sql)->many()->asArray()->fetch() ?: [];
}

function get_providers(\KPT\Database $db, int $userId): array
{
    return $db->query(
        'SELECT sp_domain, sp_username, sp_password
         FROM kptv_stream_providers
         WHERE u_id = ?'
    )->bind([$userId])->many()->asArray()->fetch() ?: [];
}

/**
 * Returns a lowercase allow-list of all non-empty identifiers from active streams.
 *
 * @return array<string,true>
 */
function get_active_stream_identifiers(\KPT\Database $db, int $userId): array
{
    $rows = $db->query(
        'SELECT s_tvg_id, s_name, s_orig_name
         FROM kptv_streams
         WHERE u_id = ? AND s_active = 1'
    )->bind([$userId])->many()->asArray()->fetch() ?: [];

    $allowed = [];
    foreach ($rows as $row) {
        foreach (['s_tvg_id', 's_name', 's_orig_name'] as $col) {
            $val = trim((string)($row[$col] ?? ''));
            if ($val !== '') {
                $allowed[strtolower($val)] = true;
            }
        }
    }
    return $allowed;
}

// ---------------------------------------------------------------------------
// EPG URL builder
// ---------------------------------------------------------------------------

function build_epg_url(array $provider): string
{
    $domain   = rtrim(trim((string)$provider['sp_domain']),   '/');
    $username = trim((string)$provider['sp_username']);
    $password = trim((string)$provider['sp_password']);
    $url      = $domain . '/xmltv.php';
    if ($username !== '' || $password !== '') {
        $url .= '?' . http_build_query(['username' => $username, 'password' => $password]);
    }
    return $url;
}

// ---------------------------------------------------------------------------
// HTTP fetch — parallel via \KPT\Curl::multiGet
// ---------------------------------------------------------------------------

/**
 * @param  string[] $urls
 * @return array<int,string|null>
 */
function fetch_urls_parallel(array $urls): array
{
    $responses = \KPT\Curl::multiGet($urls, [
        'timeout'    => 60,
        'user-agent' => 'Mozilla/5.0',
        'sslverify'  => true,
    ], 4);

    $results = [];
    foreach ($urls as $i => $url) {
        $resp = $responses[$i] ?? null;
        if ($resp === null || \KPT\Curl::isError($resp)) {
            \KPT\Logger::warning('Fetch failed for ' . $url . ': ' . ($resp ? \KPT\Curl::getError($resp) : 'no response'));
            $results[$i] = null;
            continue;
        }
        $code = \KPT\Curl::retrieveResponseCode($resp);
        if ($code !== 200) {
            \KPT\Logger::warning("HTTP {$code} for {$url}");
            $results[$i] = null;
            continue;
        }
        $results[$i] = \KPT\Curl::retrieveBody($resp);
    }
    return $results;
}

// ---------------------------------------------------------------------------
// XML filter & merge
// ---------------------------------------------------------------------------

/**
 * Filter provider blobs to the allow-list and return a merged XML string.
 *
 * @param  list<string|null>  $rawBlobs
 * @param  array<string,true> $allowed
 * @return string|null
 */
/**
 * Extract a single element's raw XML from an XMLReader positioned on it.
 */
function xmlreader_outer_xml(\XMLReader $reader): string
{
    $doc = new DOMDocument();
    $doc->loadXML('<root/>');
    $node = $reader->expand($doc);
    if (!$node) {
        return '';
    }
    $doc->documentElement->appendChild($node);
    // Return only the element, not the <root> wrapper
    return $doc->saveXML($doc->documentElement->firstChild);
}

function filter_and_merge(array $rawBlobs, array $allowed): ?string
{
    $seenChannels   = [];
    $keptChannelIds = [];
    $channelXml     = [];
    $programmeXml   = [];

    foreach ($rawBlobs as $blob) {
        if (!$blob) {
            continue;
        }

        // --- Pass 1: channels (XMLReader for low memory) ---
        $reader = new \XMLReader();
        if (!$reader->XML($blob, 'UTF-8', LIBXML_NOERROR | LIBXML_NOWARNING)) {
            \KPT\Logger::error('XML parse error in provider blob (pass 1)');
            continue;
        }
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'channel') {
                continue;
            }
            $chId  = strtolower(trim($reader->getAttribute('id') ?? ''));
            $outerXml = xmlreader_outer_xml($reader);
            if ($outerXml === '') {
                continue;
            }

            // Check id against allow-list
            $match = isset($allowed[$chId]);
            if (!$match) {
                // Check display-name elements
                $inner = @simplexml_load_string($outerXml);
                if ($inner) {
                    foreach ($inner->{'display-name'} as $dn) {
                        if (isset($allowed[strtolower(trim((string)$dn))])) {
                            $match = true;
                            break;
                        }
                    }
                }
            }
            if (!$match || isset($seenChannels[$chId])) {
                continue;
            }
            $seenChannels[$chId]   = true;
            $keptChannelIds[$chId] = true;
            $channelXml[]          = $outerXml;
        }
        $reader->close();

        if (empty($keptChannelIds)) {
            continue;
        }

        // --- Pass 2: programmes ---
        $reader = new \XMLReader();
        $reader->XML($blob, 'UTF-8', LIBXML_NOERROR | LIBXML_NOWARNING);
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'programme') {
                continue;
            }
            $chanRef = strtolower(trim($reader->getAttribute('channel') ?? ''));
            if (!isset($keptChannelIds[$chanRef])) {
                $reader->next();
                continue;
            }
            $outerXml = xmlreader_outer_xml($reader);
            if ($outerXml !== '') {
                $programmeXml[] = $outerXml;
            }
        }
        $reader->close();
    }

    if (empty($channelXml)) {
        return null;
    }

    return '<tv>' . implode('', $channelXml) . implode('', $programmeXml) . '</tv>';
}

/**
 * Final merge of all blobs into one XMLTV document.
 *
 * @param  list<string|null> $blobs
 * @return string
 */
function merge_epg_blobs(array $blobs): string
{
    $seenChannels      = [];
    $dom               = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;
    $tv                = $dom->createElement('tv');
    $tv->setAttribute('generator-info-name', 'KPTV EPG Sync');
    $dom->appendChild($tv);

    foreach ($blobs as $blob) {
        if (!$blob) {
            continue;
        }

        $reader = new \XMLReader();
        if (!$reader->XML($blob, 'UTF-8', LIBXML_NOERROR | LIBXML_NOWARNING)) {
            \KPT\Logger::error('XML parse error during final merge');
            continue;
        }

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->localName === 'channel') {
                $chId = strtolower(trim($reader->getAttribute('id') ?? ''));
                if (isset($seenChannels[$chId])) {
                    $reader->next();
                    continue;
                }
                $node = $reader->expand($dom);
                if ($node) {
                    $seenChannels[$chId] = true;
                    $tv->appendChild($node);
                }
            } elseif ($reader->localName === 'programme') {
                $node = $reader->expand($dom);
                if ($node) {
                    $tv->appendChild($node);
                }
            }
        }
        $reader->close();
    }

    return $dom->saveXML();
}

// ---------------------------------------------------------------------------
// HDHomeRun helpers
// ---------------------------------------------------------------------------

/**
 * Raw cURL fetch — used for HDHomeRun (IP-based URLs that Sanitize::url rejects)
 * and the HDHomeRun cloud API (SSL unverified).
 *
 * @return array{code:int,body:string,error:string}
 */
function hd_curl_get(string $url, bool $sslVerify = true): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => $sslVerify,
        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
    ]);
    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => ($body ?: ''), 'error' => ($errno ? $err : '')];
}

function hd_discover_device_auth(string $host): string
{
    \KPT\Logger::info("Fetching HDHomeRun device auth from {$host}");
    $resp = hd_curl_get("http://{$host}/discover.json");
    if ($resp['error'] !== '') {
        \KPT\Logger::error('HDHomeRun discover failed: ' . $resp['error']);
        return '';
    }
    $data = json_decode($resp['body'], true);
    if (empty($data['DeviceAuth'])) {
        \KPT\Logger::error('HDHomeRun: no DeviceAuth in discover response');
        return '';
    }
    \KPT\Logger::info('Discovered device auth: ' . $data['DeviceAuth']);
    return $data['DeviceAuth'];
}

function hd_fetch_channels(string $host): array
{
    \KPT\Logger::info("Fetching HDHomeRun lineup from {$host}");
    $resp = hd_curl_get("http://{$host}/lineup.json");
    if ($resp['error'] !== '') {
        \KPT\Logger::error('HDHomeRun lineup fetch failed: ' . $resp['error']);
        return [];
    }
    return json_decode($resp['body'], true) ?? [];
}

function hd_fetch_epg_data(string $deviceAuth, array $channels, int $days, int $hours): array
{
    $epgData   = ['channels' => [], 'programmes' => []];
    $baseUrl   = "https://api.hdhomerun.com/api/guide.php?DeviceAuth={$deviceAuth}";
    $nextStart = time();
    $endTime   = $nextStart + ($days * 86400);

    while ($nextStart < $endTime) {
        $resp = hd_curl_get("{$baseUrl}&Start={$nextStart}", false);
        $code = $resp['code'];

        if ($code === 400) {
            \KPT\Logger::debug('HDHomeRun guide data exhausted at ' . date('Y-m-d H:i:s', $nextStart));
            break;
        }
        if ($resp['error'] !== '' || $code >= 400) {
            \KPT\Logger::error('HDHomeRun EPG fetch failed at ' . date('Y-m-d H:i:s', $nextStart));
            break;
        }

        $segment = json_decode($resp['body'], true);
        if (!is_array($segment)) {
            break;
        }

        \KPT\Logger::info('Processing EPG segment from ' . date('Y-m-d H:i:s', $nextStart));
        foreach ($segment as $chSeg) {
            $guideNumber = $chSeg['GuideNumber'] ?? '';
            $channel     = null;
            foreach ($channels as $c) {
                if (($c['GuideNumber'] ?? '') === $guideNumber) {
                    $channel = $c;
                    break;
                }
            }
            if ($channel === null) {
                continue;
            }

            foreach ($chSeg['Guide'] ?? [] as $prog) {
                // Skip duplicates
                foreach ($epgData['programmes'] as $existing) {
                    if (
                        $existing['StartTime']   === $prog['StartTime'] &&
                        $existing['Title']       === $prog['Title'] &&
                        $existing['GuideNumber'] === $guideNumber
                    ) {
                        continue 2;
                    }
                }

                // Add channel entry once
                $alreadyAdded = false;
                foreach ($epgData['channels'] as $ec) {
                    if (($ec['GuideNumber'] ?? '') === $guideNumber) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                if (!$alreadyAdded) {
                    $channel['ImageURL']     = $chSeg['ImageURL'] ?? '';
                    $epgData['channels'][]   = $channel;
                }

                $prog['GuideNumber']       = $guideNumber;
                $epgData['programmes'][]   = $prog;
            }
        }

        $nextStart += $hours * 3600;
    }

    return $epgData;
}

function hd_create_channel(array $channelData, DOMElement $tv, DOMDocument $dom): void
{
    $ch = $dom->createElement('channel');
    $ch->setAttribute('id', (string)($channelData['GuideNumber'] ?? ''));
    $dn = $dom->createElement('display-name', htmlspecialchars((string)($channelData['GuideName'] ?? 'Unknown')));
    $ch->appendChild($dn);
    $icon = $dom->createElement('icon');
    $icon->setAttribute('src', (string)($channelData['ImageURL'] ?? ''));
    $ch->appendChild($icon);
    $tv->appendChild($ch);
}

function hd_create_programme(array $progData, string $channelNumber, DOMElement $tv, DOMDocument $dom): void
{
    try {
        $tz      = new DateTimeZone(date_default_timezone_get());
        $startDt = (new DateTime('@' . (int)$progData['StartTime']))->setTimezone($tz);
        $endDt   = (new DateTime('@' . (int)($progData['EndTime'] ?? $progData['StartTime'])))->setTimezone($tz);

        $prog = $dom->createElement('programme');
        $prog->setAttribute('start',   $startDt->format('YmdHis O'));
        $prog->setAttribute('stop',    $endDt->format('YmdHis O'));
        $prog->setAttribute('channel', $channelNumber);

        // <title>
        $t = $dom->createElement('title', htmlspecialchars((string)($progData['Title'] ?? '')));
        $t->setAttribute('lang', 'en');
        $prog->appendChild($t);

        // <sub-title>
        if (isset($progData['EpisodeTitle'])) {
            $s = $dom->createElement('sub-title', htmlspecialchars((string)$progData['EpisodeTitle']));
            $s->setAttribute('lang', 'en');
            $prog->appendChild($s);
        }

        // <desc>
        if (isset($progData['Synopsis'])) {
            $d = $dom->createElement('desc', htmlspecialchars((string)$progData['Synopsis']));
            $d->setAttribute('lang', 'en');
            $prog->appendChild($d);
        }

        // <category>
        foreach ((array)($progData['Filter'] ?? []) as $f) {
            $cat = $dom->createElement('category', htmlspecialchars((string)$f));
            $cat->setAttribute('lang', 'en');
            $prog->appendChild($cat);
        }

        // <icon>
        if (isset($progData['ImageURL'])) {
            $ic = $dom->createElement('icon');
            $ic->setAttribute('src', (string)$progData['ImageURL']);
            $prog->appendChild($ic);
        }

        // <episode-num>
        if (isset($progData['EpisodeNumber'])) {
            $epNum = (string)$progData['EpisodeNumber'];
            $sPos  = strpos($epNum, 'S');
            $ePos  = strpos($epNum, 'E');
            if ($sPos !== false && $ePos !== false) {
                $series  = (int)substr($epNum, $sPos + 1, $ePos - $sPos - 1) - 1;
                $episode = (int)substr($epNum, $ePos + 1) - 1;

                $eos = $dom->createElement('episode-num', htmlspecialchars($epNum));
                $eos->setAttribute('system', 'onscreen');
                $prog->appendChild($eos);

                $ens = $dom->createElement('episode-num', "{$series}.{$episode}.0/0");
                $ens->setAttribute('system', 'xmltv_ns');
                $prog->appendChild($ens);
            }
        }

        // <previously-shown>
        if (isset($progData['OriginalAirdate'])) {
            $airDt    = (new DateTime('@' . (int)$progData['OriginalAirdate']))->setTimezone($tz);
            $startDay = (clone $startDt)->setTime(0, 0, 0);
            if ($airDt != $startDay) {
                $ps = $dom->createElement('previously-shown');
                $ps->setAttribute('start', $airDt->format('YmdHis'));
                $prog->appendChild($ps);
            } elseif (($progData['First'] ?? false) !== true) {
                $prog->appendChild($dom->createElement('previously-shown'));
            }
        }

        // <new>
        if (($progData['First'] ?? false) === true) {
            $prog->appendChild($dom->createElement('new'));
        }

        $tv->appendChild($prog);
    } catch (\Throwable $e) {
        \KPT\Logger::error('HDHomeRun programme creation failed for ' . ($progData['Title'] ?? 'unknown') . ': ' . $e->getMessage());
    }
}

function hd_build_epg_blob(string $host, int $days, int $hours): ?string
{
    $deviceAuth = hd_discover_device_auth($host);
    if ($deviceAuth === '') {
        return null;
    }

    $channels = hd_fetch_channels($host);
    if (empty($channels)) {
        \KPT\Logger::error('HDHomeRun: no channels retrieved');
        return null;
    }

    \KPT\Logger::info('HDHomeRun EPG extraction started');
    $epgData = hd_fetch_epg_data($deviceAuth, $channels, $days, $hours);
    \KPT\Logger::info('HDHomeRun EPG extraction completed');

    $dom               = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;
    $tv                = $dom->createElement('tv');
    $tv->setAttribute('source-info-name',    'HDHomeRun');
    $tv->setAttribute('generator-info-name', 'KPTV EPG Sync');
    $dom->appendChild($tv);

    \KPT\Logger::info('HDHomeRun XMLTV transformation started');
    foreach ($epgData['channels'] as $ch) {
        hd_create_channel($ch, $tv, $dom);
    }
    foreach ($epgData['channels'] as $ch) {
        $guideNumber = $ch['GuideNumber'] ?? '';
        foreach ($epgData['programmes'] as $prog) {
            if (($prog['GuideNumber'] ?? '') === $guideNumber) {
                hd_create_programme($prog, $guideNumber, $tv, $dom);
            }
        }
    }
    \KPT\Logger::info('HDHomeRun XMLTV transformation completed');

    return $dom->saveXML();
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

header('Content-Type: text/plain; charset=utf-8');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 'off');

$start = microtime(true);
$blobs = [];

// -- IPTV provider EPG -------------------------------------------------------
$users = get_users($db);
if (empty($users)) {
    \KPT\Logger::warning('No active IPTV users found');
} else {
    foreach ($users as $user) {
        $uid       = (int)$user['id'];
        $providers = get_providers($db, $uid);

        if (empty($providers)) {
            \KPT\Logger::info("No providers for user {$uid}, skipping");
            continue;
        }

        $allowed = get_active_stream_identifiers($db, $uid);
        if (empty($allowed)) {
            \KPT\Logger::warning("No active streams for user {$uid}, skipping");
            continue;
        }

        $urls = array_map('build_epg_url', $providers);
        \KPT\Logger::info("User {$uid}: fetching " . count($urls) . ' provider(s)...');
        $raw = fetch_urls_parallel($urls);

        $fetched = count(array_filter($raw));
        \KPT\Logger::info("User {$uid}: {$fetched}/" . count($urls) . ' providers fetched, filtering...');

        $filtered = filter_and_merge($raw, $allowed);
        if ($filtered !== null) {
            $blobs[] = $filtered;
        } else {
            \KPT\Logger::warning("User {$uid}: no channels matched active streams");
        }
    }
}

// -- Extra EPG URLs ----------------------------------------------------------
if (!empty(EXTRA_EPG_URLS)) {
    \KPT\Logger::info('Fetching ' . count(EXTRA_EPG_URLS) . ' extra EPG URL(s)');
    foreach (fetch_urls_parallel(EXTRA_EPG_URLS) as $b) {
        if ($b !== null) {
            $blobs[] = $b;
        }
    }
}

// -- HDHomeRun EPG -----------------------------------------------------------
if (HDHOMERUN_HOST !== null) {
    $blob = hd_build_epg_blob(HDHOMERUN_HOST, HDHOMERUN_DAYS, HDHOMERUN_HOURS);
    if ($blob !== null) {
        $blobs[] = $blob;
    }
}

if (empty($blobs)) {
    \KPT\Logger::error('No EPG data retrieved from any source');
    exit(1);
}

// -- Merge and write atomically ----------------------------------------------
\KPT\Logger::info('Merging all EPG sources...');
$xmlOutput = merge_epg_blobs($blobs);

$outPath = __DIR__ . '/epg.xml';
$tmpPath = __DIR__ . '/epg.xml.tmp.' . getmypid();

if (file_put_contents($tmpPath, $xmlOutput) === false) {
    \KPT\Logger::error("Write failed: {$tmpPath}");
    exit(1);
}

if (!rename($tmpPath, $outPath)) {
    \KPT\Logger::error("Atomic rename failed: {$tmpPath} -> {$outPath}");
    @unlink($tmpPath);
    exit(1);
}

\KPT\Logger::info("EPG written to {$outPath}");
\KPT\Logger::info('Done in ' . round(microtime(true) - $start, 1) . 's');

echo 'Done. EPG written to ' . $outPath . PHP_EOL;
