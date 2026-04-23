<?php

/**
 * HTTP Functions
 *
 * This is our primary HTTP utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Http')) {

    /**
     * Http
     *
     * A modern PHP 8.2+ HTTP utility class providing
     * safe redirects, request inspection, and IP/CIDR utilities.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Http
    {
        // -------------------------------------------------------------------------
        // Redirects
        // -------------------------------------------------------------------------

        public static function tryRedirect(string $location, int $status = 301): void
        {
            // sanitize the location
            $location = \KPT\Sanitize::url($location);

            // if we dont have any headers, or before they are sent...
            if (! headers_sent()) {
                // Trap any PHP-level warning the header() call might still throw
                set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
                    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                }, E_WARNING);

                try {
                    header('Location: ' . $location, true, $status);
                } catch (\ErrorException) {
                    // Header failed despite headers_sent() returning false — fall through to JS
                    restore_error_handler();
                    self::jsRedirect($location);
                }

                restore_error_handler();
            } else {
                self::jsRedirect($location);
            }

            exit;
        }

        /**
         * Emit a JavaScript redirect as a fallback when headers cannot be sent.
         *
         * @param  string  $location
         * @return void
         */
        private static function jsRedirect(string $location): void
        {
            // just in case you make it here...
            $escaped = json_encode(
                $location,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
            );

            echo '<script>setTimeout(function(){window.location.href=' . $escaped . ';},100);</script>';
        }

        // -------------------------------------------------------------------------
        // Request inspection
        // -------------------------------------------------------------------------

        /**
         * Get the current request URI.
         *
         * Reconstructs the full URL from server variables and validates it
         * via \KPT\Sanitize::url().
         *
         * @return string  The sanitized request URI, or empty string on failure.
         */
        public static function getUserUri(): string
        {
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? '';
            $uri    = $_SERVER['REQUEST_URI'] ?? '';

            return \KPT\Sanitize::url($scheme . '://' . $host . $uri);
        }

        /**
         * Get the client's public IP address.
         *
         * Checks standard proxy headers in priority order, extracts the first
         * IP from comma-separated X-Forwarded-For lists, and validates each
         * candidate as a public (non-private, non-reserved) IP address.
         *
         * Note: HTTP headers can be spoofed.  Do not use this value for
         * security-critical decisions without additional verification.
         *
         * @return string  The validated public IP address, or empty string on failure.
         */
        public static function getUserIp(): string
        {
            $candidates = [];

            // HTTP_CLIENT_IP — set by some proxy configurations
            if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
                $candidates[] = $_SERVER['HTTP_CLIENT_IP'];
            }

            // HTTP_X_FORWARDED_FOR — may contain a comma-separated chain; take the first
            if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $candidates[] = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            }

            // REMOTE_ADDR — the direct connection, most trustworthy
            if (! empty($_SERVER['REMOTE_ADDR'])) {
                $candidates[] = $_SERVER['REMOTE_ADDR'];
            }

            // Return the first candidate that validates as a public IP
            foreach ($candidates as $candidate) {
                $ip = \KPT\Sanitize::ip($candidate, true, false, false);

                if ($ip !== '') {
                    return $ip;
                }
            }

            return '';
        }

        /**
         * Get the client's User-Agent string.
         *
         * Returns the sanitized HTTP_USER_AGENT server variable, or an
         * empty string when it is absent.
         *
         * @return string
         */
        public static function getUserAgent(): string
        {
            if (empty($_SERVER['HTTP_USER_AGENT'])) {
                return '';
            }

            // sanitize — the UA string must not be trusted as safe
            return \KPT\Sanitize::string((string) $_SERVER['HTTP_USER_AGENT']);
        }

        /**
         * Get the HTTP referer for the current request.
         *
         * @return string  The sanitized referer URL, or empty string when absent.
         */
        public static function getUserReferer(): string
        {
            if (empty($_SERVER['HTTP_REFERER'])) {
                return '';
            }

            return \KPT\Sanitize::url((string) $_SERVER['HTTP_REFERER']);
        }

        // -------------------------------------------------------------------------
        // Network utilities
        // -------------------------------------------------------------------------

        /**
         * Check whether an IP address falls within a CIDR range.
         *
         * Supports both IPv4 and IPv6.  When no mask is present the comparison
         * is a simple equality check.
         *
         * @param  string  $ip    The IP address to test.
         * @param  string  $cidr  A CIDR range (e.g. '192.168.1.0/24') or bare IP.
         * @return bool
         */
        public static function cidrMatch(string $ip, string $cidr): bool
        {
            // Bare IP — simple equality check
            if (! str_contains($cidr, '/')) {
                return $ip === $cidr;
            }

            [$subnet, $mask] = explode('/', $cidr, 2);
            $maskBits = (int) $mask;

            // IPv6
            if (str_contains($ip, ':')) {
                return self::cidrMatchV6($ip, $subnet, $maskBits);
            }

            // IPv4
            $ipLong     = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false || $maskBits < 0 || $maskBits > 32) {
                return false;
            }

            // Avoid shifting by 32 on 32-bit systems
            $maskLong = $maskBits === 0 ? 0 : (~0 << (32 - $maskBits));

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Check whether an IPv6 address falls within a given subnet and prefix length.
         *
         * @param  string  $ip       The IPv6 address to test.
         * @param  string  $subnet   The network address portion of the CIDR.
         * @param  int     $prefix   The prefix length (0–128).
         * @return bool
         */
        private static function cidrMatchV6(string $ip, string $subnet, int $prefix): bool
        {
            if ($prefix < 0 || $prefix > 128) {
                return false;
            }

            $ipBin     = inet_pton($ip);
            $subnetBin = inet_pton($subnet);

            // inet_pton returns false for invalid addresses
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }

            // Compare only the bits covered by the prefix length
            $fullBytes    = intdiv($prefix, 8);
            $remainingBits = $prefix % 8;

            // Full bytes must match exactly
            if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
                return false;
            }

            // Compare the partial byte when the prefix does not land on a byte boundary
            if ($remainingBits > 0) {
                $bitMask = 0xFF & (0xFF << (8 - $remainingBits));

                return (ord($ipBin[$fullBytes]) & $bitMask) === (ord($subnetBin[$fullBytes]) & $bitMask);
            }

            return true;
        }

        // -------------------------------------------------------------------------
        // Request inspection
        // -------------------------------------------------------------------------

        /**
         * Check whether the current request was made via XMLHttpRequest (AJAX).
         *
         * @return bool
         */
        public static function isAjax(): bool
        {
            return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        }

        /**
         * Check whether the current request is served over HTTPS.
         *
         * @return bool
         */
        public static function isHttps(): bool
        {
            return (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (! empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        }

        /**
         * Get the current HTTP request method.
         *
         * @return string  Uppercase method string (e.g. 'GET', 'POST'), or empty string when unavailable.
         */
        public static function method(): string
        {
            return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
        }

        /**
         * Check whether the current request uses a given HTTP method.
         *
         * @param  string  $method  Method name — case-insensitive.
         * @return bool
         */
        public static function isMethod(string $method): bool
        {
            return self::method() === strtoupper($method);
        }

        /**
         * Check whether the current request appears to be from a bot or crawler.
         *
         * Checks the User-Agent string against a list of known bot signatures.
         * Not exhaustive — sophisticated bots that spoof UA strings will not be caught.
         *
         * @return bool
         */
        public static function isBot(): bool
        {
            $ua = strtolower(self::getUserAgent());

            if ($ua === '') {
                return false;
            }

            $signatures = [
                'bot',
                'crawler',
                'spider',
                'slurp',
                'search',
                'fetch',
                'curl',
                'wget',
                'python',
                'ruby',
                'java',
                'perl',
                'libwww',
                'httpclient',
                'axios',
                'go-http',
                'okhttp',
                'scrapy',
                'googlebot',
                'bingbot',
                'yandexbot',
                'duckduckbot',
                'baiduspider',
                'facebookexternalhit',
                'twitterbot',
                'rogerbot',
                'linkedinbot',
                'embedly',
                'quora',
                'showyoubot',
                'outbrain',
                'pinterest',
                'slackbot',
                'vkshare',
                'w3c_validator',
                'whatsapp',
                'telegrambot',
                'applebot',
                'semrushbot',
                'ahrefsbot',
                'mj12bot',
                'dotbot',
            ];

            foreach ($signatures as $signature) {
                if (str_contains($ua, $signature)) {
                    return true;
                }
            }

            return false;
        }
    }
}
