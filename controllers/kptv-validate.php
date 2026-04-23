<?php

/**
 * Validate Functions
 *
 * This is our primary validation class
 *
 * @since      8.4
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// make sure the class does not already exist
if (! class_exists('\KPT\Validate')) {

    /**
     * KPTV_Validate
     *
     * A modern PHP 8.5 validation utility.  All methods return bool unless
     * otherwise noted.  Where a value benefits from pre-cleaning before
     * validation, KPT\Validate is used internally.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Validate
    {

        // -------------------------------------------------------------------------
        // Scalars
        // -------------------------------------------------------------------------

        /**
         * Validate that a value is not empty.
         *
         * Considers 0 and '0' as non-empty.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function required(mixed $value): bool
        {
            if (is_string($value)) {
                return trim($value) !== '';
            }

            // 0 and '0' are legitimate non-empty values
            return ! empty($value) || $value === 0 || $value === '0';
        }

        /**
         * Validate that a value is a string.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_string(mixed $value): bool
        {
            return is_string($value);
        }

        /**
         * Validate that a value is an integer.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_int(mixed $value): bool
        {
            return filter_var($value, FILTER_VALIDATE_INT) !== false;
        }

        /**
         * Validate that a value is a float.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_float(mixed $value): bool
        {
            return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
        }

        /**
         * Validate that a value is numeric (int or float).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_numeric(mixed $value): bool
        {
            return is_numeric($value);
        }

        /**
         * Validate that a value is a boolean or boolean-like string.
         *
         * Accepts: true/false, 1/0, "yes"/"no", "on"/"off".
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_bool(mixed $value): bool
        {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
        }

        /**
         * Validate that a value is an array.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_array(mixed $value): bool
        {
            return is_array($value);
        }

        /**
         * Validate that a value is null.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_null(mixed $value): bool
        {
            return is_null($value);
        }

        /**
         * Validate that a value is a scalar (int, float, string, or bool).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function is_scalar(mixed $value): bool
        {
            return is_scalar($value);
        }

        // -------------------------------------------------------------------------
        // String
        // -------------------------------------------------------------------------

        /**
         * Validate that a string meets a minimum length.
         *
         * @param  mixed  $value
         * @param  int    $min
         * @return bool
         */
        public static function min_length(mixed $value, int $min): bool
        {
            return mb_strlen((string) $value) >= $min;
        }

        /**
         * Validate that a string does not exceed a maximum length.
         *
         * @param  mixed  $value
         * @param  int    $max
         * @return bool
         */
        public static function max_length(mixed $value, int $max): bool
        {
            return mb_strlen((string) $value) <= $max;
        }

        /**
         * Validate that a string length is between min and max (inclusive).
         *
         * @param  mixed  $value
         * @param  int    $min
         * @param  int    $max
         * @return bool
         */
        public static function length_between(mixed $value, int $min, int $max): bool
        {
            $len = mb_strlen((string) $value);

            return $len >= $min && $len <= $max;
        }

        /**
         * Validate that a string is exactly a given length.
         *
         * @param  mixed  $value
         * @param  int    $length
         * @return bool
         */
        public static function exact_length(mixed $value, int $length): bool
        {
            return mb_strlen((string) $value) === $length;
        }

        /**
         * Validate that a string contains a given substring.
         *
         * @param  mixed   $value
         * @param  string  $needle
         * @param  bool    $case_sensitive
         * @return bool
         */
        public static function contains(mixed $value, string $needle, bool $case_sensitive = true): bool
        {
            return $case_sensitive
                ? str_contains((string) $value, $needle)
                : str_contains(mb_strtolower((string) $value), mb_strtolower($needle));
        }

        /**
         * Validate that a string starts with a given substring.
         *
         * @param  mixed   $value
         * @param  string  $needle
         * @param  bool    $case_sensitive
         * @return bool
         */
        public static function starts_with(mixed $value, string $needle, bool $case_sensitive = true): bool
        {
            return $case_sensitive
                ? str_starts_with((string) $value, $needle)
                : str_starts_with(mb_strtolower((string) $value), mb_strtolower($needle));
        }

        /**
         * Validate that a string ends with a given substring.
         *
         * @param  mixed   $value
         * @param  string  $needle
         * @param  bool    $case_sensitive
         * @return bool
         */
        public static function ends_with(mixed $value, string $needle, bool $case_sensitive = true): bool
        {
            return $case_sensitive
                ? str_ends_with((string) $value, $needle)
                : str_ends_with(mb_strtolower((string) $value), mb_strtolower($needle));
        }

        /**
         * Validate that a string matches a regular expression.
         *
         * @param  mixed   $value
         * @param  string  $pattern  Full PCRE pattern including delimiters.
         * @return bool
         */
        public static function matches(mixed $value, string $pattern): bool
        {
            return (bool) preg_match($pattern, (string) $value);
        }

        /**
         * Validate that a string contains only alphabetic characters.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function alpha(mixed $value): bool
        {
            return (bool) preg_match('/^\p{L}+$/u', (string) $value);
        }

        /**
         * Validate that a string contains only alphanumeric characters.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function alphanumeric(mixed $value): bool
        {
            return (bool) preg_match('/^[\p{L}\p{N}]+$/u', (string) $value);
        }

        /**
         * Validate that a string contains no whitespace.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function no_whitespace(mixed $value): bool
        {
            return ! preg_match('/\s/', (string) $value);
        }

        /**
         * Validate a password meets minimum strength requirements.
         *
         * Requires at least one uppercase, one lowercase, one digit,
         * one special character, and a minimum length of 8 by default.
         *
         * @param  mixed  $value
         * @param  int    $min_length       Minimum character length.
         * @param  bool   $require_special  Require at least one special character.
         * @return bool
         */
        public static function password_strength(
            mixed $value,
            int $min_length      = 8,
            bool $require_special = true
        ): bool {
            $str = (string) $value;

            if (mb_strlen($str) < $min_length) {
                return false;
            }

            // Must contain at least one uppercase letter
            if (! preg_match('/[A-Z]/', $str)) {
                return false;
            }

            // Must contain at least one lowercase letter
            if (! preg_match('/[a-z]/', $str)) {
                return false;
            }

            // Must contain at least one digit
            if (! preg_match('/[0-9]/', $str)) {
                return false;
            }

            // Optionally require at least one special character
            if ($require_special && ! preg_match('/[\W_]/', $str)) {
                return false;
            }

            return true;
        }

        /**
         * Validate that a string is a valid slug.
         *
         * Allows lowercase letters, numbers, and hyphens only.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function slug(mixed $value): bool
        {
            return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value);
        }

        /**
         * Validate that a string is a valid username.
         *
         * Allows letters, numbers, underscores, hyphens, and dots.
         *
         * @param  mixed  $value
         * @param  int    $min_length
         * @param  int    $max_length
         * @return bool
         */
        public static function username(mixed $value, int $min_length = 3, int $max_length = 64): bool
        {
            $str = (string) $value;
            $len = mb_strlen($str);

            return $len >= $min_length
                && $len <= $max_length
                && (bool) preg_match('/^[a-zA-Z0-9_\-.]+$/', $str);
        }

        /**
         * Validate a name
         *
         * Allows Unicode letters, hyphens, apostrophes, and spaces.
         * Covers hyphenated names (Mary-Jane), apostrophe names (O'Brien),
         * and multi-word names (Mary Jane).
         *
         * @param  mixed  $value
         * @param  int    $min_length
         * @param  int    $max_length
         * @return bool
         */
        public static function name(mixed $value, int $min_length = 2, int $max_length = 64): bool
        {
            $str = trim((string) $value);
            $len = mb_strlen($str);

            return $len >= $min_length
                && $len <= $max_length
                && (bool) preg_match('/^[\p{L}\'\-\s]+$/u', $str);
        }

        /**
         * Validate that two values match (strict equality).
         *
         * Useful for password confirmation fields.
         *
         * @param  mixed  $value
         * @param  mixed  $confirmation
         * @return bool
         */
        public static function confirmed(mixed $value, mixed $confirmation): bool
        {
            return $value === $confirmation;
        }

        /**
         * Validate that a string is a valid semantic version (semver).
         *
         * Accepts optional pre-release and build metadata suffixes.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function semver(mixed $value): bool
        {
            return (bool) preg_match(
                '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z\-.]+)?(?:\+[0-9A-Za-z\-.]+)?$/',
                (string) $value
            );
        }

        // -------------------------------------------------------------------------
        // Network / identifiers
        // -------------------------------------------------------------------------

        /**
         * Validate an email address.
         *
         * Pre-cleans via \KPT\Sanitize::email() then checks the result is non-empty.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function email(mixed $value): bool
        {
            return \KPT\Sanitize::email($value) !== '';
        }

        /**
         * Validate a URL.
         *
         * Pre-cleans via \KPT\Sanitize::url() then checks the result is non-empty.
         *
         * @param  mixed  $value
         * @param  int    $flags  FILTER_FLAG_* constants.
         * @return bool
         */
        public static function url(mixed $value, int $flags = 0): bool
        {
            return \KPT\Sanitize::url($value, $flags) !== '';
        }

        /**
         * Validate that a URL is reachable.
         *
         * Performs a HEAD request; use sparingly as this is a network call.
         *
         * @param  mixed  $value
         * @param  int    $timeout  Seconds before giving up.
         * @return bool
         */
        public static function url_reachable(mixed $value, int $timeout = 5): bool
        {
            $url = \KPT\Sanitize::url($value);

            if ($url === '') {
                return false;
            }

            // Use stream context for a lightweight HEAD-style check
            $ctx = stream_context_create([
                'http' => [
                    'method'          => 'HEAD',
                    'timeout'         => $timeout,
                    'ignore_errors'   => true,
                    'follow_location' => true,
                ],
                'ssl'  => [
                    'verify_peer'       => true,
                    'verify_peer_name'  => true,
                ],
            ]);

            $headers = @get_headers($url, false, $ctx);

            return $headers !== false && str_contains($headers[0], '200');
        }

        /**
         * Validate an IP address.
         *
         * Pre-cleans via \KPT\Sanitize::ip() then checks the result is non-empty.
         *
         * @param  mixed  $value
         * @param  bool   $ipv6
         * @param  bool   $private
         * @param  bool   $reserved
         * @return bool
         */
        public static function ip(
            mixed $value,
            bool $ipv6     = true,
            bool $private  = true,
            bool $reserved = true
        ): bool {
            return \KPT\Sanitize::ip($value, $ipv6, $private, $reserved) !== '';
        }

        /**
         * Validate a domain name.
         *
         * Pre-cleans via \KPT\Sanitize::domain(), then confirms it contains
         * at least one dot and resolves via DNS.
         *
         * @param  mixed  $value
         * @param  bool   $check_dns  Perform a DNS lookup to confirm the domain exists.
         * @return bool
         */
        public static function domain(mixed $value, bool $check_dns = false): bool
        {
            $domain = \KPT\Sanitize::domain($value);

            if ($domain === '' || ! str_contains($domain, '.')) {
                return false;
            }

            // Optionally verify the domain resolves
            return $check_dns ? checkdnsrr($domain, 'ANY') : true;
        }

        /**
         * Validate a port number (0–65535).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function port(mixed $value): bool
        {
            return \KPT\Sanitize::port($value) !== null;
        }

        /**
         * Validate a MAC address.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function mac_address(mixed $value): bool
        {
            return \KPT\Sanitize::mac_address($value) !== '';
        }

        /**
         * Validate a UUID (v1–v5).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function uuid(mixed $value): bool
        {
            return \KPT\Sanitize::uuid($value) !== '';
        }

        /**
         * Validate a phone number.
         *
         * Checks that after stripping formatting characters at least 7
         * and no more than 15 digits remain (ITU-T E.164 bounds).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function phone(mixed $value): bool
        {
            $digits = \KPT\Sanitize::phone($value, true);
            $len    = strlen($digits);

            return $len >= 7 && $len <= 15;
        }

        /**
         * Validate a hex color code (3 or 6 characters, with or without #).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function hex_color(mixed $value): bool
        {
            return \KPT\Sanitize::hex_color($value) !== '';
        }

        /**
         * Validate an RGB or RGBA color string.
         *
         * Accepts: rgb(255, 255, 255) and rgba(255, 255, 255, 0.5)
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function rgb_color(mixed $value): bool
        {
            return (bool) preg_match(
                '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(\s*,\s*(0|0?\.\d+|1(\.0)?))?\s*\)$/',
                trim((string) $value)
            );
        }

        /**
         * Validate an HSL or HSLA color string.
         *
         * Accepts: hsl(360, 100%, 50%) and hsla(360, 100%, 50%, 0.5)
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function hsl_color(mixed $value): bool
        {
            return (bool) preg_match(
                '/^hsla?\(\s*(\d{1,3})\s*,\s*(\d{1,3})%\s*,\s*(\d{1,3})%(\s*,\s*(0|0?\.\d+|1(\.0)?))?\s*\)$/',
                trim((string) $value)
            );
        }

        /**
         * Validate any supported color format (hex, rgb, rgba, hsl, hsla).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function color(mixed $value): bool
        {
            return self::hex_color($value)
                || self::rgb_color($value)
                || self::hsl_color($value);
        }

        /**
         * Validate a base64-encoded string.
         *
         * @param  mixed  $value
         * @param  bool   $url_safe  Accept URL-safe base64 alphabet.
         * @return bool
         */
        public static function base64(mixed $value, bool $url_safe = false): bool
        {
            return \KPT\Sanitize::base64($value, $url_safe) !== '';
        }

        /**
         * Validate a GPS latitude value (-90 to 90).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function latitude(mixed $value): bool
        {
            $f = filter_var($value, FILTER_VALIDATE_FLOAT);

            return $f !== false && $f >= -90.0 && $f <= 90.0;
        }

        /**
         * Validate a GPS longitude value (-180 to 180).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function longitude(mixed $value): bool
        {
            $f = filter_var($value, FILTER_VALIDATE_FLOAT);

            return $f !== false && $f >= -180.0 && $f <= 180.0;
        }

        /**
         * Validate a latitude/longitude coordinate pair.
         *
         * @param  mixed  $lat
         * @param  mixed  $lng
         * @return bool
         */
        public static function coordinates(mixed $lat, mixed $lng): bool
        {
            return self::latitude($lat) && self::longitude($lng);
        }

        /**
         * Validate an ISO 3166-1 alpha-2 country code (e.g. 'US', 'GB').
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function country_code(mixed $value): bool
        {
            return (bool) preg_match('/^[A-Z]{2}$/', strtoupper(trim((string) $value)));
        }

        /**
         * Validate an IETF BCP 47 language code (e.g. 'en', 'en-US', 'zh-Hant').
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function language_code(mixed $value): bool
        {
            return (bool) preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*$/', trim((string) $value));
        }

        /**
         * Validate a timezone identifier against PHP's known list.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function timezone(mixed $value): bool
        {
            return in_array((string) $value, \DateTimeZone::listIdentifiers(), true);
        }

        /**
         * Validate a US ZIP code (5-digit or ZIP+4 format).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function zip_code(mixed $value): bool
        {
            return (bool) preg_match('/^\d{5}(?:-\d{4})?$/', trim((string) $value));
        }

        /**
         * Validate a postal code for a given country.
         *
         * Covers: US, CA, GB, AU, DE, FR, NL, and a generic numeric fallback.
         *
         * @param  mixed   $value
         * @param  string  $country  ISO 3166-1 alpha-2 country code.
         * @return bool
         */
        public static function postal_code(mixed $value, string $country = 'US'): bool
        {
            $str = trim((string) $value);

            // Per-country postal code patterns
            $patterns = [
                'US' => '/^\d{5}(?:-\d{4})?$/',
                'CA' => '/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/',
                'GB' => '/^[A-Za-z]{1,2}\d[A-Za-z\d]?\s*\d[A-Za-z]{2}$/',
                'AU' => '/^\d{4}$/',
                'DE' => '/^\d{5}$/',
                'FR' => '/^\d{5}$/',
                'NL' => '/^\d{4}\s?[A-Za-z]{2}$/',
            ];

            $pattern = $patterns[strtoupper($country)] ?? '/^\d{4,10}$/';

            return (bool) preg_match($pattern, $str);
        }

        /**
         * Validate an ISBN-10 or ISBN-13.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function isbn(mixed $value): bool
        {
            // Strip hyphens and spaces before validation
            $str = preg_replace('/[\s\-]/', '', (string) $value);

            if (strlen($str) === 10) {
                return self::_isbn10($str);
            }

            if (strlen($str) === 13) {
                return self::_isbn13($str);
            }

            return false;
        }

        /**
         * Validate a credit card number via the Luhn algorithm.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function credit_card(mixed $value): bool
        {
            // Strip spaces and hyphens before the Luhn check
            $str = preg_replace('/[\s\-]/', '', (string) $value);

            if (! ctype_digit($str)) {
                return false;
            }

            return self::_luhn($str);
        }

        // -------------------------------------------------------------------------
        // Date / Time
        // -------------------------------------------------------------------------

        /**
         * Validate a date string against a given format.
         *
         * @param  mixed   $value
         * @param  string  $format  Expected format (default Y-m-d).
         * @return bool
         */
        public static function date(mixed $value, string $format = 'Y-m-d'): bool
        {
            return \KPT\Sanitize::date($value, $format) !== '';
        }

        /**
         * Validate a time string against a given format.
         *
         * @param  mixed   $value
         * @param  string  $format  Expected format (default H:i:s).
         * @return bool
         */
        public static function time(mixed $value, string $format = 'H:i:s'): bool
        {
            $str = \KPT\Sanitize::string($value, false, false);
            $dt  = \DateTimeImmutable::createFromFormat($format, $str);

            return $dt !== false && ! array_filter(\DateTimeImmutable::getLastErrors() ?: []);
        }

        /**
         * Validate a datetime string against a given format.
         *
         * @param  mixed   $value
         * @param  string  $format  Expected format (default Y-m-d H:i:s).
         * @return bool
         */
        public static function datetime(mixed $value, string $format = 'Y-m-d H:i:s'): bool
        {
            return self::date($value, $format);
        }

        /**
         * Validate that a date falls before a given date.
         *
         * @param  mixed   $value
         * @param  string  $before  Date string to compare against.
         * @param  string  $format
         * @return bool
         */
        public static function date_before(mixed $value, string $before, string $format = 'Y-m-d'): bool
        {
            $dt    = \DateTimeImmutable::createFromFormat($format, (string) $value);
            $limit = \DateTimeImmutable::createFromFormat($format, $before);

            return $dt !== false && $limit !== false && $dt < $limit;
        }

        /**
         * Validate that a date falls after a given date.
         *
         * @param  mixed   $value
         * @param  string  $after   Date string to compare against.
         * @param  string  $format
         * @return bool
         */
        public static function date_after(mixed $value, string $after, string $format = 'Y-m-d'): bool
        {
            $dt    = \DateTimeImmutable::createFromFormat($format, (string) $value);
            $limit = \DateTimeImmutable::createFromFormat($format, $after);

            return $dt !== false && $limit !== false && $dt > $limit;
        }

        /**
         * Validate that a date falls within a given range (inclusive).
         *
         * @param  mixed   $value
         * @param  string  $start
         * @param  string  $end
         * @param  string  $format
         * @return bool
         */
        public static function date_between(mixed $value, string $start, string $end, string $format = 'Y-m-d'): bool
        {
            $dt    = \DateTimeImmutable::createFromFormat($format, (string) $value);
            $from  = \DateTimeImmutable::createFromFormat($format, $start);
            $to    = \DateTimeImmutable::createFromFormat($format, $end);

            return $dt !== false && $from !== false && $to !== false
                && $dt >= $from && $dt <= $to;
        }

        /**
         * Validate that a date represents an age of at least $min years.
         *
         * Useful for age-gate validation (e.g. must be 18+).
         *
         * @param  mixed   $value   Date of birth.
         * @param  int     $min     Minimum age in years.
         * @param  string  $format
         * @return bool
         */
        public static function min_age(mixed $value, int $min, string $format = 'Y-m-d'): bool
        {
            $dob = \DateTimeImmutable::createFromFormat($format, (string) $value);

            if ($dob === false) {
                return false;
            }

            // Calculate age by comparing to today
            return (int) $dob->diff(new \DateTimeImmutable())->y >= $min;
        }

        /**
         * Validate that a date represents an age no greater than $max years.
         *
         * @param  mixed   $value
         * @param  int     $max
         * @param  string  $format
         * @return bool
         */
        public static function max_age(mixed $value, int $max, string $format = 'Y-m-d'): bool
        {
            $dob = \DateTimeImmutable::createFromFormat($format, (string) $value);

            if ($dob === false) {
                return false;
            }

            return (int) $dob->diff(new \DateTimeImmutable())->y <= $max;
        }

        // -------------------------------------------------------------------------
        // Numeric
        // -------------------------------------------------------------------------

        /**
         * Validate that a number is greater than or equal to a minimum.
         *
         * @param  mixed      $value
         * @param  int|float  $min
         * @return bool
         */
        public static function min(mixed $value, int|float $min): bool
        {
            return is_numeric($value) && $value >= $min;
        }

        /**
         * Validate that a number is less than or equal to a maximum.
         *
         * @param  mixed      $value
         * @param  int|float  $max
         * @return bool
         */
        public static function max(mixed $value, int|float $max): bool
        {
            return is_numeric($value) && $value <= $max;
        }

        /**
         * Validate that a number falls within a range (inclusive).
         *
         * @param  mixed      $value
         * @param  int|float  $min
         * @param  int|float  $max
         * @return bool
         */
        public static function between(mixed $value, int|float $min, int|float $max): bool
        {
            return is_numeric($value) && $value >= $min && $value <= $max;
        }

        /**
         * Validate that a number is positive (> 0).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function positive(mixed $value): bool
        {
            return is_numeric($value) && $value > 0;
        }

        /**
         * Validate that a number is negative (< 0).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function negative(mixed $value): bool
        {
            return is_numeric($value) && $value < 0;
        }

        /**
         * Validate that a number is zero or positive (>= 0).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function non_negative(mixed $value): bool
        {
            return is_numeric($value) && $value >= 0;
        }

        /**
         * Validate that a float has no more than a given number of decimal places.
         *
         * @param  mixed  $value
         * @param  int    $places
         * @return bool
         */
        public static function decimal_places(mixed $value, int $places): bool
        {
            if (! is_numeric($value)) {
                return false;
            }

            // Extract the decimal portion and count its length
            $parts = explode('.', (string) $value);

            return strlen($parts[1] ?? '') <= $places;
        }

        /**
         * Validate that a number is divisible by a given divisor.
         *
         * @param  mixed      $value
         * @param  int|float  $divisor
         * @return bool
         */
        public static function divisible_by(mixed $value, int|float $divisor): bool
        {
            if (! is_numeric($value) || $divisor == 0) {
                return false;
            }

            return fmod((float) $value, (float) $divisor) === 0.0;
        }

        // -------------------------------------------------------------------------
        // File system
        // -------------------------------------------------------------------------

        /**
         * Validate that a file exists and is readable.
         *
         * @param  mixed  $value  Absolute file path.
         * @return bool
         */
        public static function file_exists(mixed $value): bool
        {
            $path = \KPT\Sanitize::path((string) $value);

            return $path !== '' && is_file($path) && is_readable($path);
        }

        /**
         * Validate that a directory exists and is readable.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function dir_exists(mixed $value): bool
        {
            $path = \KPT\Sanitize::path((string) $value);

            return $path !== '' && is_dir($path) && is_readable($path);
        }

        /**
         * Validate that a file has an allowed extension.
         *
         * Comparison is case-insensitive.
         *
         * @param  mixed    $value       Filename or full path.
         * @param  array    $extensions  Allowed extensions without leading dot.
         * @return bool
         */
        public static function file_extension(mixed $value, array $extensions): bool
        {
            $ext = strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));

            return in_array($ext, array_map('strtolower', $extensions), true);
        }

        /**
         * Validate that a file does not exceed a maximum size.
         *
         * @param  mixed  $value    Absolute file path.
         * @param  int    $max_bytes
         * @return bool
         */
        public static function file_size(mixed $value, int $max_bytes): bool
        {
            $path = \KPT\Sanitize::path((string) $value);

            return $path !== '' && is_file($path) && filesize($path) <= $max_bytes;
        }

        /**
         * Validate that a file's MIME type is in an allowed list.
         *
         * Requires the fileinfo extension.
         *
         * @param  mixed   $value         Absolute file path.
         * @param  array   $allowed_mime  e.g. ['image/jpeg', 'image/png']
         * @return bool
         */
        public static function file_mime(mixed $value, array $allowed_mime): bool
        {
            $path = \KPT\Sanitize::path((string) $value);

            if ($path === '' || ! is_file($path)) {
                return false;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($path);

            return in_array($mime, $allowed_mime, true);
        }

        // -------------------------------------------------------------------------
        // Arrays
        // -------------------------------------------------------------------------

        /**
         * Validate that an array is not empty.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function array_not_empty(mixed $value): bool
        {
            return is_array($value) && count($value) > 0;
        }

        /**
         * Validate that an array contains at least $min elements.
         *
         * @param  mixed  $value
         * @param  int    $min
         * @return bool
         */
        public static function array_min_count(mixed $value, int $min): bool
        {
            return is_array($value) && count($value) >= $min;
        }

        /**
         * Validate that an array contains no more than $max elements.
         *
         * @param  mixed  $value
         * @param  int    $max
         * @return bool
         */
        public static function array_max_count(mixed $value, int $max): bool
        {
            return is_array($value) && count($value) <= $max;
        }

        /**
         * Validate that an array contains a specific key.
         *
         * @param  mixed      $value
         * @param  string|int $key
         * @return bool
         */
        public static function array_has_key(mixed $value, string|int $key): bool
        {
            return is_array($value) && array_key_exists($key, $value);
        }

        /**
         * Validate that an array contains all specified keys.
         *
         * @param  mixed  $value
         * @param  array  $keys
         * @return bool
         */
        public static function array_has_keys(mixed $value, array $keys): bool
        {
            if (! is_array($value)) {
                return false;
            }

            // Ensure every required key is present
            foreach ($keys as $key) {
                if (! array_key_exists($key, $value)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Validate that every element in an array passes a callable.
         *
         * @param  mixed     $value
         * @param  callable  $callback  Must return bool.
         * @return bool
         */
        public static function array_all(mixed $value, callable $callback): bool
        {
            if (! is_array($value)) {
                return false;
            }

            foreach ($value as $item) {
                if (! $callback($item)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Validate that at least one element in an array passes a callable.
         *
         * @param  mixed     $value
         * @param  callable  $callback  Must return bool.
         * @return bool
         */
        public static function array_any(mixed $value, callable $callback): bool
        {
            if (! is_array($value)) {
                return false;
            }

            foreach ($value as $item) {
                if ($callback($item)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Validate that a value exists within an array.
         *
         * @param  mixed  $value
         * @param  array  $haystack
         * @param  bool   $strict
         * @return bool
         */
        public static function in_array(mixed $value, array $haystack, bool $strict = false): bool
        {
            return in_array($value, $haystack, $strict);
        }

        /**
         * Validate that a value does not exist within an array.
         *
         * @param  mixed  $value
         * @param  array  $haystack
         * @param  bool   $strict
         * @return bool
         */
        public static function not_in_array(mixed $value, array $haystack, bool $strict = false): bool
        {
            return ! in_array($value, $haystack, $strict);
        }

        // -------------------------------------------------------------------------
        // Comparison
        // -------------------------------------------------------------------------

        /**
         * Validate strict equality.
         *
         * @param  mixed  $value
         * @param  mixed  $expected
         * @return bool
         */
        public static function equals(mixed $value, mixed $expected): bool
        {
            return $value === $expected;
        }

        /**
         * Validate strict inequality.
         *
         * @param  mixed  $value
         * @param  mixed  $unexpected
         * @return bool
         */
        public static function not_equals(mixed $value, mixed $unexpected): bool
        {
            return $value !== $unexpected;
        }

        /**
         * Validate that a value is an instance of a given class.
         *
         * @param  mixed   $value
         * @param  string  $class
         * @return bool
         */
        public static function instance_of(mixed $value, string $class): bool
        {
            return $value instanceof $class;
        }

        // -------------------------------------------------------------------------
        // Conditional
        // -------------------------------------------------------------------------

        /**
         * Validate that $value is non-empty when $condition is true.
         *
         * @param  mixed  $value
         * @param  bool   $condition
         * @return bool
         */
        public static function required_if(mixed $value, bool $condition): bool
        {
            return ! $condition || self::required($value);
        }

        /**
         * Validate that $value is non-empty when $condition is false.
         *
         * @param  mixed  $value
         * @param  bool   $condition
         * @return bool
         */
        public static function required_unless(mixed $value, bool $condition): bool
        {
            return $condition || self::required($value);
        }

        // -------------------------------------------------------------------------
        // Structured / rich input
        // -------------------------------------------------------------------------

        /**
         * Validate a JSON string.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function json(mixed $value): bool
        {
            return json_validate(trim((string) $value));
        }

        /**
         * Validate an XML string.
         *
         * Pre-cleans via \KPT\Sanitize::xml() then checks the result is non-empty.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function xml(mixed $value): bool
        {
            return \KPT\Sanitize::xml($value) !== '';
        }

        /**
         * Validate an SVG string.
         *
         * Pre-cleans via \KPT\Sanitize::svg() then checks the result is non-empty.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function svg(mixed $value): bool
        {
            return \KPT\Sanitize::svg($value) !== '';
        }

        // -------------------------------------------------------------------------
        // Aggregate / map
        // -------------------------------------------------------------------------

        /**
         * Validate a key=value map against a set of rules.
         *
         * Each rule is a callable that receives the field value and returns bool.
         * Returns an array of field names that failed; empty array means all passed.
         *
         * Example:
         * <code>
         * $errors = KPTV_Validate::map( $_POST, [
         *     'name'     => fn( $v ) => KPTV_Validate::min_length( $v, 2 ),
         *     'email'    => fn( $v ) => KPTV_Validate::email( $v ),
         *     'age'      => fn( $v ) => KPTV_Validate::between( $v, 18, 120 ),
         * ] );
         *
         * if ( ! empty( $errors ) ) { // handle failures }
         * </code>
         *
         * @param  array<string,mixed>     $data
         * @param  array<string,callable>  $rules
         * @param  bool                   $bail   Stop on first failure when true.
         * @return array<string>  Field names that failed validation.
         */
        public static function map(array $data, array $rules, bool $bail = false): array
        {
            $errors = [];

            foreach ($rules as $field => $rule) {
                $value = $data[$field] ?? null;

                if (! $rule($value)) {
                    $errors[] = $field;

                    // Stop immediately on first failure when bailing
                    if ($bail) {
                        break;
                    }
                }
            }

            return $errors;
        }

        /**
         * Validate a map and return true/false rather than an error list.
         *
         * @param  array<string,mixed>     $data
         * @param  array<string,callable>  $rules
         * @return bool
         */
        public static function passes(array $data, array $rules): bool
        {
            return empty(self::map($data, $rules));
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Validate an ISBN-10 check digit.
         *
         * @param  string  $isbn  10-character string, digits only (X allowed as last char).
         * @return bool
         */
        private static function _isbn10(string $isbn): bool
        {
            if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
                return false;
            }

            $sum = 0;

            for ($i = 0; $i < 9; $i++) {
                $sum += (int) $isbn[$i] * (10 - $i);
            }

            // Last character can be X (= 10)
            $last = $isbn[9] === 'X' ? 10 : (int) $isbn[9];
            $sum += $last;

            return $sum % 11 === 0;
        }

        /**
         * Validate an ISBN-13 check digit.
         *
         * @param  string  $isbn  13-character digit string.
         * @return bool
         */
        private static function _isbn13(string $isbn): bool
        {
            if (! ctype_digit($isbn)) {
                return false;
            }

            $sum = 0;

            for ($i = 0; $i < 12; $i++) {
                // Alternating weight of 1 and 3
                $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
            }

            $check = (10 - ($sum % 10)) % 10;

            return $check === (int) $isbn[12];
        }

        /**
         * Run the Luhn algorithm on a digit string.
         *
         * @param  string  $number  Digits only.
         * @return bool
         */
        private static function _luhn(string $number): bool
        {
            $sum    = 0;
            $alt    = false;
            $length = strlen($number);

            // Traverse digits from right to left
            for ($i = $length - 1; $i >= 0; $i--) {
                $n = (int) $number[$i];

                if ($alt) {
                    $n *= 2;

                    // Subtract 9 when the doubled value exceeds a single digit
                    if ($n > 9) {
                        $n -= 9;
                    }
                }

                $sum += $n;
                $alt  = ! $alt;
            }

            return $sum % 10 === 0;
        }
    }
}
