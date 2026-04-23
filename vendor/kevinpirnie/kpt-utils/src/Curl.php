<?php

/**
 * cURL Functions
 *
 * This is our primary cURL utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Curl')) {

    /**
     * Curl
     *
     * A modern PHP 8.2+ HTTP client modelled after WordPress's HTTP API.
     * All methods return a consistent response array; use isError() to check
     * for failure before consuming body/headers/cookies.
     *
     * Response array shape:
     * <code>
     * [
     *     'headers'  => ['header-name' => 'value'],
     *     'body'     => '...',
     *     'response' => ['code' => 200, 'message' => 'OK'],
     *     'cookies'  => ['name' => 'value'],
     *     'error'    => '',
     * ]
     * </code>
     *
     * Options array shape:
     * <code>
     * [
     *     'method'      => 'GET',
     *     'timeout'     => 5,
     *     'redirection' => 5,
     *     'headers'     => ['X-Custom' => 'value'],
     *     'body'        => 'raw string or array (form-encoded)',
     *     'cookies'     => ['name' => 'value'],
     *     'sslverify'   => true,
     *     'user-agent'  => 'KPT-Utils/1.0',
     *     'auth'        => ['user', 'pass', 'basic|digest|bearer'],
     *     'decompress'  => true,
     * ]
     * </code>
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Curl
    {
        // -------------------------------------------------------------------------
        // Constants
        // -------------------------------------------------------------------------

        /** Default request timeout in seconds */
        private const DEFAULT_TIMEOUT   = 5;

        /** Default maximum number of redirects to follow */
        private const DEFAULT_REDIRECTS = 5;

        /** Default User-Agent header value */
        private const USER_AGENT        = 'KPT-Utils/1.0';

        /** HTTP method constants */
        public const METHOD_GET    = 'GET';
        public const METHOD_POST   = 'POST';
        public const METHOD_PUT    = 'PUT';
        public const METHOD_PATCH  = 'PATCH';
        public const METHOD_DELETE = 'DELETE';
        public const METHOD_HEAD   = 'HEAD';

        // -------------------------------------------------------------------------
        // Core request
        // -------------------------------------------------------------------------

        /**
         * Perform an HTTP request.
         *
         * @param  string  $url      The URL to request.
         * @param  array   $options  Request options — see class docblock for shape.
         * @return array             Response array — see class docblock for shape.
         */
        public static function request(string $url, array $options = []): array
        {
            if (! extension_loaded('curl')) {
                return self::buildError('The curl extension is required.');
            }

            $url = \KPT\Sanitize::url($url);

            if ($url === '') {
                return self::buildError('Invalid or empty URL.');
            }

            $opts = array_merge([
                'method'      => self::METHOD_GET,
                'timeout'     => self::DEFAULT_TIMEOUT,
                'redirection' => self::DEFAULT_REDIRECTS,
                'headers'     => [],
                'body'        => null,
                'cookies'     => [],
                'sslverify'   => true,
                'user-agent'  => self::USER_AGENT,
                'auth'        => [],
                'decompress'  => true,
            ], $options);

            $responseHeaders = [];
            $rawCookies      = [];
            $ch              = self::buildHandle($url, $opts, $responseHeaders, $rawCookies);
            $body            = curl_exec($ch);
            $errno           = curl_errno($ch);
            $errStr          = curl_error($ch);
            $info            = curl_getinfo($ch);

            curl_close($ch);

            if ($errno !== 0) {
                return self::buildError($errStr !== '' ? $errStr : 'cURL error ' . $errno);
            }

            return [
                'headers'  => $responseHeaders,
                'body'     => $body === false ? '' : $body,
                'response' => [
                    'code'    => (int) $info['http_code'],
                    'message' => self::statusMessage((int) $info['http_code']),
                ],
                'cookies'  => self::parseCookies($rawCookies),
                'error'    => '',
            ];
        }

        // -------------------------------------------------------------------------
        // HTTP method shortcuts
        // -------------------------------------------------------------------------

        /**
         * Perform a GET request.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function get(string $url, array $options = []): array
        {
            return self::request($url, array_merge($options, ['method' => self::METHOD_GET]));
        }

        /**
         * Perform a POST request.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function post(string $url, array $options = []): array
        {
            return self::request($url, array_merge($options, ['method' => self::METHOD_POST]));
        }

        /**
         * Perform a PUT request.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function put(string $url, array $options = []): array
        {
            return self::request($url, array_merge($options, ['method' => self::METHOD_PUT]));
        }

        /**
         * Perform a PATCH request.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function patch(string $url, array $options = []): array
        {
            return self::request($url, array_merge($options, ['method' => self::METHOD_PATCH]));
        }

        /**
         * Perform a DELETE request.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function delete(string $url, array $options = []): array
        {
            return self::request($url, array_merge($options, ['method' => self::METHOD_DELETE]));
        }

        /**
         * Perform a HEAD request.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function head(string $url, array $options = []): array
        {
            return self::request($url, array_merge($options, ['method' => self::METHOD_HEAD]));
        }

        /**
         * Perform multiple HTTP requests concurrently.
         *
         * Each entry in $requests must contain a 'url' key plus any per-request
         * options which override the shared $options defaults.  Results are keyed
         * by the same keys as the input array.  Individual failures return a
         * buildError() response for that key — the batch never fails as a whole.
         *
         * @param  array  $requests     Keyed array of request option arrays, each with a 'url' key.
         * @param  array  $options      Shared options applied to every request.
         * @param  int    $concurrency  Max simultaneous handles (0 = unlimited).
         * @return array                Keyed response arrays matching the input keys.
         */
        public static function multiRequest(array $requests, array $options = [], int $concurrency = 0): array
        {
            if (! extension_loaded('curl')) {
                return array_map(fn(): array => self::buildError('The curl extension is required.'), $requests);
            }

            $handles       = [];
            $headerStorage = [];
            $cookieStorage = [];
            $results       = [];

            foreach ($requests as $key => $request) {
                $url = \KPT\Sanitize::url((string) ($request['url'] ?? ''));

                if ($url === '') {
                    $results[$key] = self::buildError('Invalid or empty URL.');
                    continue;
                }

                // Per-request options override global options; strip 'url' — it is not an option
                $opts = array_merge([
                    'method'      => self::METHOD_GET,
                    'timeout'     => self::DEFAULT_TIMEOUT,
                    'redirection' => self::DEFAULT_REDIRECTS,
                    'headers'     => [],
                    'body'        => null,
                    'cookies'     => [],
                    'sslverify'   => true,
                    'user-agent'  => self::USER_AGENT,
                    'auth'        => [],
                    'decompress'  => true,
                ], $options, array_diff_key($request, ['url' => '']));

                $headerStorage[$key] = [];
                $cookieStorage[$key] = [];

                $handles[$key] = self::buildHandle($url, $opts, $headerStorage[$key], $cookieStorage[$key]);
            }

            if (! empty($handles)) {
                $results += self::executeMulti($handles, $headerStorage, $cookieStorage, $concurrency);
            }

            return $results;
        }

        /**
         * Perform multiple GET requests concurrently.
         *
         * @param  array  $urls         Keyed or indexed array of URLs.
         * @param  array  $options      Shared options applied to every request.
         * @param  int    $concurrency  Max simultaneous handles (0 = unlimited).
         * @return array                Keyed response arrays matching the input keys.
         */
        public static function multiGet(array $urls, array $options = [], int $concurrency = 0): array
        {
            return self::multiRequest(
                array_map(fn(string $url): array => ['url' => $url, 'method' => self::METHOD_GET], $urls),
                $options,
                $concurrency
            );
        }

        /**
         * Perform multiple POST requests concurrently.
         *
         * Each entry in $requests must contain a 'url' key and may include a
         * 'body' key with the POST payload.
         *
         * @param  array  $requests     Keyed array of request arrays, each with a 'url' key.
         * @param  array  $options      Shared options applied to every request.
         * @param  int    $concurrency  Max simultaneous handles (0 = unlimited).
         * @return array                Keyed response arrays matching the input keys.
         */
        public static function multiPost(array $requests, array $options = [], int $concurrency = 0): array
        {
            return self::multiRequest(
                array_map(function (array $request): array {
                    $request['method'] = self::METHOD_POST;
                    return $request;
                }, $requests),
                $options,
                $concurrency
            );
        }

        // -------------------------------------------------------------------------
        // Safe variants
        // -------------------------------------------------------------------------

        /**
         * Perform a GET request, rejecting private and loopback addresses.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function safeGet(string $url, array $options = []): array
        {
            if (self::isPrivateUrl($url)) {
                return self::buildError('Requests to private or loopback addresses are not permitted.');
            }

            return self::get($url, $options);
        }

        /**
         * Perform a POST request, rejecting private and loopback addresses.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function safePost(string $url, array $options = []): array
        {
            if (self::isPrivateUrl($url)) {
                return self::buildError('Requests to private or loopback addresses are not permitted.');
            }

            return self::post($url, $options);
        }

        /**
         * Perform a PUT request, rejecting private and loopback addresses.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function safePut(string $url, array $options = []): array
        {
            if (self::isPrivateUrl($url)) {
                return self::buildError('Requests to private or loopback addresses are not permitted.');
            }

            return self::put($url, $options);
        }

        /**
         * Perform a PATCH request, rejecting private and loopback addresses.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function safePatch(string $url, array $options = []): array
        {
            if (self::isPrivateUrl($url)) {
                return self::buildError('Requests to private or loopback addresses are not permitted.');
            }

            return self::patch($url, $options);
        }

        /**
         * Perform a DELETE request, rejecting private and loopback addresses.
         *
         * @param  string  $url
         * @param  array   $options
         * @return array
         */
        public static function safeDelete(string $url, array $options = []): array
        {
            if (self::isPrivateUrl($url)) {
                return self::buildError('Requests to private or loopback addresses are not permitted.');
            }

            return self::delete($url, $options);
        }

        /**
         * Return a callable that prepends a base URL to all requests.
         *
         * Useful when making multiple calls to the same API without repeating
         * the base URL on every call.
         *
         * Example:
         * <code>
         * $api = Curl::withBaseUrl('https://api.example.com/v1');
         * $response = $api('get', '/users', ['headers' => ['Authorization' => 'Bearer ...']]);
         * </code>
         *
         * @param  string  $baseUrl        The base URL to prepend.
         * @param  array   $defaultOptions Options merged into every request.
         * @return \Closure
         */
        public static function withBaseUrl(string $baseUrl, array $defaultOptions = []): \Closure
        {
            $baseUrl = rtrim($baseUrl, '/');

            return function (
                string $method,
                string $path = '',
                array $options = []
            ) use (
                $baseUrl,
                $defaultOptions
            ): array {
                $url     = $baseUrl . '/' . ltrim($path, '/');
                $method  = strtolower($method);
                $options = array_merge($defaultOptions, $options);

                // Delegate to the matching static method if it exists, otherwise request()
                return match ($method) {
                    'get'    => self::get($url, $options),
                    'post'   => self::post($url, $options),
                    'put'    => self::put($url, $options),
                    'patch'  => self::patch($url, $options),
                    'delete' => self::delete($url, $options),
                    'head'   => self::head($url, $options),
                    default  => self::request($url, array_merge($options, ['method' => strtoupper($method)])),
                };
            };
        }

        // -------------------------------------------------------------------------
        // Response helpers
        // -------------------------------------------------------------------------

        /**
         * Retrieve the response body.
         *
         * @param  array  $response
         * @return string
         */
        public static function retrieveBody(array $response): string
        {
            return (string) ($response['body'] ?? '');
        }

        /**
         * Retrieve all response headers.
         *
         * @param  array  $response
         * @return array
         */
        public static function retrieveHeaders(array $response): array
        {
            return (array) ($response['headers'] ?? []);
        }

        /**
         * Retrieve a single response header by name (case-insensitive).
         *
         * @param  array   $response
         * @param  string  $header
         * @return string  Header value, or empty string when absent.
         */
        public static function retrieveHeader(array $response, string $header): string
        {
            return (string) (self::retrieveHeaders($response)[strtolower($header)] ?? '');
        }

        /**
         * Retrieve the HTTP response code.
         *
         * @param  array  $response
         * @return int
         */
        public static function retrieveResponseCode(array $response): int
        {
            return (int) ($response['response']['code'] ?? 0);
        }

        /**
         * Retrieve the HTTP response message.
         *
         * @param  array  $response
         * @return string
         */
        public static function retrieveResponseMessage(array $response): string
        {
            return (string) ($response['response']['message'] ?? '');
        }

        /**
         * Retrieve all response cookies.
         *
         * @param  array  $response
         * @return array
         */
        public static function retrieveCookies(array $response): array
        {
            return (array) ($response['cookies'] ?? []);
        }

        /**
         * Check whether the response represents an error.
         *
         * @param  array  $response
         * @return bool
         */
        public static function isError(array $response): bool
        {
            return ! empty($response['error']);
        }

        /**
         * Retrieve the error message from a failed response.
         *
         * @param  array  $response
         * @return string  Error message, or empty string when no error.
         */
        public static function getError(array $response): string
        {
            return (string) ($response['error'] ?? '');
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Apply authentication credentials to the cURL handle.
         *
         * Supports basic, digest, and bearer token authentication.
         *
         * @param  \CurlHandle  $ch       Active cURL handle.
         * @param  array        &$headers Request headers array (modified in place for bearer).
         * @param  array        $auth     Auth tuple: [user, pass, type] or [token, '', 'bearer'].
         * @return void
         */
        private static function applyAuth(\CurlHandle $ch, array &$headers, array $auth): void
        {
            $user = (string) ($auth[0] ?? '');
            $pass = (string) ($auth[1] ?? '');
            $type = strtolower((string) ($auth[2] ?? 'basic'));

            switch ($type) {
                case 'bearer':
                    // Bearer token is passed as the first element
                    $headers[] = 'Authorization: Bearer ' . $user;
                    break;
                case 'digest':
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
                    break;
                default:
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
            }
        }

        /**
         * Parse raw Set-Cookie header values into a name => value map.
         *
         * Only the cookie name and value are extracted; attributes such as
         * Path, Domain, Expires, and HttpOnly are intentionally discarded.
         *
         * @param  array  $rawCookies  Raw Set-Cookie header strings.
         * @return array
         */
        private static function parseCookies(array $rawCookies): array
        {
            $cookies = [];

            foreach ($rawCookies as $raw) {
                // The first segment before ';' is always name=value
                $pair = explode('=', explode(';', $raw)[0], 2);

                if (count($pair) === 2) {
                    $cookies[trim($pair[0])] = trim($pair[1]);
                }
            }

            return $cookies;
        }

        /**
         * Check whether a URL resolves to a private or loopback address.
         *
         * Used by safeGet() and safePost() to block SSRF vectors.
         *
         * @param  string  $url
         * @return bool  True when the URL is private or unresolvable.
         */
        private static function isPrivateUrl(string $url): bool
        {
            $host = parse_url($url, PHP_URL_HOST);

            if (! $host) {
                return true;
            }

            // Resolve the hostname to an IP address
            $ip = gethostbyname((string) $host);

            // gethostbyname returns the input unchanged when resolution fails
            if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
                return true;
            }

            // Reject private, loopback, and reserved IP ranges
            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }

        /**
         * Build a consistent error response array.
         *
         * @param  string  $message
         * @return array
         */
        private static function buildError(string $message): array
        {
            return [
                'headers'  => [],
                'body'     => '',
                'response' => ['code' => 0, 'message' => ''],
                'cookies'  => [],
                'error'    => $message,
            ];
        }

        /**
         * Build and configure a cURL handle from a sanitized URL and options array.
         *
         * Extracted from request() so both single and multi methods share identical
         * handle configuration.  Header and cookie storage are populated via the
         * CURLOPT_HEADERFUNCTION callback and passed back through the references.
         *
         * @param  string        $url
         * @param  array         $opts
         * @param  array         &$responseHeaders  Populated by the header callback.
         * @param  array         &$rawCookies       Populated by the header callback.
         * @return \CurlHandle
         */
        private static function buildHandle(
            string $url,
            array $opts,
            array &$responseHeaders,
            array &$rawCookies
        ): \CurlHandle {
            $method          = strtoupper((string) ($opts['method'] ?? self::METHOD_GET));
            $responseHeaders = [];
            $rawCookies      = [];
            $ch              = curl_init();

            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function (
                \CurlHandle $ch,
                string $header
            ) use (
                &$responseHeaders,
                &$rawCookies
            ): int {
                $len   = strlen($header);
                $parts = explode(':', $header, 2);

                if (count($parts) === 2) {
                    $name  = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);

                    if ($name === 'set-cookie') {
                        $rawCookies[] = $value;
                    } else {
                        $responseHeaders[$name] = $value;
                    }
                }

                return $len;
            });

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int) ($opts['timeout'] ?? self::DEFAULT_TIMEOUT),
                CURLOPT_MAXREDIRS      => (int) ($opts['redirection'] ?? self::DEFAULT_REDIRECTS),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => (string) ($opts['user-agent'] ?? self::USER_AGENT),
                CURLOPT_SSL_VERIFYPEER => (bool) ($opts['sslverify'] ?? true),
                CURLOPT_SSL_VERIFYHOST => ($opts['sslverify'] ?? true) ? 2 : 0,
            ]);

            if ($opts['decompress'] ?? true) {
                curl_setopt($ch, CURLOPT_ENCODING, '');
            }

            switch ($method) {
                case self::METHOD_POST:
                    curl_setopt($ch, CURLOPT_POST, true);
                    break;
                case self::METHOD_HEAD:
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    break;
                case self::METHOD_GET:
                    curl_setopt($ch, CURLOPT_HTTPGET, true);
                    break;
                default:
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }

            if (($opts['body'] ?? null) !== null && $method !== self::METHOD_HEAD) {
                $body = is_array($opts['body'])
                    ? http_build_query($opts['body'])
                    : (string) $opts['body'];
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $headers = [];

            foreach ((array) ($opts['headers'] ?? []) as $name => $value) {
                $headers[] = $name . ': ' . $value;
            }

            if (! empty($opts['cookies'])) {
                $cookieStr = implode('; ', array_map(
                    fn(string $k, string $v): string => $k . '=' . $v,
                    array_keys($opts['cookies']),
                    array_values($opts['cookies'])
                ));
                curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
            }

            if (! empty($opts['auth'])) {
                self::applyAuth($ch, $headers, (array) $opts['auth']);
            }

            if (! empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }

            return $ch;
        }

        /**
         * Execute a pool of cURL handles concurrently via curl_multi.
         *
         * When $concurrency is 0 all handles are added at once.  When greater than 0
         * handles are fed into the pool as slots free up, keeping exactly $concurrency
         * requests in flight at any time.
         *
         * @param  array  $handles        Keyed array of CurlHandle instances.
         * @param  array  &$headerStorage Per-key response header arrays.
         * @param  array  &$cookieStorage Per-key raw cookie arrays.
         * @param  int    $concurrency    Max simultaneous handles (0 = unlimited).
         * @return array                  Keyed response arrays.
         */
        private static function executeMulti(
            array $handles,
            array &$headerStorage,
            array &$cookieStorage,
            int $concurrency = 0
        ): array {
            $mh      = curl_multi_init();
            $pending = $handles;
            $active  = [];
            $results = [];

            do {
                // Fill the active pool up to the concurrency limit; 0 means add everything at once
                while (! empty($pending) && ($concurrency === 0 || count($active) < $concurrency)) {
                    $key = array_key_first($pending);
                    $ch  = array_shift($pending);
                    curl_multi_add_handle($mh, $ch);
                    $active[$key] = $ch;
                }

                $running = 0;
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.1);

                // Drain any handles that have completed since the last iteration
                while ($info = curl_multi_info_read($mh)) {
                    if ($info['msg'] !== CURLMSG_DONE) {
                        continue;
                    }

                    $done = $info['handle'];
                    $key  = array_search($done, $active, true);

                    if ($key === false) {
                        continue;
                    }

                    $body     = curl_multi_getcontent($done);
                    $errno    = curl_errno($done);
                    $errStr   = curl_error($done);
                    $curlInfo = curl_getinfo($done);

                    curl_multi_remove_handle($mh, $done);
                    curl_close($done);
                    unset($active[$key]);

                    $results[$key] = $errno !== 0
                        ? self::buildError($errStr !== '' ? $errStr : 'cURL error ' . $errno)
                        : [
                            'headers'  => $headerStorage[$key] ?? [],
                            'body'     => $body === false ? '' : $body,
                            'response' => [
                                'code'    => (int) $curlInfo['http_code'],
                                'message' => self::statusMessage((int) $curlInfo['http_code']),
                            ],
                            'cookies'  => self::parseCookies($cookieStorage[$key] ?? []),
                            'error'    => '',
                        ];
                }
            } while (! empty($active) || ! empty($pending));

            curl_multi_close($mh);

            return $results;
        }

        /**
         * Map an HTTP status code to its standard reason phrase.
         *
         * @param  int  $code
         * @return string
         */
        private static function statusMessage(int $code): string
        {
            return match ($code) {
                100     => 'Continue',
                101     => 'Switching Protocols',
                200     => 'OK',
                201     => 'Created',
                202     => 'Accepted',
                204     => 'No Content',
                206     => 'Partial Content',
                301     => 'Moved Permanently',
                302     => 'Found',
                303     => 'See Other',
                304     => 'Not Modified',
                307     => 'Temporary Redirect',
                308     => 'Permanent Redirect',
                400     => 'Bad Request',
                401     => 'Unauthorized',
                403     => 'Forbidden',
                404     => 'Not Found',
                405     => 'Method Not Allowed',
                408     => 'Request Timeout',
                409     => 'Conflict',
                410     => 'Gone',
                415     => 'Unsupported Media Type',
                422     => 'Unprocessable Entity',
                429     => 'Too Many Requests',
                500     => 'Internal Server Error',
                501     => 'Not Implemented',
                502     => 'Bad Gateway',
                503     => 'Service Unavailable',
                504     => 'Gateway Timeout',
                default => 'Unknown',
            };
        }
    }
}
