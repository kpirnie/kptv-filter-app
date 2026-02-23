<?php
/**
 * EPG Proxy Controller
 *
 * Streaming EPG passthrough and aggregation for all provider types.
 * Single provider: pure pipe. All providers: parallel fetch, filtered merge.
 *
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPTV_PATH') || die('Direct Access is not allowed!');

if (!class_exists('KPTV_EPG_Proxy')) {

    class KPTV_EPG_Proxy extends \KPT\Database
    {
        public function __construct()
        {
            parent::__construct(KPTV::get_setting('database'));
        }

        // -----------------------------------------------------------------------
        // Single provider — pure pipe
        // /epg/{user}/{provider}
        // -----------------------------------------------------------------------
        public function handleEpgRequest(string $user = '', int $provider = 0): void
        {
            if (empty($user) && isset($_GET['username'], $_GET['password'])) {
                $userId     = KPTV::decrypt($_GET['password']);
                $providerId = (int) $_GET['username'];
            } else {
                $userId     = KPTV::decrypt(urldecode($user));
                $providerId = $provider;
            }

            if (!$userId || !is_numeric($userId) || $providerId < 1) {
                $this->sendError(401);
            }

            $rec = $this->query(
                'SELECT sp_domain, sp_username, sp_password
                   FROM kptv_stream_providers
                  WHERE id = ? AND u_id = ?'
            )->bind([$providerId, (int)$userId])->single()->fetch();

            if (!$rec) $this->sendError(404);

            while (ob_get_level()) ob_end_clean();

            header('Content-Type: application/xml; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Accel-Buffering: no');
            http_response_code(200);

            $this->streamUrl($this->buildEpgUrl($rec));
            exit;
        }

        // -----------------------------------------------------------------------
        // All providers — parallel fetch, filtered merge, streamed output
        // /epg/{user}
        // -----------------------------------------------------------------------
        public function handleAllProvidersEpg(string $user): void
        {
            ini_set('memory_limit', '256M');
            set_time_limit(300);
            while (ob_get_level()) ob_end_clean();

            $userId = KPTV::decrypt(urldecode($user));
            if (!$userId || !is_numeric($userId)) $this->sendError(401);

            $providers = $this->query(
                'SELECT sp_domain, sp_username, sp_password
                   FROM kptv_stream_providers
                  WHERE u_id = ?'
            )->bind([(int)$userId])->fetch();

            if (!$providers) $this->sendError(404);

            // Build allow-list from active streams for this user.
            // Keyed by every non-empty identifier for O(1) lookups.
            $activeStreams = $this->query(
                'SELECT s_tvg_id, s_name, s_orig_name
                   FROM kptv_streams
                  WHERE u_id = ? AND s_active = 1'
            )->bind([(int)$userId])->fetch();

            $allowed = [];
            foreach ($activeStreams as $s) {
                foreach (['s_tvg_id', 's_name', 's_orig_name'] as $col) {
                    $val = trim((string)($s->$col ?? ''));
                    if ($val !== '') $allowed[strtolower($val)] = true;
                }
            }
            unset($activeStreams);

            // -----------------------------------------------------------------------
            // Parallel fetch all providers into tmpfiles via curl_multi
            // -----------------------------------------------------------------------
            $tmpFiles = [];
            $multi    = curl_multi_init();
            $handles  = [];

            foreach ($providers as $i => $rec) {
                $tmp = tmpfile();
                if (!$tmp) continue;

                $ch = curl_init($this->buildEpgUrl($rec));
                curl_setopt_array($ch, [
                    CURLOPT_FILE           => $tmp,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 5,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => 120,
                    CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0',
                    CURLOPT_ENCODING       => '',
                ]);

                curl_multi_add_handle($multi, $ch);
                $handles[$i] = ['ch' => $ch, 'tmp' => $tmp];
            }

            do {
                curl_multi_exec($multi, $running);
                curl_multi_select($multi);
            } while ($running > 0);

            foreach ($handles as $h) {
                $code = curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($multi, $h['ch']);
                curl_close($h['ch']);

                if ($code >= 200 && $code < 400) {
                    rewind($h['tmp']);
                    $tmpFiles[] = $h['tmp'];
                } else {
                    \KPT\Logger::error("EPG fetch failed for provider, HTTP $code");
                    fclose($h['tmp']);
                }
            }

            curl_multi_close($multi);

            if (empty($tmpFiles)) $this->sendError(502);

            header('Content-Type: application/xml; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Accel-Buffering: no');
            http_response_code(200);

            echo '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
            echo '<tv generator-info-name="KPTV Stream Manager">' . PHP_EOL;
            flush();

            $seenChannels   = [];
            $keptChannelIds = [];

            // -----------------------------------------------------------------------
            // Pass 1 — <channel> elements, allow-list filtered + deduplicated
            // readInnerXml() avoids full DOM expansion for nodes we're going to skip.
            // We only call moveToFirstAttribute() which is cheap.
            // -----------------------------------------------------------------------
            foreach ($tmpFiles as $tmp) {
                rewind($tmp);
                $path   = stream_get_meta_data($tmp)['uri'];
                $reader = new \XMLReader();
                if (!@$reader->open($path)) continue;

                while ($reader->read()) {
                    if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'channel') {
                        continue;
                    }

                    $id    = strtolower(trim((string)($reader->getAttribute('id') ?? '')));
                    $inner = $reader->readInnerXml();

                    $match = isset($allowed[$id]);

                    if (!$match) {
                        // Check display-name text via regex — avoids DOM expand() entirely
                        preg_match_all('/<display-name[^>]*>([^<]+)<\/display-name>/i', $inner, $m);
                        foreach ($m[1] as $dn) {
                            if (isset($allowed[strtolower(trim(html_entity_decode($dn, ENT_XML1)))])) {
                                $match = true;
                                break;
                            }
                        }
                    }

                    if (!$match || isset($seenChannels[$id])) continue;

                    $seenChannels[$id]   = true;
                    $keptChannelIds[$id] = true;

                    // Preserve ALL attributes on the channel element
                    $attrs = '';
                    if ($reader->moveToFirstAttribute()) {
                        do {
                            $attrs .= ' ' . $reader->name . '="' . htmlspecialchars($reader->value, ENT_XML1) . '"';
                        } while ($reader->moveToNextAttribute());
                        $reader->moveToElement();
                    }

                    echo '<channel' . $attrs . '>' . $inner . '</channel>' . PHP_EOL;
                    flush();
                }
                $reader->close();
            }

            // -----------------------------------------------------------------------
            // Pass 2 — <programme> elements, filtered to kept channel ids only.
            // All attributes preserved — no data loss.
            // -----------------------------------------------------------------------
            foreach ($tmpFiles as $tmp) {
                rewind($tmp);
                $path   = stream_get_meta_data($tmp)['uri'];
                $reader = new \XMLReader();
                if (!@$reader->open($path)) continue;

                while ($reader->read()) {
                    if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'programme') {
                        continue;
                    }

                    $channel = strtolower(trim((string)($reader->getAttribute('channel') ?? '')));
                    if (!isset($keptChannelIds[$channel])) continue;

                    // Preserve ALL attributes on the programme element
                    $attrs = '';
                    if ($reader->moveToFirstAttribute()) {
                        do {
                            $attrs .= ' ' . $reader->name . '="' . htmlspecialchars($reader->value, ENT_XML1) . '"';
                        } while ($reader->moveToNextAttribute());
                        $reader->moveToElement();
                    }

                    echo '<programme' . $attrs . '>'
                        . $reader->readInnerXml()
                        . '</programme>' . PHP_EOL;
                    flush();
                }
                $reader->close();
            }

            foreach ($tmpFiles as $tmp) fclose($tmp);

            echo '</tv>';
            exit;
        }

        // -----------------------------------------------------------------------
        // Shared curl streaming method.
        //
        // $target = null     → writes directly to PHP output (single-provider pipe)
        // $target = resource → writes to file handle (multi-provider tmpfile fetch)
        //
        // Returns true on success (HTTP 2xx/3xx), false on failure.
        // -----------------------------------------------------------------------
        private function streamUrl(string $url, $target = null): bool
        {
            $toOutput = ($target === null);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'Mozilla/5.0',
                CURLOPT_ENCODING       => '',
                // Strip upstream headers — we set our own
                CURLOPT_HEADERFUNCTION => static fn($ch, $h): int => strlen($h),
                CURLOPT_WRITEFUNCTION  => static function ($ch, $data) use ($toOutput, $target): int {
                    if ($toOutput) {
                        echo $data;
                        flush();
                    } else {
                        fwrite($target, $data);
                    }
                    return strlen($data);
                },
            ]);

            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                \KPT\Logger::error("EPG curl error [$url]: $err");
            }

            return ($code >= 200 && $code < 400);
        }

        // -----------------------------------------------------------------------
        // Build xmltv.php URL from a provider record
        // -----------------------------------------------------------------------
        private function buildEpgUrl(object $rec): string
        {
            $domain   = rtrim((string)$rec->sp_domain, '/');
            $username = (string)($rec->sp_username ?? '');
            $password = (string)($rec->sp_password ?? '');

            $url = $domain . '/xmltv.php';
            if ($username !== '' || $password !== '') {
                $url .= '?' . http_build_query([
                    'username' => $username,
                    'password' => $password,
                ]);
            }
            return $url;
        }

        // -----------------------------------------------------------------------
        // Error response helper
        // -----------------------------------------------------------------------
        private function sendError(int $code): void
        {
            http_response_code($code);
            header('Content-Type: text/plain');
            echo match($code) {
                401     => 'Unauthorized',
                404     => 'Provider not found',
                default => 'Upstream error',
            };
            exit;
        }
    }
}