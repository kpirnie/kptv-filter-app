<?php

/**
 * String Functions
 *
 * This is our primary string utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Str')) {

    /**
     * Str
     *
     * A modern PHP 8.2+ string utility class providing multi-needle search,
     * regex search, whole-word matching, and common string inspection helpers.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Str
    {
        // -------------------------------------------------------------------------
        // Search
        // -------------------------------------------------------------------------

        /**
         * Check whether a string contains any of the given substrings.
         *
         * Search is case-insensitive.  Provides an 8.2-compatible fallback
         * for the PHP 8.4 array_any() function.
         *
         * @param  string  $haystack  The string to search within.
         * @param  array   $needles   Substrings to search for.
         * @return bool
         */
        public static function strContainsAny(string $haystack, array $needles): bool
        {
            // PHP 8.4+: delegate to the native function
            if (function_exists('array_any')) {
                return array_any(
                    $needles,
                    fn(string $n): bool => str_contains(strtolower($haystack), strtolower($n))
                );
            }

            // PHP 8.2 / 8.3 fallback: early-return foreach
            foreach ($needles as $needle) {
                if (str_contains(strtolower($haystack), strtolower($needle))) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Check whether a string matches any of the given regex patterns.
         *
         * Patterns are matched case-insensitively.  Provides an 8.2-compatible
         * fallback for the PHP 8.4 array_any() function.
         *
         * @param  string  $haystack  The string to search within.
         * @param  array   $patterns  PCRE pattern bodies without delimiters.
         * @return bool
         */
        public static function strContainsAnyRegex(string $haystack, array $patterns): bool
        {
            // PHP 8.4+: delegate to the native function
            if (function_exists('array_any')) {
                return array_any(
                    $patterns,
                    fn(string $p): bool => (bool) preg_match('~' . $p . '~i', $haystack)
                );
            }

            // PHP 8.2 / 8.3 fallback: early-return foreach
            foreach ($patterns as $pattern) {
                if ((bool) preg_match('~' . $pattern . '~i', $haystack)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Check whether a string contains a given word.
         *
         * Uses lookahead and lookbehind assertions to match whole words only,
         * respecting common punctuation and non-ASCII characters.  A plain
         * substring check (str_contains) is intentionally not used here as
         * it would match partial words.
         *
         * @param  string  $string  The string to search within.
         * @param  string  $word    The whole word to search for.
         * @return bool
         */
        public static function containsWord(string $string, string $word): bool
        {
            // Lookahead/lookbehind boundaries cover whitespace and common punctuation
            return (bool) preg_match(
                '/(?<=[\s,.:;"\']|^)' . preg_quote($word, '/') . '(?=[\s,.:;"\']|$)/',
                $string
            );
        }

        // -------------------------------------------------------------------------
        // Inspection
        // -------------------------------------------------------------------------

        /**
         * Check whether a value is empty, null, or the literal string 'null'.
         *
         * Useful for sanitizing form input or API responses where downstream
         * services may return the string 'null' instead of a true null value.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function isEmpty(mixed $value): bool
        {
            // empty() already covers null, 0, '', [], false — the string 'null' needs explicit check
            return empty($value) || $value === 'null';
        }

        /**
         * Check whether a value is strictly blank.
         *
         * Unlike isEmpty(), this treats 0, '0', and false as non-blank.
         * Only null, the literal string 'null', and empty/whitespace-only
         * strings are considered blank.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function isBlank(mixed $value): bool
        {
            // Preserve 0, '0', and false as non-blank
            if ($value === 0 || $value === '0' || $value === false) {
                return false;
            }

            // Whitespace-only strings are blank
            if (is_string($value)) {
                return trim($value) === '' || $value === 'null';
            }

            return is_null($value);
        }

        // -------------------------------------------------------------------------
        // Transformation
        // -------------------------------------------------------------------------

        /**
         * Truncate a string to a maximum number of characters.
         *
         * @param  string  $value
         * @param  int     $length   Maximum character length.
         * @param  string  $suffix   Appended when truncation occurs (default '...').
         * @return string
         */
        public static function truncate(string $value, int $length, string $suffix = '...'): string
        {
            if (mb_strlen($value) <= $length) {
                return $value;
            }

            return mb_substr($value, 0, $length - mb_strlen($suffix)) . $suffix;
        }

        /**
         * Truncate a string to a maximum length, breaking at a word boundary.
         *
         * @param  string  $value
         * @param  int     $length   Maximum character length.
         * @param  string  $suffix   Appended when truncation occurs (default '...').
         * @return string
         */
        public static function excerpt(string $value, int $length, string $suffix = '...'): string
        {
            if (mb_strlen($value) <= $length) {
                return $value;
            }

            // Break at the last whitespace within the allowed length
            $truncated = mb_substr($value, 0, $length - mb_strlen($suffix));
            $lastSpace = mb_strrpos($truncated, ' ');

            return ($lastSpace !== false ? mb_substr($truncated, 0, $lastSpace) : $truncated) . $suffix;
        }

        /**
         * Convert a string to Title Case.
         *
         * @param  string  $value
         * @return string
         */
        public static function toTitleCase(string $value): string
        {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        /**
         * Convert a string to camelCase.
         *
         * @param  string  $value
         * @return string
         */
        public static function toCamelCase(string $value): string
        {
            $str = self::toStudlyCase($value);

            return lcfirst($str);
        }

        /**
         * Convert a string to StudlyCase (PascalCase).
         *
         * @param  string  $value
         * @return string
         */
        public static function toStudlyCase(string $value): string
        {
            return str_replace(' ', '', mb_convert_case(
                preg_replace('/[\-_]+/', ' ', $value),
                MB_CASE_TITLE,
                'UTF-8'
            ));
        }

        /**
         * Convert a string to snake_case.
         *
         * @param  string  $value
         * @param  string  $delimiter  Separator character (default '_').
         * @return string
         */
        public static function toSnakeCase(string $value, string $delimiter = '_'): string
        {
            // Insert delimiter before uppercase letters following lowercase letters or digits
            $value = preg_replace('/([a-z\d])([A-Z])/', '$1' . $delimiter . '$2', $value);

            // Replace spaces and existing delimiters with the chosen delimiter
            $value = preg_replace('/[\s\-_]+/', $delimiter, $value);

            return mb_strtolower($value);
        }

        /**
         * Convert a string to kebab-case.
         *
         * @param  string  $value
         * @return string
         */
        public static function toKebabCase(string $value): string
        {
            return self::toSnakeCase($value, '-');
        }

        /**
         * Mask part of a string for safe display.
         *
         * Replaces characters between $start and $end with $char.
         * Negative $end is counted from the right, mirroring substr() behaviour.
         *
         * Examples:
         *   mask('user@example.com', 2, -7)  → 'us***********om'
         *   mask('4111111111111111', 4, -4)  → '4111********1111'
         *   mask('+14085551234', 2, -2)      → '+1********34'
         *
         * @param  string  $value
         * @param  int     $start  Characters to leave visible at the start.
         * @param  int     $end    Characters to leave visible at the end (negative).
         * @param  string  $char   Masking character (default '*').
         * @return string
         */
        public static function mask(string $value, int $start = 0, int $end = 0, string $char = '*'): string
        {
            $length = mb_strlen($value);
            $endPos = $end < 0 ? $length + $end : $length - $end;

            if ($start >= $endPos) {
                return $value;
            }

            return mb_substr($value, 0, $start)
                . str_repeat(mb_substr($char, 0, 1), $endPos - $start)
                . mb_substr($value, $endPos);
        }

        /**
         * Generate a random string of a given length from a configurable alphabet.
         *
         * Not cryptographically secure — use Crypto::generateRandString() when
         * security is a concern.  Suitable for placeholders, test data, and tokens
         * where unpredictability is not required.
         *
         * @param  int     $length    Length of the generated string.
         * @param  string  $alphabet  Characters to draw from.
         * @return string
         */
        public static function random(
            int $length = 16,
            string $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
        ): string {
            $alphabetLength = mb_strlen($alphabet);
            $result         = '';

            for ($i = 0; $i < max(1, $length); $i++) {
                $result .= mb_substr($alphabet, rand(0, $alphabetLength - 1), 1);
            }

            return $result;
        }

        /**
         * Convert a string to a URL-friendly slug.
         *
         * Delegates to Sanitize::slug() — provided here for discoverability.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function slug(mixed $value): string
        {
            return \KPT\Sanitize::slug($value);
        }

        // -------------------------------------------------------------------------
        // Extraction
        // -------------------------------------------------------------------------

        /**
         * Extract the string between two substrings.
         *
         * Returns empty string when either delimiter is not found.
         *
         * @param  string  $value
         * @param  string  $start  Opening delimiter.
         * @param  string  $end    Closing delimiter.
         * @return string
         */
        public static function between(string $value, string $start, string $end): string
        {
            $startPos = mb_strpos($value, $start);

            if ($startPos === false) {
                return '';
            }

            $startPos += mb_strlen($start);
            $endPos    = mb_strpos($value, $end, $startPos);

            if ($endPos === false) {
                return '';
            }

            return mb_substr($value, $startPos, $endPos - $startPos);
        }

        /**
         * Wrap a string with a prefix and optional suffix.
         *
         * When $suffix is omitted the prefix is used on both sides.
         *
         * @param  string       $value
         * @param  string       $prefix
         * @param  string|null  $suffix  Defaults to $prefix when null.
         * @return string
         */
        public static function wrap(string $value, string $prefix, ?string $suffix = null): string
        {
            return $prefix . $value . ($suffix ?? $prefix);
        }

        // -------------------------------------------------------------------------
        // Measurement
        // -------------------------------------------------------------------------

        /**
         * Count the words in a string, respecting Unicode characters.
         *
         * @param  string  $value
         * @return int
         */
        public static function wordCount(string $value): int
        {
            // Match sequences of Unicode letters and combining marks
            return (int) preg_match_all('/\p{L}+/u', trim($value));
        }

        // -------------------------------------------------------------------------
        // Padding
        // -------------------------------------------------------------------------

        /**
         * Pad a string on the left to a given length.
         *
         * Multibyte-safe — uses mb_strlen for length calculation.
         *
         * @param  string  $value
         * @param  int     $length   Target total length.
         * @param  string  $pad      Padding character(s) (default ' ').
         * @return string
         */
        public static function padLeft(string $value, int $length, string $pad = ' '): string
        {
            $padNeeded = $length - mb_strlen($value);

            if ($padNeeded <= 0) {
                return $value;
            }

            return mb_substr(str_repeat($pad, (int) ceil($padNeeded / mb_strlen($pad))), 0, $padNeeded) . $value;
        }

        /**
         * Pad a string on the right to a given length.
         *
         * Multibyte-safe — uses mb_strlen for length calculation.
         *
         * @param  string  $value
         * @param  int     $length   Target total length.
         * @param  string  $pad      Padding character(s) (default ' ').
         * @return string
         */
        public static function padRight(string $value, int $length, string $pad = ' '): string
        {
            $padNeeded = $length - mb_strlen($value);

            if ($padNeeded <= 0) {
                return $value;
            }

            return $value . mb_substr(str_repeat($pad, (int) ceil($padNeeded / mb_strlen($pad))), 0, $padNeeded);
        }

        /**
         * Pad a string on both sides to a given length.
         *
         * When the padding cannot be distributed evenly the right side gets
         * the extra character.  Multibyte-safe.
         *
         * @param  string  $value
         * @param  int     $length   Target total length.
         * @param  string  $pad      Padding character(s) (default ' ').
         * @return string
         */
        public static function padBoth(string $value, int $length, string $pad = ' '): string
        {
            $padNeeded = $length - mb_strlen($value);

            if ($padNeeded <= 0) {
                return $value;
            }

            $leftPad  = (int) floor($padNeeded / 2);
            $rightPad = (int) ceil($padNeeded / 2);
            $padStr   = str_repeat($pad, (int) ceil($padNeeded / mb_strlen($pad)));

            return mb_substr($padStr, 0, $leftPad) . $value . mb_substr($padStr, 0, $rightPad);
        }
    }
}
