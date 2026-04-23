<?php

/**
 * Array Functions
 *
 * This is our primary array utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Arr')) {

    /**
     * Arr
     *
     * A modern PHP 8.2+ array utility class providing multi-needle search,
     * key subset matching, multi-dimensional sorting, and object conversion.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Arr
    {
        // -------------------------------------------------------------------------
        // Search
        // -------------------------------------------------------------------------

        /**
         * Check whether any element in an array contains the given substring.
         *
         * Search is case-insensitive and uses partial matching — an element
         * matches if it contains the needle anywhere within it.  Provides an
         * 8.2-compatible fallback for the PHP 8.4 array_any() function.
         *
         * @param  string  $needle    The substring to search for.
         * @param  array   $haystack  The array to search within.
         * @return bool
         */
        public static function findInArray(string $needle, array $haystack): bool
        {
            // PHP 8.4+: delegate to the native function
            if (function_exists('array_any')) {
                return array_any(
                    $haystack,
                    fn(mixed $item): bool => stripos((string) $item, $needle) !== false
                );
            }

            // PHP 8.2 / 8.3 fallback: early-return foreach
            foreach ($haystack as $item) {
                if (stripos((string) $item, $needle) !== false) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Find the first array element whose key contains a given substring.
         *
         * Provides an 8.2-compatible fallback for the PHP 8.4 array_find_key()
         * function.  Returns the matched element's value, or false when no key
         * contains the subset.
         *
         * @param  array   $array          The array to search.
         * @param  string  $subset         The substring to look for in keys.
         * @param  bool    $caseSensitive  Whether the key search is case-sensitive.
         * @return mixed                   The matched element value, or false.
         */
        public static function arrayKeyContainsSubset(
            array $array,
            string $subset,
            bool $caseSensitive = true
        ): mixed {
            if (empty($array)) {
                return false;
            }

            // PHP 8.4+: delegate to the native function
            if (function_exists('array_find_key')) {
                $matchingKey = array_find_key(
                    $array,
                    fn(mixed $value, mixed $key): bool => $caseSensitive
                        ? str_contains((string) $key, $subset)
                        : stripos((string) $key, $subset) !== false
                );

                return $matchingKey !== null ? $array[$matchingKey] : false;
            }

            // PHP 8.2 / 8.3 fallback: early-return foreach over keys
            foreach (array_keys($array) as $key) {
                $matched = $caseSensitive
                    ? str_contains((string) $key, $subset)
                    : stripos((string) $key, $subset) !== false;

                if ($matched) {
                    return $array[$key];
                }
            }

            return false;
        }

        // -------------------------------------------------------------------------
        // Sorting
        // -------------------------------------------------------------------------

        /**
         * Sort a multi-dimensional array by a shared subkey.
         *
         * Numeric subkey values use spaceship comparison; all other values
         * are compared case-insensitively as strings.  The array is sorted
         * in place via usort().
         *
         * @param  array   &$array   The array to sort (modified in place).
         * @param  string  $subkey   The key to sort by (default 'id').
         * @param  bool    $sortAsc  True for ascending, false for descending.
         * @return void
         */
        public static function sortMultiDim(array &$array, string $subkey = 'id', bool $sortAsc = true): void
        {
            usort($array, function (mixed $a, mixed $b) use ($subkey): int {
                $valA = $a[$subkey] ?? null;
                $valB = $b[$subkey] ?? null;

                // Use numeric spaceship comparison when both values are numeric
                if (is_numeric($valA) && is_numeric($valB)) {
                    return $valA <=> $valB;
                }

                // Fall back to case-insensitive string comparison
                return strcasecmp((string) $valA, (string) $valB);
            });

            // Reverse after sort rather than during to keep the comparator simple
            if (! $sortAsc) {
                $array = array_reverse($array);
            }
        }

        // -------------------------------------------------------------------------
        // Conversion
        // -------------------------------------------------------------------------

        /**
         * Recursively convert an object (or nested objects) to an array.
         *
         * Arrays nested within the object are walked recursively so that any
         * objects they contain are also converted.
         *
         * @param  object  $value  The object to convert.
         * @return array
         */
        public static function objectToArray(object $value): array
        {
            $result = [];

            foreach ($value as $key => $val) {
                if (is_object($val)) {
                    // Recurse directly into nested objects
                    $result[$key] = self::objectToArray($val);
                } elseif (is_array($val)) {
                    // Walk nested arrays so any objects inside are also converted
                    $result[$key] = array_map(
                        fn(mixed $item): mixed => is_object($item) ? self::objectToArray($item) : $item,
                        $val
                    );
                } else {
                    $result[$key] = $val;
                }
            }

            return $result;
        }

        // -------------------------------------------------------------------------
        // Transformation
        // -------------------------------------------------------------------------

        /**
         * Flatten a multi-dimensional array to a single level.
         *
         * @param  array  $array
         * @param  float  $depth  Depth limit (INF for full flattening).
         * @return array
         */
        public static function flatten(array $array, float $depth = INF): array
        {
            $result = [];

            foreach ($array as $item) {
                if (is_array($item) && $depth > 0) {
                    $result = array_merge($result, self::flatten($item, $depth - 1));
                } else {
                    $result[] = $item;
                }
            }

            return $result;
        }

        /**
         * Pluck a column of values from a multi-dimensional array.
         *
         * Optionally key the result by another column.
         *
         * @param  array        $array
         * @param  string       $value  Column to pluck as values.
         * @param  string|null  $key    Column to use as keys (optional).
         * @return array
         */
        public static function pluck(array $array, string $value, ?string $key = null): array
        {
            $results = [];

            foreach ($array as $item) {
                $val = is_array($item) ? ($item[$value] ?? null) : ($item->$value ?? null);

                if ($key !== null) {
                    $k           = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
                    $results[$k] = $val;
                } else {
                    $results[] = $val;
                }
            }

            return $results;
        }

        /**
         * Group a multi-dimensional array by a field or callback result.
         *
         * @param  array            $array
         * @param  string|callable  $key  Field name or fn(mixed $item): mixed
         * @return array
         */
        public static function groupBy(array $array, string|callable $key): array
        {
            $result = [];

            foreach ($array as $item) {
                $groupKey = is_callable($key)
                    ? $key($item)
                    : (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null));

                $result[$groupKey][] = $item;
            }

            return $result;
        }

        /**
         * Return only the elements whose keys are in the given list.
         *
         * @param  array  $array
         * @param  array  $keys
         * @return array
         */
        public static function only(array $array, array $keys): array
        {
            return array_intersect_key($array, array_flip($keys));
        }

        /**
         * Return all elements except those whose keys are in the given list.
         *
         * @param  array  $array
         * @param  array  $keys
         * @return array
         */
        public static function except(array $array, array $keys): array
        {
            return array_diff_key($array, array_flip($keys));
        }

        // -------------------------------------------------------------------------
        // Dot notation
        // -------------------------------------------------------------------------

        /**
         * Flatten a nested array into dot-notation keys.
         *
         * Example: ['user' => ['name' => 'Kevin']] → ['user.name' => 'Kevin']
         *
         * @param  array   $array
         * @param  string  $prefix  Internal prefix for recursion.
         * @return array
         */
        public static function dotNotationFlatten(array $array, string $prefix = ''): array
        {
            $result = [];

            foreach ($array as $key => $value) {
                $dotKey = $prefix !== '' ? $prefix . '.' . $key : (string) $key;

                if (is_array($value) && ! empty($value)) {
                    $result += self::dotNotationFlatten($value, $dotKey);
                } else {
                    $result[$dotKey] = $value;
                }
            }

            return $result;
        }

        /**
         * Expand a flat dot-notation array into a nested array.
         *
         * Example: ['user.name' => 'Kevin'] → ['user' => ['name' => 'Kevin']]
         *
         * @param  array  $array
         * @return array
         */
        public static function dotNotationExpand(array $array): array
        {
            $result = [];

            foreach ($array as $key => $value) {
                // Fast path — no dot means top-level key only
                if (! str_contains((string) $key, '.')) {
                    $result[$key] = $value;
                    continue;
                }

                $segments = explode('.', (string) $key);
                $current  = &$result;

                foreach ($segments as $segment) {
                    if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                        $current[$segment] = [];
                    }

                    $current = &$current[$segment];
                }

                $current = $value;
            }

            return $result;
        }

        // -------------------------------------------------------------------------
        // Inspection
        // -------------------------------------------------------------------------

        /**
         * Check whether an array is associative.
         *
         * An array is considered associative when its keys are not a sequential
         * integer sequence starting from 0.
         *
         * @param  array  $array
         * @return bool
         */
        public static function isAssoc(array $array): bool
        {
            if (empty($array)) {
                return false;
            }

            return array_keys($array) !== range(0, count($array) - 1);
        }

        // -------------------------------------------------------------------------
        // Retrieval
        // -------------------------------------------------------------------------

        /**
         * Get the first element of an array, or the first element matching a callback.
         *
         * @param  array          $array
         * @param  callable|null  $callback  fn(mixed $value, mixed $key): bool
         * @param  mixed          $default   Returned when no match is found.
         * @return mixed
         */
        public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
        {
            if ($callback === null) {
                return ! empty($array) ? reset($array) : $default;
            }

            foreach ($array as $key => $value) {
                if ($callback($value, $key)) {
                    return $value;
                }
            }

            return $default;
        }

        /**
         * Get the last element of an array, or the last element matching a callback.
         *
         * @param  array          $array
         * @param  callable|null  $callback  fn(mixed $value, mixed $key): bool
         * @param  mixed          $default   Returned when no match is found.
         * @return mixed
         */
        public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
        {
            if ($callback === null) {
                return ! empty($array) ? end($array) : $default;
            }

            $match = $default;

            foreach ($array as $key => $value) {
                if ($callback($value, $key)) {
                    $match = $value;
                }
            }

            return $match;
        }

        /**
         * Ensure a value is an array.
         *
         * Passes arrays through unchanged.  Wraps scalars and objects in an array.
         * Returns an empty array for null.
         *
         * @param  mixed  $value
         * @return array
         */
        public static function wrap(mixed $value): array
        {
            if (is_null($value)) {
                return [];
            }

            return is_array($value) ? $value : [$value];
        }

        // -------------------------------------------------------------------------
        // Combination
        // -------------------------------------------------------------------------

        /**
         * Zip one or more arrays together by index.
         *
         * Each element in the result is an array of corresponding elements
         * from the input arrays.  Result length matches the longest input.
         *
         * @param  array  ...$arrays
         * @return array
         */
        public static function zip(array ...$arrays): array
        {
            if (empty($arrays)) {
                return [];
            }

            $length = max(array_map('count', $arrays));
            $result = [];

            for ($i = 0; $i < $length; $i++) {
                $result[] = array_map(fn(array $arr): mixed => $arr[$i] ?? null, $arrays);
            }

            return $result;
        }

        /**
         * Split an array into chunks of a given size.
         *
         * Returns an array of arrays.  Preserves keys within each chunk.
         *
         * @param  array  $array
         * @param  int    $size
         * @return array
         */
        public static function chunk(array $array, int $size): array
        {
            return array_chunk($array, max(1, $size), true);
        }

        /**
         * Return a shuffled copy of an array without modifying the original.
         *
         * @param  array  $array
         * @return array
         */
        public static function shuffle(array $array): array
        {
            $copy = $array;
            shuffle($copy);

            return $copy;
        }
    }
}
