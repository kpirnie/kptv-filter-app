<?php

/**
 * DateTime Functions
 *
 * This is our primary date and time utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\DateTime')) {

    /**
     * DateTime
     *
     * A modern PHP 8.2+ date and time utility class providing human-readable
     * time differences and WordPress-compatible time constants.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class DateTime
    {
        // -------------------------------------------------------------------------
        // Time constants
        // -------------------------------------------------------------------------

        /** Seconds in one minute */
        public const MINUTE_IN_SECONDS = 60;

        /** Seconds in one hour */
        public const HOUR_IN_SECONDS = self::MINUTE_IN_SECONDS * 60;

        /** Seconds in one day */
        public const DAY_IN_SECONDS = self::HOUR_IN_SECONDS * 24;

        /** Seconds in one week */
        public const WEEK_IN_SECONDS = self::DAY_IN_SECONDS * 7;

        /** Seconds in one month (30 days) */
        public const MONTH_IN_SECONDS = self::DAY_IN_SECONDS * 30;

        /** Seconds in one year (365 days) */
        public const YEAR_IN_SECONDS = self::DAY_IN_SECONDS * 365;

        // -------------------------------------------------------------------------
        // Formatting
        // -------------------------------------------------------------------------

        /**
         * Get the current datetime in a given format.
         *
         * @param  string  $format  Output format (default 'Y-m-d H:i:s').
         * @return string
         */
        public static function now(string $format = 'Y-m-d H:i:s'): string
        {
            return (new \DateTimeImmutable())->format($format);
        }

        /**
         * Return a human-readable "time ago" string for a given datetime.
         *
         * Resolves differences from seconds through years, with singular/plural
         * labels.  Falls back to a formatted date string for differences beyond
         * one year.  Returns an empty string when the input cannot be parsed.
         *
         * @param  string  $datetime   Any datetime string parseable by strtotime().
         * @param  string  $fallback   Date format used when diff exceeds one year.
         * @return string
         */
        public static function timeAgo(string $datetime, string $fallback = 'M j, Y'): string
        {
            $time = strtotime($datetime);

            // strtotime returns false for unparseable input
            if ($time === false) {
                return '';
            }

            $diff = time() - $time;

            // Seconds
            if ($diff < self::MINUTE_IN_SECONDS) {
                $n = $diff;
                return $n . ' ' . ($n === 1 ? 'Second' : 'Seconds') . ' Ago';
            }

            // Minutes
            if ($diff < self::HOUR_IN_SECONDS) {
                $n = (int) floor($diff / self::MINUTE_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Minute' : 'Minutes') . ' Ago';
            }

            // Hours
            if ($diff < self::DAY_IN_SECONDS) {
                $n = (int) floor($diff / self::HOUR_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Hour' : 'Hours') . ' Ago';
            }

            // Days
            if ($diff < self::WEEK_IN_SECONDS) {
                $n = (int) floor($diff / self::DAY_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Day' : 'Days') . ' Ago';
            }

            // Weeks
            if ($diff < self::MONTH_IN_SECONDS) {
                $n = (int) floor($diff / self::WEEK_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Week' : 'Weeks') . ' Ago';
            }

            // Months
            if ($diff < self::YEAR_IN_SECONDS) {
                $n = (int) floor($diff / self::MONTH_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Month' : 'Months') . ' Ago';
            }

            // Beyond a year — fall back to a formatted date string
            return (new \DateTimeImmutable('@' . $time))->format($fallback);
        }

        /**
         * Return a human-readable difference between two datetime strings.
         *
         * @param  string  $from     Start datetime string.
         * @param  string  $to       End datetime string (default 'now').
         * @param  string  $fallback Date format used when diff exceeds one year.
         * @return string
         */
        public static function humanDiff(string $from, string $to = 'now', string $fallback = 'M j, Y'): string
        {
            $fromTime = strtotime($from);
            $toTime   = strtotime($to);

            if ($fromTime === false || $toTime === false) {
                return '';
            }

            $diff = abs($toTime - $fromTime);

            if ($diff < self::MINUTE_IN_SECONDS) {
                $n = $diff;
                return $n . ' ' . ($n === 1 ? 'Second' : 'Seconds');
            }

            if ($diff < self::HOUR_IN_SECONDS) {
                $n = (int) floor($diff / self::MINUTE_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Minute' : 'Minutes');
            }

            if ($diff < self::DAY_IN_SECONDS) {
                $n = (int) floor($diff / self::HOUR_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Hour' : 'Hours');
            }

            if ($diff < self::WEEK_IN_SECONDS) {
                $n = (int) floor($diff / self::DAY_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Day' : 'Days');
            }

            if ($diff < self::MONTH_IN_SECONDS) {
                $n = (int) floor($diff / self::WEEK_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Week' : 'Weeks');
            }

            if ($diff < self::YEAR_IN_SECONDS) {
                $n = (int) floor($diff / self::MONTH_IN_SECONDS);
                return $n . ' ' . ($n === 1 ? 'Month' : 'Months');
            }

            return (new \DateTimeImmutable('@' . $fromTime))->format($fallback);
        }

        /**
         * Check whether a datetime string falls on a weekend.
         *
         * @param  string  $datetime
         * @return bool
         */
        public static function isWeekend(string $datetime): bool
        {
            $time = strtotime($datetime);

            if ($time === false) {
                return false;
            }

            return in_array((int) date('N', $time), [6, 7], true);
        }

        /**
         * Check whether a datetime string falls on a weekday.
         *
         * @param  string  $datetime
         * @return bool
         */
        public static function isWeekday(string $datetime): bool
        {
            return ! self::isWeekend($datetime);
        }

        /**
         * Get the start of the day (midnight) for a given datetime string.
         *
         * @param  string  $datetime
         * @param  string  $format   Output format (default 'Y-m-d H:i:s').
         * @return string            Formatted datetime, or empty string on failure.
         */
        public static function startOfDay(string $datetime, string $format = 'Y-m-d H:i:s'): string
        {
            $time = strtotime($datetime);

            if ($time === false) {
                return '';
            }

            return (new \DateTimeImmutable('@' . $time))
                ->setTime(0, 0, 0)
                ->format($format);
        }

        /**
         * Get the end of the day (23:59:59) for a given datetime string.
         *
         * @param  string  $datetime
         * @param  string  $format   Output format (default 'Y-m-d H:i:s').
         * @return string            Formatted datetime, or empty string on failure.
         */
        public static function endOfDay(string $datetime, string $format = 'Y-m-d H:i:s'): string
        {
            $time = strtotime($datetime);

            if ($time === false) {
                return '';
            }

            return (new \DateTimeImmutable('@' . $time))
                ->setTime(23, 59, 59)
                ->format($format);
        }

        // -------------------------------------------------------------------------
        // Arithmetic
        // -------------------------------------------------------------------------

        /**
         * Add a number of days to a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $days
         * @param  string  $format   Output format (default 'Y-m-d H:i:s').
         * @return string            Formatted datetime, or empty string on failure.
         */
        public static function addDays(string $datetime, int $days, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, ($days >= 0 ? '+' : '') . $days . ' days', $format);
        }

        /**
         * Subtract a number of days from a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $days
         * @param  string  $format
         * @return string
         */
        public static function subDays(string $datetime, int $days, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, '-' . abs($days) . ' days', $format);
        }

        /**
         * Add a number of months to a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $months
         * @param  string  $format
         * @return string
         */
        public static function addMonths(string $datetime, int $months, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, ($months >= 0 ? '+' : '') . $months . ' months', $format);
        }

        /**
         * Subtract a number of months from a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $months
         * @param  string  $format
         * @return string
         */
        public static function subMonths(string $datetime, int $months, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, '-' . abs($months) . ' months', $format);
        }

        /**
         * Add a number of years to a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $years
         * @param  string  $format
         * @return string
         */
        public static function addYears(string $datetime, int $years, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, ($years >= 0 ? '+' : '') . $years . ' years', $format);
        }

        /**
         * Subtract a number of years from a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $years
         * @param  string  $format
         * @return string
         */
        public static function subYears(string $datetime, int $years, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, '-' . abs($years) . ' years', $format);
        }

        /**
         * Add a number of hours to a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $hours
         * @param  string  $format
         * @return string
         */
        public static function addHours(string $datetime, int $hours, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, ($hours >= 0 ? '+' : '') . $hours . ' hours', $format);
        }

        /**
         * Subtract a number of hours from a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $hours
         * @param  string  $format
         * @return string
         */
        public static function subHours(string $datetime, int $hours, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, '-' . abs($hours) . ' hours', $format);
        }

        /**
         * Add a number of minutes to a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $minutes
         * @param  string  $format
         * @return string
         */
        public static function addMinutes(string $datetime, int $minutes, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, ($minutes >= 0 ? '+' : '') . $minutes . ' minutes', $format);
        }

        /**
         * Subtract a number of minutes from a datetime string.
         *
         * @param  string  $datetime
         * @param  int     $minutes
         * @param  string  $format
         * @return string
         */
        public static function subMinutes(string $datetime, int $minutes, string $format = 'Y-m-d H:i:s'): string
        {
            return self::modify($datetime, '-' . abs($minutes) . ' minutes', $format);
        }

        // -------------------------------------------------------------------------
        // Comparison
        // -------------------------------------------------------------------------

        /**
         * Check whether a datetime falls between two other datetimes (inclusive).
         *
         * @param  string  $datetime
         * @param  string  $start
         * @param  string  $end
         * @return bool
         */
        public static function isBetween(string $datetime, string $start, string $end): bool
        {
            $time      = strtotime($datetime);
            $startTime = strtotime($start);
            $endTime   = strtotime($end);

            if ($time === false || $startTime === false || $endTime === false) {
                return false;
            }

            return $time >= $startTime && $time <= $endTime;
        }

        /**
         * Convert a datetime string to a DateTimeImmutable instance.
         *
         * @param  string  $datetime
         * @return \DateTimeImmutable|null  Null when the string cannot be parsed.
         */
        public static function toDateTimeImmutable(string $datetime): ?\DateTimeImmutable
        {
            $time = strtotime($datetime);

            if ($time === false) {
                return null;
            }

            return new \DateTimeImmutable('@' . $time);
        }

        /**
         * Apply a strtotime-compatible modifier to a datetime string.
         *
         * Shared by all arithmetic methods to avoid duplication.
         *
         * @param  string  $datetime
         * @param  string  $modifier
         * @param  string  $format
         * @return string
         */
        private static function modify(string $datetime, string $modifier, string $format): string
        {
            $time = strtotime($datetime);

            if ($time === false) {
                return '';
            }

            $modified = (new \DateTimeImmutable('@' . $time))->modify($modifier);

            return $modified !== false ? $modified->format($format) : '';
        }
    }
}
