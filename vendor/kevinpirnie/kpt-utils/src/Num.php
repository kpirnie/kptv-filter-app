<?php

/**
 * Number Functions
 *
 * This is our primary number utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Num')) {

    /**
     * Num
     *
     * A modern PHP 8.2+ number utility class providing ordinal formatting
     * and human-readable byte conversion.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Num
    {
        // -------------------------------------------------------------------------
        // Formatting
        // -------------------------------------------------------------------------

        /**
         * Format an integer as an ordinal number string.
         *
         * Correctly handles the 11th/12th/13th edge cases that naive
         * modulo-10 implementations get wrong.
         *
         * Examples: 1 → '1st', 11 → '11th', 22 → '22nd', 103 → '103rd'
         *
         * @param  int  $value  The number to format.
         * @return string
         */
        public static function ordinal(int $value): string
        {
            $abs    = abs($value);
            $mod100 = $abs % 100;
            $mod10  = $abs % 10;

            // 11, 12, 13 are exceptions — they always use 'th' regardless of mod10
            $suffix = match (true) {
                $mod100 >= 11 && $mod100 <= 13 => 'th',
                $mod10 === 1                    => 'st',
                $mod10 === 2                    => 'nd',
                $mod10 === 3                    => 'rd',
                default                         => 'th',
            };

            return $value . $suffix;
        }

        /**
         * Format a byte count as a human-readable string.
         *
         * Scales automatically from bytes through to petabytes.
         * Returns '0 B' for zero or negative input.
         *
         * Examples: 1024 → '1 KB', 1536 → '1.5 KB', 1073741824 → '1 GB'
         *
         * @param  int  $size       Size in bytes.
         * @param  int  $precision  Decimal places in the output (default 2).
         * @return string
         */
        public static function formatBytes(int $size, int $precision = 2): string
        {
            if ($size <= 0) {
                return '0 B';
            }

            $suffixes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
            $base     = log($size, 1024);
            $index    = (int) min(floor($base), count($suffixes) - 1);

            return round(pow(1024, $base - $index), $precision) . ' ' . $suffixes[$index];
        }

        /**
         * Clamp a numeric value between a minimum and maximum.
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
         * Format a number as a currency string.
         *
         * @param  int|float  $value
         * @param  string     $currency   ISO 4217 currency code (default 'USD').
         * @param  string     $locale     ICU locale string (default 'en_US').
         * @param  int        $precision  Decimal places (default 2).
         * @return string
         */
        public static function formatCurrency(
            int|float $value,
            string $currency = 'USD',
            string $locale = 'en_US',
            int $precision = 2
        ): string {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $precision);

            return $formatter->formatCurrency((float) $value, $currency);
        }

        /**
         * Format a number as a percentage string.
         *
         * @param  int|float  $value      Value between 0 and 1 (e.g. 0.75 → '75%').
         * @param  int        $precision  Decimal places (default 1).
         * @param  string     $locale     ICU locale string (default 'en_US').
         * @return string
         */
        public static function formatPercent(int|float $value, int $precision = 1, string $locale = 'en_US'): string
        {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::PERCENT);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $precision);

            return $formatter->format((float) $value);
        }

        /**
         * Convert an integer to a Roman numeral string.
         *
         * Supports values from 1 to 3999.
         * Returns an empty string for out-of-range input.
         *
         * @param  int  $value
         * @return string
         */
        public static function toRoman(int $value): string
        {
            if ($value < 1 || $value > 3999) {
                return '';
            }

            $map = [
                1000 => 'M',
                900 => 'CM',
                800 => 'DCCC',
                700 => 'DCC',
                600 => 'DC',
                500 => 'D',
                400 => 'CD',
                300 => 'CCC',
                200 => 'CC',
                100 => 'C',
                90 => 'XC',
                80 => 'LXXX',
                70 => 'LXX',
                60 => 'LX',
                50 => 'L',
                40 => 'XL',
                30 => 'XXX',
                20 => 'XX',
                10 => 'X',
                9 => 'IX',
                8 => 'VIII',
                7 => 'VII',
                6 => 'VI',
                5 => 'V',
                4 => 'IV',
                3 => 'III',
                2 => 'II',
                1 => 'I',
            ];

            $result = '';

            foreach ($map as $num => $numeral) {
                while ($value >= $num) {
                    $result .= $numeral;
                    $value  -= $num;
                }
            }

            return $result;
        }

        /**
         * Convert a Roman numeral string to an integer.
         *
         * Case-insensitive. Returns 0 for invalid input.
         *
         * @param  string  $value
         * @return int
         */
        public static function fromRoman(string $value): int
        {
            $map = [
                'M' => 1000,
                'CM' => 900,
                'CD' => 400,
                'D' => 500,
                'C' => 100,
                'XC' => 90,
                'XL' => 40,
                'L' => 50,
                'X' => 10,
                'IX' => 9,
                'IV' => 4,
                'V' => 5,
                'I' => 1,
            ];

            $value  = strtoupper(trim($value));
            $result = 0;
            $i      = 0;
            $length = strlen($value);

            while ($i < $length) {
                // Check two-character numeral first before falling back to one
                $two = substr($value, $i, 2);
                $one = $value[$i];

                if (isset($map[$two])) {
                    $result += $map[$two];
                    $i      += 2;
                } elseif (isset($map[$one])) {
                    $result += $map[$one];
                    $i++;
                } else {
                    // Invalid character encountered
                    return 0;
                }
            }

            return $result;
        }
    }
}
