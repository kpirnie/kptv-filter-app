<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

class CurlClient
{
    private int $cnxLimit;
    private int $maxRetries;
    private int $retryDelay;
    private int $connectTimeout;
    private int $timeout;

    private static array $defaultHeaders = [
        'Accept: */*',
        'Connection: keep-alive',
    ];

    public function __construct(
        int $cnxLimit      = 1,
        int $maxRetries    = 3,
        int $retryDelay    = 2,
        int $connectTimeout = 10,
        int $timeout       = 60
    ) {
        $this->cnxLimit       = max(1, $cnxLimit);
        $this->maxRetries     = $maxRetries;
        $this->retryDelay     = $retryDelay;
        $this->connectTimeout = $connectTimeout;
        $this->timeout        = $timeout;
    }

    /**
     * Fetch a single URL. Returns response body as string.
     * Retries on connection/timeout errors.
     */
    public function get(string $url, array $headers = []): string
    {
        $attempt = 0;

        while (true) {
            $ch = $this->buildHandle($url, $headers);

            $body  = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body !== false && $errno === 0) {
                // Treat 4xx/5xx as fatal — no retry will help auth errors
                if ($httpCode >= 400) {
                    throw new \RuntimeException(
                        "HTTP {$httpCode} response — {$url}"
                    );
                }
                return $body;
            }

            $attempt++;
            if ($attempt >= $this->maxRetries) {
                throw new \RuntimeException(
                    "curl failed after {$this->maxRetries} attempts [{$errno}]: {$error} — {$url}"
                );
            }

            $delay = in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT], true)
                ? $this->retryDelay * $attempt
                : $this->retryDelay;

            sleep($delay);
        }
    }

    /**
     * Fetch multiple URLs in parallel, rolling window capped at $cnxLimit.
     * Returns array keyed by the same keys as $urls.
     *
     * @param  array<int|string, string> $urls
     * @return array<int|string, string>
     */
    public function getMulti(array $urls, array $headers = []): array
    {
        $results = [];
        $pending = $urls;
        $active  = [];   // key => curl handle
        $multi   = curl_multi_init();

        $addNext = function () use (&$pending, &$active, $multi, $headers): void {
            while (count($active) < $this->cnxLimit && !empty($pending)) {
                $key = array_key_first($pending);
                $url = array_shift($pending);
                $ch  = $this->buildHandle($url, $headers);
                curl_multi_add_handle($multi, $ch);
                $active[$key] = $ch;
            }
        };

        $addNext();

        do {
            $status = curl_multi_exec($multi, $running);
            if ($status > CURLM_OK) {
                break;
            }

            while ($info = curl_multi_info_read($multi)) {
                $done    = $info['handle'];
                $doneKey = array_search($done, $active, true);

                $body  = curl_multi_getcontent($done);
                $errno = curl_errno($done);

                $results[$doneKey] = ($errno === 0 && $body !== false) ? $body : '';

                curl_multi_remove_handle($multi, $done);
                curl_close($done);
                unset($active[$doneKey]);

                $addNext();
            }

            if ($running) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running || !empty($active));

        curl_multi_close($multi);

        return $results;
    }

    private function buildHandle(string $url, array $extraHeaders = []): \CurlHandle
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER     => array_merge(self::$defaultHeaders, $extraHeaders),
        ]);
        return $ch;
    }
}
