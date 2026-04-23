<?php

/**
 * Environment Functions
 *
 * This is our primary .env file parser and accessor class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Env')) {

    /**
     * Env
     *
     * A modern PHP 8.2+ .env file parser with variable interpolation and
     * typed accessors.  Supports single/double-quoted values, inline comments,
     * export-prefixed lines, and ${VAR} / $VAR interpolation syntax.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Env
    {
        // -------------------------------------------------------------------------
        // Internal state
        // -------------------------------------------------------------------------

        /** @var array<string,string> Parsed environment variables */
        private static array $data = [];

        // -------------------------------------------------------------------------
        // Loading
        // -------------------------------------------------------------------------

        /**
         * Load and parse a .env file.
         *
         * Can be called multiple times to merge additional files.
         * When $override is false, existing keys are not overwritten.
         * When $putenv is true, each variable is also registered via putenv()
         * and written to $_ENV so getenv() and $_ENV[] work as expected.
         *
         * @param  string  $path      Absolute path to the .env file.
         * @param  bool    $override  Overwrite already-loaded keys.
         * @param  bool    $putenv    Also expose variables via putenv() and $_ENV.
         * @return void
         *
         * @throws \RuntimeException When the file cannot be read.
         */
        public static function load(string $path, bool $override = false, bool $putenv = false): void
        {
            if (! is_file($path) || ! is_readable($path)) {
                throw new \RuntimeException('Unable to read .env file: ' . $path);
            }

            $parsed = self::parse(file_get_contents($path));

            foreach ($parsed as $key => $value) {
                // Interpolate references to already-loaded variables
                $value = self::interpolate($value);

                if ($override || ! array_key_exists($key, self::$data)) {
                    self::$data[$key] = $value;

                    if ($putenv) {
                        putenv($key . '=' . $value);
                        $_ENV[$key] = $value;
                    }
                }
            }
        }

        // -------------------------------------------------------------------------
        // Accessors
        // -------------------------------------------------------------------------

        /**
         * Get a raw string value.
         *
         * Falls back to getenv() when the key is not in the loaded data,
         * allowing variables set outside the .env file to be retrieved too.
         *
         * @param  string  $key
         * @param  mixed   $default  Returned when the key is absent.
         * @return mixed
         */
        public static function get(string $key, mixed $default = null): mixed
        {
            if (array_key_exists($key, self::$data)) {
                return self::$data[$key];
            }

            // Fall through to system environment
            $env = getenv($key);

            return $env !== false ? $env : $default;
        }

        /**
         * Get a value cast to string.
         *
         * @param  string  $key
         * @param  string  $default
         * @return string
         */
        public static function getString(string $key, string $default = ''): string
        {
            return (string) self::get($key, $default);
        }

        /**
         * Get a value cast to int.
         *
         * @param  string  $key
         * @param  int     $default
         * @return int
         */
        public static function getInt(string $key, int $default = 0): int
        {
            return (int) self::get($key, $default);
        }

        /**
         * Get a value cast to float.
         *
         * @param  string  $key
         * @param  float   $default
         * @return float
         */
        public static function getFloat(string $key, float $default = 0.0): float
        {
            return (float) self::get($key, $default);
        }

        /**
         * Get a value cast to bool.
         *
         * Truthy strings: true, 1, yes, on (case-insensitive).
         * All other values return false.
         *
         * @param  string  $key
         * @param  bool    $default
         * @return bool
         */
        public static function getBool(string $key, bool $default = false): bool
        {
            $value = self::get($key);

            if ($value === null) {
                return $default;
            }

            return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
        }

        /**
         * Get a delimited value as an array.
         *
         * Example: APP_HOSTS=foo.com,bar.com → ['foo.com', 'bar.com']
         *
         * @param  string  $key
         * @param  string  $delimiter
         * @param  array   $default
         * @return array
         */
        public static function getArray(string $key, string $delimiter = ',', array $default = []): array
        {
            $value = self::get($key);

            if ($value === null || $value === '') {
                return $default;
            }

            return array_map('trim', explode($delimiter, (string) $value));
        }

        /**
         * Check whether a key is present.
         *
         * Also checks the system environment via getenv().
         *
         * @param  string  $key
         * @return bool
         */
        public static function has(string $key): bool
        {
            return array_key_exists($key, self::$data) || getenv($key) !== false;
        }

        /**
         * Get a value that must be present.
         *
         * @param  string  $key
         * @return string
         *
         * @throws \RuntimeException When the key is absent.
         */
        public static function required(string $key): string
        {
            if (! self::has($key)) {
                throw new \RuntimeException('Required environment variable "' . $key . '" is not set.');
            }

            return self::getString($key);
        }

        /**
         * Set a value at runtime.
         *
         * Does not persist to the .env file — runtime only.
         *
         * @param  string  $key
         * @param  string  $value
         * @return void
         */
        public static function set(string $key, string $value): void
        {
            self::$data[$key] = $value;
        }

        /**
         * Return all loaded variables as an associative array.
         *
         * @return array<string,string>
         */
        public static function all(): array
        {
            return self::$data;
        }

        /**
         * Clear all loaded variables.
         *
         * Useful for testing or reloading a different .env file.
         *
         * @return void
         */
        public static function flush(): void
        {
            self::$data = [];
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Parse raw .env file contents into a key => value map.
         *
         * @param  string  $contents
         * @return array<string,string>
         */
        private static function parse(string $contents): array
        {
            $result = [];

            foreach (explode("\n", str_replace("\r\n", "\n", $contents)) as $line) {
                $parsed = self::parseLine($line);

                if ($parsed !== null) {
                    [$key, $value]  = $parsed;
                    $result[$key]   = $value;
                }
            }

            return $result;
        }

        /**
         * Parse a single line into a [key, value] tuple.
         *
         * Returns null for blank lines and comments.
         *
         * Handles:
         * - export KEY=VALUE       (export prefix)
         * - KEY=VALUE              (unquoted — inline # stripped)
         * - KEY="VALUE"            (double-quoted — escape sequences processed)
         * - KEY='VALUE'            (single-quoted — literal, no interpolation)
         * - KEY=                   (empty value)
         * - KEY                    (valueless — treated as empty string)
         *
         * @param  string  $line
         * @return array{0:string,1:string}|null
         */
        private static function parseLine(string $line): ?array
        {
            // Strip leading/trailing whitespace
            $line = trim($line);

            // Skip blank lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                return null;
            }

            // Strip optional export prefix
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            // Split on the first = only
            $eqPos = strpos($line, '=');

            // Valueless key — treat as empty string
            if ($eqPos === false) {
                $key = trim($line);
                return self::isValidKey($key) ? [$key, ''] : null;
            }

            $key   = trim(substr($line, 0, $eqPos));
            $value = substr($line, $eqPos + 1);

            if (! self::isValidKey($key)) {
                return null;
            }

            // Double-quoted value — process escape sequences, allow interpolation later
            if (str_starts_with($value, '"')) {
                $value = self::parseDoubleQuoted($value);

                return [$key, $value];
            }

            // Single-quoted value — literal, no further processing
            if (str_starts_with($value, "'")) {
                $value = self::parseSingleQuoted($value);

                return [$key, $value];
            }

            // Unquoted — strip inline comments and surrounding whitespace
            if (($commentPos = strpos($value, ' #')) !== false) {
                $value = substr($value, 0, $commentPos);
            }

            return [$key, trim($value)];
        }

        /**
         * Extract the content of a double-quoted value, processing escape sequences.
         *
         * @param  string  $raw  Raw value string starting with ".
         * @return string
         */
        private static function parseDoubleQuoted(string $raw): string
        {
            // Find the closing quote, respecting backslash escapes
            $len    = strlen($raw);
            $result = '';
            $i      = 1; // Skip opening quote

            while ($i < $len) {
                $char = $raw[$i];

                if ($char === '\\' && $i + 1 < $len) {
                    // Process escape sequences
                    $next = $raw[$i + 1];
                    $result .= match ($next) {
                        'n'     => "\n",
                        'r'     => "\r",
                        't'     => "\t",
                        '"'     => '"',
                        '\\'    => '\\',
                        '$'     => '$',
                        default => '\\' . $next,
                    };
                    $i += 2;
                    continue;
                }

                // Closing quote
                if ($char === '"') {
                    break;
                }

                $result .= $char;
                $i++;
            }

            return $result;
        }

        /**
         * Extract the content of a single-quoted value (literal — no processing).
         *
         * @param  string  $raw  Raw value string starting with '.
         * @return string
         */
        private static function parseSingleQuoted(string $raw): string
        {
            // Strip the surrounding single quotes only
            if (strlen($raw) >= 2 && str_ends_with($raw, "'")) {
                return substr($raw, 1, -1);
            }

            // Malformed — return as-is without quotes
            return ltrim($raw, "'");
        }

        /**
         * Interpolate ${VAR} and $VAR references within a value.
         *
         * References are resolved from already-loaded data first,
         * then from the system environment via getenv().
         * Unresolvable references are replaced with an empty string.
         *
         * @param  string  $value
         * @return string
         */
        private static function interpolate(string $value): string
        {
            // ${VAR_NAME} syntax
            $value = preg_replace_callback(
                '/\$\{([A-Z_][A-Z0-9_]*)\}/i',
                fn(array $m): string => self::$data[$m[1]] ?? (getenv($m[1]) ?: ''),
                $value
            );

            // $VAR_NAME syntax — must not be preceded by alphanumeric or underscore
            $value = preg_replace_callback(
                '/(?<![A-Z0-9_])\$([A-Z_][A-Z0-9_]*)/i',
                fn(array $m): string => self::$data[$m[1]] ?? (getenv($m[1]) ?: ''),
                $value
            );

            return $value;
        }

        /**
         * Validate that a key contains only alphanumeric characters and underscores.
         *
         * @param  string  $key
         * @return bool
         */
        private static function isValidKey(string $key): bool
        {
            return $key !== '' && (bool) preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key);
        }
    }
}
