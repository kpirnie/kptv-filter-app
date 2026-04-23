<?php

/**
 * Cast Functions
 *
 * This is our primary type casting class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Cast')) {

    /**
     * Cast
     *
     * A modern PHP 8.2+ type casting utility providing safe, explicit coercion
     * between scalar types and arrays.  Unlike Sanitize, no cleaning or
     * validation is performed — values are purely converted between types.
     *
     * When $nullable is false (default), failed casts return a typed zero-value.
     * When $nullable is true, failed casts return null.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Cast
    {
        // -------------------------------------------------------------------------
        // Scalar casts
        // -------------------------------------------------------------------------

        /**
         * Cast a value to int.
         *
         * Strings are parsed with intval() using base 10.
         * Booleans become 0 or 1.
         * Non-numeric strings return 0 or null depending on $nullable.
         *
         * @param  mixed  $value
         * @param  bool   $nullable  Return null instead of 0 on failure.
         * @return int|null
         */
        public static function toInt(mixed $value, bool $nullable = false): ?int
        {
            if (is_int($value)) {
                return $value;
            }

            if (is_bool($value)) {
                return (int) $value;
            }

            if (is_float($value)) {
                return (int) $value;
            }

            if (is_string($value) && is_numeric(trim($value))) {
                return (int) trim($value);
            }

            if (is_null($value)) {
                return $nullable ? null : 0;
            }

            return $nullable ? null : 0;
        }

        /**
         * Cast a value to float.
         *
         * Strings are parsed with floatval().
         * Non-numeric strings return 0.0 or null depending on $nullable.
         *
         * @param  mixed  $value
         * @param  bool   $nullable  Return null instead of 0.0 on failure.
         * @return float|null
         */
        public static function toFloat(mixed $value, bool $nullable = false): ?float
        {
            if (is_float($value)) {
                return $value;
            }

            if (is_int($value)) {
                return (float) $value;
            }

            if (is_bool($value)) {
                return (float) $value;
            }

            if (is_string($value) && is_numeric(trim($value))) {
                return (float) trim($value);
            }

            if (is_null($value)) {
                return $nullable ? null : 0.0;
            }

            return $nullable ? null : 0.0;
        }

        /**
         * Cast a value to bool.
         *
         * Truthy strings: 'true', '1', 'yes', 'on' (case-insensitive).
         * Falsy strings:  'false', '0', 'no', 'off', '' (case-insensitive).
         * Unrecognised strings return false or null depending on $nullable.
         *
         * @param  mixed  $value
         * @param  bool   $nullable  Return null instead of false on failure.
         * @return bool|null
         */
        public static function toBool(mixed $value, bool $nullable = false): ?bool
        {
            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (bool) $value;
            }

            if (is_null($value)) {
                return $nullable ? null : false;
            }

            if (is_string($value)) {
                $lower = strtolower(trim($value));

                if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                    return true;
                }

                if (in_array($lower, ['false', '0', 'no', 'off', ''], true)) {
                    return false;
                }

                // Unrecognised string
                return $nullable ? null : false;
            }

            return $nullable ? null : false;
        }

        /**
         * Cast a value to string.
         *
         * Scalars and objects implementing __toString() are converted directly.
         * Arrays return '[]' or null depending on $nullable.
         * null returns '' or null depending on $nullable.
         *
         * @param  mixed  $value
         * @param  bool   $nullable  Return null instead of '' on failure.
         * @return string|null
         */
        public static function toString(mixed $value, bool $nullable = false): ?string
        {
            if (is_string($value)) {
                return $value;
            }

            if (is_null($value)) {
                return $nullable ? null : '';
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                return (string) $value;
            }

            // Arrays and non-stringable objects
            return $nullable ? null : '';
        }

        /**
         * Cast a value to array.
         *
         * Arrays are passed through unchanged.
         * null returns [] or null depending on $nullable.
         * Objects are cast via (array) — public properties become keys.
         * JSON strings are decoded when valid.
         * Scalars are wrapped in a single-element array.
         *
         * @param  mixed  $value
         * @param  bool   $nullable  Return null instead of [] on failure.
         * @return array|null
         */
        public static function toArray(mixed $value, bool $nullable = false): ?array
        {
            if (is_array($value)) {
                return $value;
            }

            if (is_null($value)) {
                return $nullable ? null : [];
            }

            if (is_object($value)) {
                return (array) $value;
            }

            // Attempt JSON decode for string values that look like arrays/objects
            if (is_string($value)) {
                $trimmed = trim($value);

                if (
                    (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '['))
                    && function_exists('json_validate')
                    ? json_validate($trimmed)
                    : json_decode($trimmed) !== null
                ) {
                    $decoded = json_decode($trimmed, true);

                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }

                // Non-JSON string — wrap in array
                return [$value];
            }

            // Scalar — wrap in array
            return [$value];
        }

        /**
         * Cast a value to a Collection.
         *
         * Converts to array first via toArray(), then wraps in a Collection.
         *
         * @param  mixed  $value
         * @param  bool   $nullable  Return null instead of an empty Collection on failure.
         * @return \KPT\Collection|null
         */
        public static function toCollection(mixed $value, bool $nullable = false): ?\KPT\Collection
        {
            $array = self::toArray($value, $nullable);

            if ($array === null) {
                return null;
            }

            return \KPT\Collection::make($array);
        }

        // -------------------------------------------------------------------------
        // Numeric casts
        // -------------------------------------------------------------------------

        /**
         * Cast a value to a non-negative int.
         *
         * Negative values are clamped to 0.
         *
         * @param  mixed  $value
         * @param  bool   $nullable
         * @return int|null
         */
        public static function toUnsignedInt(mixed $value, bool $nullable = false): ?int
        {
            $int = self::toInt($value, $nullable);

            return $int !== null ? max(0, $int) : null;
        }

        /**
         * Cast a value to a positive int.
         *
         * Zero and negative values return 1 or null depending on $nullable.
         *
         * @param  mixed  $value
         * @param  bool   $nullable
         * @return int|null
         */
        public static function toPositiveInt(mixed $value, bool $nullable = false): ?int
        {
            $int = self::toInt($value, $nullable);

            if ($int === null) {
                return null;
            }

            return $int > 0 ? $int : ($nullable ? null : 1);
        }

        // -------------------------------------------------------------------------
        // Nullable convenience wrappers
        // -------------------------------------------------------------------------

        /**
         * Cast to int or return null on failure.
         *
         * @param  mixed  $value
         * @return int|null
         */
        public static function intOrNull(mixed $value): ?int
        {
            return self::toInt($value, true);
        }

        /**
         * Cast to float or return null on failure.
         *
         * @param  mixed  $value
         * @return float|null
         */
        public static function floatOrNull(mixed $value): ?float
        {
            return self::toFloat($value, true);
        }

        /**
         * Cast to bool or return null on failure.
         *
         * @param  mixed  $value
         * @return bool|null
         */
        public static function boolOrNull(mixed $value): ?bool
        {
            return self::toBool($value, true);
        }

        /**
         * Cast to string or return null on failure.
         *
         * @param  mixed  $value
         * @return string|null
         */
        public static function stringOrNull(mixed $value): ?string
        {
            return self::toString($value, true);
        }

        /**
         * Cast to array or return null on failure.
         *
         * @param  mixed  $value
         * @return array|null
         */
        public static function arrayOrNull(mixed $value): ?array
        {
            return self::toArray($value, true);
        }
    }
}
