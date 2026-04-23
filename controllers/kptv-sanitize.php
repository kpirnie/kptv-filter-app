<?php

/**
 * Sanitize Functions
 *
 * This is our primary sanitize class
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
if (! class_exists('\KPT\Sanitize')) {

    /**
     * KPTV_Sanitize
     *
     * A modern PHP 8.5 sanitization utility leveraging filter_var, filter_input,
     * and native string/type functions throughout.  Internal methods are reused
     * wherever a pre-clean pass is appropriate so logic is never duplicated.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Sanitize
    {

        // -------------------------------------------------------------------------
        // Scalars
        // -------------------------------------------------------------------------

        /**
         * Sanitize a plain-text string.
         *
         * Strips tags, encodes special characters, and trims whitespace.
         * This is the base sanitizer used internally by most other methods.
         *
         * @param  mixed  $value
         * @param  bool   $allow_newlines    Preserve \n / \r after tag-strip.
         * @param  bool   $allow_whitespace  Preserve internal whitespace characters.
         * @return string
         */
        public static function string(
            mixed $value,
            bool $allow_newlines   = false,
            bool $allow_whitespace = true
        ): string {
            // Encode special HTML characters first, then strip any remaining tags
            $str = filter_var((string) $value, FILTER_SANITIZE_SPECIAL_CHARS);
            $str = strip_tags($str);

            // Collapse or remove newlines based on flag
            if (! $allow_newlines) {
                $str = preg_replace('/[\r\n]+/', ' ', $str);
            }

            // Strip all whitespace characters when not permitted
            if (! $allow_whitespace) {
                $str = preg_replace('/\s+/', '', $str);
            }

            return trim($str);
        }

        /**
         * Sanitize a textarea value.
         *
         * Convenience wrapper around self::string() with newlines preserved
         * by default, suitable for multi-line user input.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function textarea(mixed $value): string
        {
            return self::string($value, true, true);
        }

        /**
         * Sanitize a string intended for HTML output.
         *
         * Strips disallowed tags then HTML-encodes the result.
         *
         * @param  mixed   $value
         * @param  string  $allowed_tags  Tag allowlist e.g. '<p><a><strong>'.
         * @return string
         */
        public static function html(mixed $value, string $allowed_tags = ''): string
        {
            // Strip tags before encoding so the allowlist is respected first
            $str = strip_tags((string) $value, $allowed_tags);

            return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        /**
         * Sanitize an integer.
         *
         * Returns 0 when the value falls outside the optional min/max bounds
         * or cannot be interpreted as an integer.
         *
         * @param  mixed     $value
         * @param  int|null  $min  Inclusive lower bound.
         * @param  int|null  $max  Inclusive upper bound.
         * @return int
         */
        public static function int(mixed $value, ?int $min = null, ?int $max = null): int
        {
            $options = [];

            if ($min !== null) {
                $options['min_range'] = $min;
            }

            if ($max !== null) {
                $options['max_range'] = $max;
            }

            $filtered = filter_var(
                $value,
                FILTER_VALIDATE_INT,
                $options ? ['options' => $options] : []
            );

            // filter_var returns false on failure; fall back to 0
            return ($filtered !== false) ? (int) $filtered : 0;
        }

        /**
         * Sanitize a float.
         *
         * Returns 0.0 when the value cannot be interpreted as a float.
         *
         * @param  mixed   $value
         * @param  string  $decimal  Decimal separator character.
         * @return float
         */
        public static function float(mixed $value, string $decimal = '.'): float
        {
            $filtered = filter_var(
                $value,
                FILTER_VALIDATE_FLOAT,
                ['options' => ['decimal' => $decimal]]
            );

            // filter_var returns false on failure; fall back to 0.0
            return ($filtered !== false) ? (float) $filtered : 0.0;
        }

        /**
         * Sanitize a boolean.
         *
         * Accepts truthy strings: true/false, 1/0, "yes"/"no", "on"/"off".
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function bool(mixed $value): bool
        {
            return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // -------------------------------------------------------------------------
        // Network / identifiers
        // -------------------------------------------------------------------------

        /**
         * Sanitize an email address.
         *
         * Pre-cleans via self::string() (no whitespace), then applies
         * FILTER_SANITIZE_EMAIL followed by FILTER_VALIDATE_EMAIL.
         *
         * @param  mixed  $value
         * @return string  Lowercased, validated address, or empty string on failure.
         */
        public static function email(mixed $value): string
        {
            // Pre-clean: strip tags, special chars, and all whitespace
            $sanitized = filter_var(self::string($value, false, false), FILTER_SANITIZE_EMAIL);
            $validated  = filter_var($sanitized, FILTER_VALIDATE_EMAIL);

            return $validated !== false ? mb_strtolower($validated) : '';
        }

        /**
         * Sanitize and validate a URL.
         *
         * Pre-cleans via self::string() (no whitespace), then applies
         * FILTER_SANITIZE_URL followed by FILTER_VALIDATE_URL.
         *
         * @param  mixed  $value
         * @param  int    $flags  FILTER_FLAG_* constants (e.g. FILTER_FLAG_PATH_REQUIRED).
         * @return string  Validated URL, or empty string on failure.
         */
        public static function url(mixed $value, int $flags = 0): string
        {
            // Pre-clean: strip tags, special chars, and all whitespace
            $sanitized = filter_var(self::string($value, false, false), FILTER_SANITIZE_URL);
            $validated  = filter_var($sanitized, FILTER_VALIDATE_URL, $flags);

            return $validated !== false ? $validated : '';
        }

        /**
         * Sanitize an IP address (v4 or v6).
         *
         * Pre-cleans via self::string() (no whitespace) before validation.
         *
         * @param  mixed  $value
         * @param  bool   $ipv6      Allow IPv6 addresses.
         * @param  bool   $private   Allow private ranges.
         * @param  bool   $reserved  Allow reserved ranges.
         * @return string  Validated IP, or empty string on failure.
         */
        public static function ip(
            mixed $value,
            bool $ipv6     = true,
            bool $private  = true,
            bool $reserved = true
        ): string {
            $flags = 0;

            // Build the flag mask based on version and range preferences
            if ($ipv6) {
                $flags |= FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
            } else {
                $flags |= FILTER_FLAG_IPV4;
            }

            if (! $private) {
                $flags |= FILTER_FLAG_NO_PRIV_RANGE;
            }

            if (! $reserved) {
                $flags |= FILTER_FLAG_NO_RES_RANGE;
            }

            // Pre-clean: strip tags, special chars, and all whitespace
            $validated = filter_var(self::string($value, false, false), FILTER_VALIDATE_IP, $flags);

            return $validated !== false ? $validated : '';
        }

        /**
         * Sanitize a domain name.
         *
         * Pre-cleans via self::string() (no whitespace), lowercases, then
         * strips any character that is not alphanumeric, a dot, or a hyphen.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function domain(mixed $value): string
        {
            // Pre-clean then lowercase before stripping disallowed characters
            $domain = mb_strtolower(self::string($value, false, false));
            $domain = preg_replace('/[^a-z0-9.\-]/', '', $domain);

            // Remove any leading/trailing dots that may remain
            return trim($domain, '.');
        }

        /**
         * Sanitize a phone number.
         *
         * Strips everything except digits, spaces, and the common formatting
         * characters: + ( ) - .
         * No validation of format or country code is performed.
         *
         * @param  mixed  $value
         * @param  bool   $digits_only  Return only numeric digits with no formatting chars.
         * @return string
         */
        public static function phone(mixed $value, bool $digits_only = false): string
        {
            // Pre-clean: strip tags and special chars first
            $str = self::string($value, false, false);

            if ($digits_only) {
                // Strip everything except digits
                return preg_replace('/\D/', '', $str);
            }

            // Keep digits and common phone formatting characters
            return preg_replace('/[^0-9+\(\)\-.\s]/', '', $str);
        }

        /**
         * Sanitize a MAC address.
         *
         * Accepts colon- or hyphen-delimited formats and normalises to
         * lowercase colon-delimited output (e.g. 'aa:bb:cc:dd:ee:ff').
         *
         * @param  mixed  $value
         * @return string  Normalised MAC address, or empty string on failure.
         */
        public static function mac_address(mixed $value): string
        {
            // Pre-clean then normalise delimiter to colon before validation
            $str = strtolower(str_replace('-', ':', self::string($value, false, false)));

            // Must match the standard xx:xx:xx:xx:xx:xx pattern
            if (! preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $str)) {
                return '';
            }

            return $str;
        }

        /**
         * Sanitize and validate a UUID (v1–v5, case-insensitive).
         *
         * Returns the UUID in lowercase canonical form or empty string on failure.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function uuid(mixed $value): string
        {
            // Pre-clean then lowercase for case-insensitive comparison
            $str = strtolower(self::string($value, false, false));

            // RFC 4122 UUID pattern
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $str)) {
                return '';
            }

            return $str;
        }

        /**
         * Sanitize a hex color code.
         *
         * Accepts 3- or 6-character hex values with or without a leading #.
         * Always returns the value with a leading # or empty string on failure.
         *
         * @param  mixed  $value
         * @return string  e.g. '#ff6600', or empty string on failure.
         */
        public static function hex_color(mixed $value): string
        {
            // Pre-clean then strip the leading hash before validation
            $str = ltrim(self::string($value, false, false), '#');

            // Must be exactly 3 or 6 valid hex characters
            if (! preg_match('/^([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $str)) {
                return '';
            }

            return '#' . strtolower($str);
        }

        /**
         * Sanitize a base64-encoded string.
         *
         * Strips characters outside the base64 alphabet, then verifies the
         * string round-trips cleanly through encode/decode.
         *
         * @param  mixed  $value
         * @param  bool   $url_safe  Accept URL-safe base64 (- and _ instead of + and /).
         * @return string  Clean base64 string, or empty string on failure.
         */
        public static function base64(mixed $value, bool $url_safe = false): string
        {
            // Pre-clean then strip characters outside the base64 alphabet
            $str = self::string($value, false, false);

            $pattern = $url_safe ? '/[^A-Za-z0-9\-_=]/' : '/[^A-Za-z0-9+\/=]/';
            $str     = preg_replace($pattern, '', $str);

            // Verify the string is valid by round-tripping it
            if (base64_encode(base64_decode($str, true)) !== $str) {
                return '';
            }

            return $str;
        }

        // -------------------------------------------------------------------------
        // Structured / rich input
        // -------------------------------------------------------------------------

        /**
         * Sanitize a slug (URL path segment).
         *
         * Pre-cleans via self::string() (whitespace preserved for replacement),
         * lowercases, strips non-word characters, then converts spaces/underscores
         * to hyphens and collapses duplicate hyphens.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function slug(mixed $value): string
        {
            // Pre-clean with whitespace preserved so spaces can become hyphens
            $str = mb_strtolower(self::string($value, false, true));

            // Strip anything that is not a Unicode letter, number, space, hyphen, or underscore
            $str = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $str);

            // Normalise spaces and underscores to hyphens
            $str = preg_replace('/[\s_]+/', '-', $str);

            // Collapse consecutive hyphens
            $str = preg_replace('/-{2,}/', '-', $str);

            return trim($str, '-');
        }

        /**
         * Sanitize a username.
         *
         * Allows letters, numbers, underscores, hyphens, and dots.
         * Enforces optional min/max length bounds.
         *
         * @param  mixed     $value
         * @param  int       $min_length  Minimum character length (default 3).
         * @param  int       $max_length  Maximum character length (default 64).
         * @return string  Sanitized username, or empty string if length bounds fail.
         */
        public static function username(mixed $value, int $min_length = 3, int $max_length = 64): string
        {
            // Pre-clean: no whitespace allowed in usernames
            $str = self::string($value, false, false);

            // Only allow the characters typical for a username
            $str = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $str);

            // Reject if outside the specified length bounds
            $len = mb_strlen($str);

            if ($len < $min_length || $len > $max_length) {
                return '';
            }

            return $str;
        }

        /**
         * Sanitize a file name (basename only, no path traversal).
         *
         * Pre-cleans via self::string() (no whitespace), extracts the basename,
         * then replaces disallowed characters and collapses consecutive dots.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function filename(mixed $value): string
        {
            // Pre-clean then isolate basename to prevent directory traversal
            $name = basename(self::string($value, false, false));

            // Replace any character that is not a word char, dot, or hyphen
            $name = preg_replace('/[^\w.\-]/u', '_', $name);

            // Collapse consecutive dots to block extension spoofing (e.g. file..php)
            $name = preg_replace('/\.{2,}/', '.', $name);

            return trim($name, '._');
        }

        /**
         * Sanitize a file system path.
         *
         * Resolves the real path; returns empty string when the path does not
         * exist or escapes an optional $base_dir boundary.
         *
         * @param  mixed       $value
         * @param  string|null $base_dir  Restrict result to this directory.
         * @return string  Resolved absolute path, or empty string on failure.
         */
        public static function path(mixed $value, ?string $base_dir = null): string
        {
            $real = realpath((string) $value);

            // realpath() returns false for non-existent paths
            if ($real === false) {
                return '';
            }

            if ($base_dir !== null) {
                $base = realpath($base_dir);

                // Reject path if base_dir is invalid or path escapes it
                if ($base === false || ! str_starts_with($real, $base)) {
                    return '';
                }
            }

            return $real;
        }

        /**
         * Sanitize a JSON string and return decoded data.
         *
         * Uses json_validate() (PHP 8.3+) before decoding to avoid
         * triggering a JSON exception on obviously bad input.
         *
         * @param  mixed  $value
         * @param  bool   $assoc  Decode objects as associative arrays.
         * @return mixed          Decoded value, or null on failure.
         */
        public static function json(mixed $value, bool $assoc = true): mixed
        {
            $str = trim((string) $value);

            // Fast structural check before full decode
            if (! json_validate($str)) {
                return null;
            }

            return json_decode($str, $assoc, 512, JSON_THROW_ON_ERROR);
        }

        /**
         * Sanitize an XML string.
         *
         * Strips characters that are illegal in XML 1.0, then validates
         * the result parses without errors.
         *
         * @param  mixed  $value
         * @return string  Clean XML string, or empty string on parse failure.
         */
        public static function xml(mixed $value): string
        {
            // Strip illegal XML 1.0 characters per the spec
            $str = preg_replace(
                '/[^\x09\x0A\x0D\x20-\xD7FF\xE000-\xFFFD\x{10000}-\x{10FFFF}]/u',
                '',
                (string) $value
            );

            // Attempt a parse to confirm structural validity
            libxml_use_internal_errors(true);
            $valid = simplexml_load_string($str) !== false;
            libxml_clear_errors();

            return $valid ? $str : '';
        }

        /**
         * Sanitize a date string to a normalized format.
         *
         * Pre-cleans via self::string() (no whitespace) before parsing.
         *
         * @param  mixed   $value
         * @param  string  $format  Expected input format for DateTimeImmutable.
         * @param  string  $output  Desired output format.
         * @return string           Formatted date, or empty string on failure.
         */
        public static function date(
            mixed $value,
            string $format = 'Y-m-d',
            string $output = 'Y-m-d'
        ): string {
            try {
                // Pre-clean: strip tags, special chars, and all whitespace
                $dt = \DateTimeImmutable::createFromFormat($format, self::string($value, false, false));

                return $dt !== false ? $dt->format($output) : '';
            } catch (\Exception) {
                return '';
            }
        }

        /**
         * Sanitize an SVG string.
         *
         * Pre-cleans via self::xml() to strip illegal characters, then removes
         * dangerous elements, event attributes, javascript: URIs, and other
         * common SVG attack vectors.
         *
         * @param  mixed  $value
         * @return string  Sanitized SVG string, or empty string on parse failure.
         */
        public static function svg(mixed $value): string
        {
            // Pre-clean: strip illegal XML characters via self::xml()
            $str = self::xml((string) $value);

            if ($str === '') {
                return '';
            }

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();

            if (! $dom->loadXML($str, LIBXML_NONET | LIBXML_NOERROR)) {
                libxml_clear_errors();
                return '';
            }

            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);

            // Remove dangerous elements entirely — collect first to avoid live NodeList issues
            $dangerous_tags = ['script', 'foreignObject', 'iframe', 'object', 'embed', 'use'];

            foreach ($dangerous_tags as $tag) {
                foreach (iterator_to_array($xpath->query("//{$tag}")) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }

            // Remove on* event attributes and any attribute containing javascript: or data:
            foreach (iterator_to_array($xpath->query('//@*')) as $attr) {
                $name  = strtolower($attr->nodeName);
                $value = strtolower($attr->nodeValue);

                if (
                    str_starts_with($name, 'on') ||
                    str_contains($value, 'javascript:') ||
                    str_contains($value, 'data:')
                ) {
                    // ownerElement can be null if the node was already detached
                    $attr->ownerElement?->removeAttributeNode($attr);
                }
            }

            // Strip javascript: and data: from href and xlink:href specifically
            foreach (iterator_to_array($xpath->query('//@href | //@xlink:href')) as $attr) {
                if (preg_match('/^\s*(javascript|data):/i', $attr->nodeValue)) {
                    $attr->ownerElement?->removeAttributeNode($attr);
                }
            }

            // Sanitize url() references inside style attributes
            foreach (iterator_to_array($xpath->query('//@style')) as $attr) {
                $attr->nodeValue = preg_replace(
                    '/url\s*\(\s*["\']?\s*(javascript|data):/i',
                    'url(#',
                    $attr->nodeValue
                );
            }

            return $dom->saveXML($dom->documentElement);
        }

        /**
         * Strip non-printable and control characters from a string.
         *
         * Useful for sanitizing input destined for logs, CSV output, or any
         * context where control characters could cause parsing issues.
         * Preserves standard whitespace (\t, \n, \r) by default.
         *
         * @param  mixed  $value
         * @param  bool   $strip_whitespace  Also strip \t, \n, \r when true.
         * @return string
         */
        public static function printable(mixed $value, bool $strip_whitespace = false): string
        {
            // Pre-clean first to remove tags and encoded chars
            $str = self::string($value, ! $strip_whitespace, ! $strip_whitespace);

            // Strip C0/C1 control characters; optionally include standard whitespace
            $pattern = $strip_whitespace
                ? '/[\x00-\x1F\x7F-\x9F]/'
                : '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/';

            return preg_replace($pattern, '', $str);
        }

        /**
         * Sanitize a string to alphanumeric characters only.
         *
         * Pre-cleans via self::string() then strips everything outside
         * the a-z, A-Z, 0-9 range.
         *
         * @param  mixed  $value
         * @param  bool   $allow_spaces  Preserve space characters.
         * @return string
         */
        public static function alphanumeric(mixed $value, bool $allow_spaces = false): string
        {
            // Pre-clean with whitespace preserved when spaces are to be kept
            $str = self::string($value, false, $allow_spaces);

            $pattern = $allow_spaces ? '/[^a-zA-Z0-9\s]/' : '/[^a-zA-Z0-9]/';

            return preg_replace($pattern, '', $str);
        }

        /**
         * Ensure a value exists within an allowed whitelist.
         *
         * Returns the sanitized value when it is in the whitelist,
         * or $default when it is not.
         *
         * @param  mixed        $value
         * @param  array        $allowed   List of permitted values.
         * @param  mixed        $default   Returned when $value is not in $allowed.
         * @param  bool         $strict    Use strict type comparison.
         * @return mixed
         */
        public static function whitelist(mixed $value, array $allowed, mixed $default = null, bool $strict = false): mixed
        {
            return in_array($value, $allowed, $strict) ? $value : $default;
        }

        /**
         * Sanitize a string and enforce a maximum length.
         *
         * Pre-cleans via self::string() then truncates to $max_length characters.
         *
         * @param  mixed   $value
         * @param  int     $max_length     Maximum number of characters to allow.
         * @param  bool    $allow_newlines  Passed through to self::string().
         * @return string
         */
        public static function truncate(mixed $value, int $max_length, bool $allow_newlines = false): string
        {
            // Pre-clean first, then cut to the requested length
            return mb_substr(self::string($value, $allow_newlines), 0, $max_length);
        }

        // -------------------------------------------------------------------------
        // Aggregates
        // -------------------------------------------------------------------------

        /**
         * Sanitize each element in an array with a given callable.
         *
         * @param  array     $values
         * @param  callable  $sanitizer  Any static method on this class, or custom callable.
         * @param  mixed     ...$args    Extra arguments forwarded after $value.
         * @return array
         */
        public static function array(array $values, callable $sanitizer, mixed ...$args): array
        {
            return array_map(
                static fn(mixed $v): mixed => $sanitizer($v, ...$args),
                $values
            );
        }

        /**
         * Sanitize a key=value map, applying per-key sanitizer rules.
         *
         * Each rule is either a bare callable or a tuple of [callable, [...args]].
         *
         * Example:
         * <code>
         * KPTV_Sanitize::map( $_POST, [
         *     'name'     => [ [ KPTV_Sanitize::class, 'string' ], [] ],
         *     'email'    => [ KPTV_Sanitize::class, 'email' ],
         *     'age'      => [ [ KPTV_Sanitize::class, 'int' ], [ 0, 120 ] ],
         *     'status'   => [ [ KPTV_Sanitize::class, 'whitelist' ], [ [ 'active', 'inactive' ] ] ],
         * ] );
         * </code>
         *
         * @param  array<string,mixed>                            $data
         * @param  array<string,callable|array{callable,mixed[]}> $rules
         * @return array<string,mixed>
         */
        public static function map(array $data, array $rules): array
        {
            $out = [];

            foreach ($rules as $key => $rule) {
                // Skip keys not present in the input data
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                // Normalize rule to [callable, args] tuple
                [$fn, $args] = is_array($rule) ? $rule : [$rule, []];

                $out[$key] = $fn($data[$key], ...$args);
            }

            return $out;
        }

        // -------------------------------------------------------------------------
        // Superglobal helpers
        // -------------------------------------------------------------------------

        /**
         * Pull and sanitize a scalar from a superglobal via filter_input.
         *
         * @param  int        $type     INPUT_GET | INPUT_POST | INPUT_SERVER | …
         * @param  string     $name     Variable name.
         * @param  int        $filter   FILTER_SANITIZE_* / FILTER_VALIDATE_* constant.
         * @param  array|int  $options  filter_input options or flags.
         * @param  mixed      $default  Returned when the key is absent or invalid.
         * @return mixed
         */
        public static function input(
            int $type,
            string $name,
            int $filter        = FILTER_DEFAULT,
            array|int $options = 0,
            mixed $default     = null
        ): mixed {
            // Normalize int flags to the options array form filter_input expects
            $opts   = is_array($options) ? $options : ['flags' => $options];
            $result = filter_input($type, $name, $filter, $opts);

            // filter_input returns null (missing) or false (invalid) on failure
            return ($result === null || $result === false) ? $default : $result;
        }

        /**
         * Pull and sanitize multiple keys from a superglobal via filter_input_array.
         *
         * Keys whose values are null or false after filtering are removed from the result.
         *
         * @param  int                      $type        INPUT_GET | INPUT_POST | …
         * @param  array<string,int|array>  $definition  filter_input_array definition map.
         * @return array<string,mixed>
         */
        public static function input_array(int $type, array $definition): array
        {
            $result = filter_input_array($type, $definition, false);

            // Return an empty array when filter_input_array itself fails
            return is_array($result) ? array_filter(
                $result,
                static fn(mixed $v): bool => $v !== null && $v !== false
            ) : [];
        }

        // -------------------------------------------------------------------------
        // Encoding helpers
        // -------------------------------------------------------------------------

        /**
         * Encode a value for safe HTML attribute output.
         *
         * Pre-cleans via self::string() before encoding.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function attr(mixed $value): string
        {
            // Pre-clean then encode remaining special characters for attribute context
            return htmlspecialchars(
                self::string($value),
                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                'UTF-8'
            );
        }

        /**
         * Encode a value for safe inline JavaScript string output.
         *
         * JSON-encodes the value with aggressive hex escaping so it is safe
         * to drop directly into a JS context without additional wrapping.
         *
         * @param  mixed  $value
         * @return string  JSON-encoded value safe for JS embedding.
         */
        public static function js(mixed $value): string
        {
            return json_encode(
                $value,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
            );
        }

        /**
         * Sanitize a value for safe use as a CSS property value.
         *
         * Pre-cleans via self::string() then strips characters outside the
         * set commonly required for valid CSS values.
         *
         * @param  mixed  $value
         * @return string
         */
        public static function css(mixed $value): string
        {
            // Pre-clean then whitelist characters valid in CSS property values
            return preg_replace('/[^a-zA-Z0-9\s\-_.,#%()\/]/', '', self::string($value));
        }

        // -------------------------------------------------------------------------
        // Numeric helpers
        // -------------------------------------------------------------------------

        /**
         * Clamp a numeric value between a min and max.
         *
         * @param  int|float  $value
         * @param  int|float  $min
         * @param  int|float  $max
         * @return int|float
         */
        public static function clamp(int|float $value, int|float $min, int|float $max): int|float
        {
            return max($min, min($max, $value));
        }

        /**
         * Sanitize and validate a port number (0–65535).
         *
         * Delegates range validation to self::int().
         *
         * @param  mixed  $value
         * @return int|null  Null when the value is not a valid port number.
         */
        public static function port(mixed $value): ?int
        {
            // Delegate to self::int() with port-number bounds
            $int = self::int($value, 0, 65535);

            // Distinguish a legitimate 0 from a failed parse that also returns 0
            return ($int > 0 || (string) $value === '0') ? $int : null;
        }
    }
}
