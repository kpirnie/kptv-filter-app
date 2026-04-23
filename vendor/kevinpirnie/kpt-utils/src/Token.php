<?php

/**
 * Token Functions
 *
 * This is our primary token management class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Token')) {

    /**
     * Token
     *
     * A modern PHP 8.2+ CSRF token and signed URL utility backed by
     * KPT\Session for storage and KPT\Crypto for token generation and signing.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Token
    {
        // -------------------------------------------------------------------------
        // Internal constants
        // -------------------------------------------------------------------------

        /** Session namespace for all token storage */
        private const SESSION_KEY = '__kpt_tokens__';

        /** Query parameter name used for the signature in signed URLs */
        private const SIG_PARAM = '_sig';

        /** Query parameter name used for the expiry timestamp in signed URLs */
        private const EXP_PARAM = '_expires';

        // -------------------------------------------------------------------------
        // CSRF
        // -------------------------------------------------------------------------

        /**
         * Generate and store a CSRF token.
         *
         * Tokens are stored in the session under an isolated namespace keyed
         * by $name, alongside an expiry timestamp.  Calling generate() again
         * for the same $name replaces the previous token.
         *
         * @param  string  $name    Identifier for this token (default 'csrf').
         * @param  int     $expiry  Seconds until the token expires (default 3600).
         * @return string           The generated token string.
         */
        public static function generate(string $name = 'csrf', int $expiry = 3600): string
        {
            $token = \KPT\Crypto::generateToken(32);

            \KPT\Session::set(self::SESSION_KEY . '.' . $name, [
                'token'   => $token,
                'expires' => time() + max(1, $expiry),
            ]);

            return $token;
        }

        /**
         * Verify a CSRF token against the stored value.
         *
         * Uses timing-safe comparison to prevent timing attacks.
         * When $consume is true the token is removed after a successful
         * verification, making it single-use.
         *
         * @param  string  $token    The token to verify.
         * @param  string  $name     Identifier used when the token was generated.
         * @param  bool    $consume  Invalidate the token after successful verification.
         * @return bool
         */
        public static function verify(string $token, string $name = 'csrf', bool $consume = true): bool
        {
            $stored = \KPT\Session::get(self::SESSION_KEY . '.' . $name);

            if (! is_array($stored) || empty($stored['token']) || empty($stored['expires'])) {
                return false;
            }

            // Reject expired tokens before comparing
            if (time() > (int) $stored['expires']) {
                \KPT\Session::remove(self::SESSION_KEY . '.' . $name);
                return false;
            }

            $valid = \KPT\Crypto::timingSafeEquals($token, (string) $stored['token']);

            // Remove the token after successful single-use verification
            if ($valid && $consume) {
                \KPT\Session::remove(self::SESSION_KEY . '.' . $name);
            }

            return $valid;
        }

        /**
         * Check whether a stored token exists and has not expired.
         *
         * Does not consume the token.
         *
         * @param  string  $name
         * @return bool
         */
        public static function has(string $name = 'csrf'): bool
        {
            $stored = \KPT\Session::get(self::SESSION_KEY . '.' . $name);

            return is_array($stored)
                && ! empty($stored['token'])
                && ! empty($stored['expires'])
                && time() <= (int) $stored['expires'];
        }

        /**
         * Invalidate a stored token without verifying it.
         *
         * @param  string  $name
         * @return void
         */
        public static function invalidate(string $name = 'csrf'): void
        {
            \KPT\Session::remove(self::SESSION_KEY . '.' . $name);
        }

        /**
         * Generate an HTML hidden input field containing a fresh CSRF token.
         *
         * @param  string  $name     Token identifier and input field name.
         * @param  int     $expiry   Seconds until the token expires.
         * @return string            HTML <input> element.
         */
        public static function field(string $name = 'csrf', int $expiry = 3600): string
        {
            $token = self::generate($name, $expiry);

            return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"'
                . ' value="' . htmlspecialchars($token, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
        }

        // -------------------------------------------------------------------------
        // Signed URLs
        // -------------------------------------------------------------------------

        /**
         * Generate a signed URL with an embedded expiry timestamp.
         *
         * The signature is an HMAC-SHA256 of the URL (including the expiry
         * parameter but excluding the signature itself), keyed by $secret.
         *
         * @param  string  $url     The URL to sign.
         * @param  string  $secret  Secret key for HMAC signing.
         * @param  int     $expiry  Seconds until the signed URL expires (default 3600).
         * @return string           The signed URL.
         */
        public static function signUrl(string $url, string $secret, int $expiry = 3600): string
        {
            // Strip any existing signature/expiry params before re-signing
            $url     = self::stripSignatureParams($url);
            $expires = time() + max(1, $expiry);

            // Append the expiry timestamp before signing so it is covered by the HMAC
            $unsigned = self::appendQueryParam($url, self::EXP_PARAM, (string) $expires);
            $sig      = \KPT\Crypto::hmac($unsigned, $secret);

            return self::appendQueryParam($unsigned, self::SIG_PARAM, $sig);
        }

        /**
         * Verify a signed URL.
         *
         * Checks expiry first, then re-derives the HMAC and compares using
         * a timing-safe comparison.
         *
         * @param  string  $url     The signed URL to verify.
         * @param  string  $secret  Secret key used when the URL was signed.
         * @return bool
         */
        public static function verifySignedUrl(string $url, string $secret): bool
        {
            $params = [];
            $query  = parse_url($url, PHP_URL_QUERY);

            if ($query) {
                parse_str($query, $params);
            }

            // Both parameters must be present
            if (empty($params[self::SIG_PARAM]) || empty($params[self::EXP_PARAM])) {
                return false;
            }

            // Reject expired URLs before computing the HMAC
            if (time() > (int) $params[self::EXP_PARAM]) {
                return false;
            }

            $receivedSig = (string) $params[self::SIG_PARAM];

            // Reconstruct the unsigned URL — remove only the signature param
            $unsigned    = self::stripQueryParam($url, self::SIG_PARAM);
            $expectedSig = \KPT\Crypto::hmac($unsigned, $secret);

            return \KPT\Crypto::timingSafeEquals($receivedSig, $expectedSig);
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Append a query parameter to a URL.
         *
         * @param  string  $url
         * @param  string  $key
         * @param  string  $value
         * @return string
         */
        private static function appendQueryParam(string $url, string $key, string $value): string
        {
            $separator = str_contains($url, '?') ? '&' : '?';

            return $url . $separator . urlencode($key) . '=' . urlencode($value);
        }

        /**
         * Remove a specific query parameter from a URL.
         *
         * @param  string  $url
         * @param  string  $key
         * @return string
         */
        private static function stripQueryParam(string $url, string $key): string
        {
            $parts = parse_url($url);
            $query = [];

            if (! empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }

            unset($query[$key]);

            // Rebuild the URL without the removed parameter
            $base = ($parts['scheme'] ?? '') . '://'
                . ($parts['host'] ?? '')
                . ($parts['path'] ?? '');

            return ! empty($query) ? $base . '?' . http_build_query($query) : $base;
        }

        /**
         * Remove both signature parameters from a URL before re-signing.
         *
         * @param  string  $url
         * @return string
         */
        private static function stripSignatureParams(string $url): string
        {
            return self::stripQueryParam(
                self::stripQueryParam($url, self::SIG_PARAM),
                self::EXP_PARAM
            );
        }
    }
}
