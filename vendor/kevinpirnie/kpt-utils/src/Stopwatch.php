<?php

/**
 * Stopwatch
 *
 * A simple execution timer
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Stopwatch')) {

    /**
     * Stopwatch
     *
     * A modern PHP 8.2+ execution timer providing start/stop/lap/reset
     * functionality with millisecond precision and memory tracking.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Stopwatch
    {
        // -------------------------------------------------------------------------
        // Internal state
        // -------------------------------------------------------------------------

        /** @var float|null Start time in microseconds */
        private ?float $startTime = null;

        /** @var float|null Stop time in microseconds */
        private ?float $stopTime = null;

        /** @var array<array{label:string,time:float,elapsed:float,memory:int}> Lap records */
        private array $laps = [];

        /** @var int Memory usage at start in bytes */
        private int $startMemory = 0;

        // -------------------------------------------------------------------------
        // Factory
        // -------------------------------------------------------------------------

        /**
         * Create and immediately start a new Stopwatch.
         *
         * @return static
         */
        public static function start(): static
        {
            $instance = new static();
            $instance->begin();

            return $instance;
        }

        // -------------------------------------------------------------------------
        // Control
        // -------------------------------------------------------------------------

        /**
         * Start or restart the stopwatch.
         *
         * Clears any existing laps and stop time.
         *
         * @return static
         */
        public function begin(): static
        {
            $this->startTime   = microtime(true);
            $this->stopTime    = null;
            $this->laps        = [];
            $this->startMemory = memory_get_usage(true);

            return $this;
        }

        /**
         * Stop the stopwatch.
         *
         * @return static
         */
        public function stop(): static
        {
            if ($this->startTime !== null) {
                $this->stopTime = microtime(true);
            }

            return $this;
        }

        /**
         * Record a lap time with an optional label.
         *
         * The lap records the time elapsed since the stopwatch was started,
         * not since the previous lap.
         *
         * @param  string  $label  Optional label for the lap.
         * @return static
         */
        public function lap(string $label = ''): static
        {
            if ($this->startTime === null) {
                return $this;
            }

            $now          = microtime(true);
            $this->laps[] = [
                'label'   => $label !== '' ? $label : 'Lap ' . (count($this->laps) + 1),
                'time'    => $now,
                'elapsed' => ($now - $this->startTime) * 1000,
                'memory'  => memory_get_usage(true),
            ];

            return $this;
        }

        /**
         * Reset the stopwatch to its initial state.
         *
         * @return static
         */
        public function reset(): static
        {
            $this->startTime   = null;
            $this->stopTime    = null;
            $this->laps        = [];
            $this->startMemory = 0;

            return $this;
        }

        // -------------------------------------------------------------------------
        // Elapsed time
        // -------------------------------------------------------------------------

        /**
         * Get elapsed time in milliseconds.
         *
         * Returns time up to stop() if stopped, or time since start() if still running.
         * Returns 0.0 when the stopwatch has not been started.
         *
         * @param  int  $precision  Decimal places (default 3).
         * @return float
         */
        public function elapsed(int $precision = 3): float
        {
            if ($this->startTime === null) {
                return 0.0;
            }

            $end = $this->stopTime ?? microtime(true);

            return round(($end - $this->startTime) * 1000, $precision);
        }

        /**
         * Get elapsed time in seconds.
         *
         * @param  int  $precision  Decimal places (default 6).
         * @return float
         */
        public function elapsedSeconds(int $precision = 6): float
        {
            if ($this->startTime === null) {
                return 0.0;
            }

            $end = $this->stopTime ?? microtime(true);

            return round($end - $this->startTime, $precision);
        }

        /**
         * Get elapsed time as a human-readable string.
         *
         * Examples: '1.234 ms', '1.234 s', '1 m 2.345 s'
         *
         * @return string
         */
        public function elapsedHuman(): string
        {
            $ms = $this->elapsed(3);

            if ($ms < 1000) {
                return round($ms, 3) . ' ms';
            }

            $seconds = $ms / 1000;

            if ($seconds < 60) {
                return round($seconds, 3) . ' s';
            }

            $minutes = (int) floor($seconds / 60);
            $seconds = round($seconds - ($minutes * 60), 3);

            return $minutes . ' m ' . $seconds . ' s';
        }

        // -------------------------------------------------------------------------
        // Laps
        // -------------------------------------------------------------------------

        /**
         * Get all recorded laps.
         *
         * Each lap contains: label, time (microtime), elapsed (ms since start),
         * and memory (bytes).
         *
         * @return array<array{label:string,time:float,elapsed:float,memory:int}>
         */
        public function laps(): array
        {
            return $this->laps;
        }

        /**
         * Get the fastest lap by elapsed time.
         *
         * Returns null when no laps have been recorded.
         *
         * @return array|null
         */
        public function fastestLap(): ?array
        {
            if (empty($this->laps)) {
                return null;
            }

            return array_reduce(
                $this->laps,
                fn(?array $carry, array $lap): array => $carry === null || $lap['elapsed'] < $carry['elapsed']
                    ? $lap
                    : $carry,
                null
            );
        }

        /**
         * Get the slowest lap by elapsed time.
         *
         * Returns null when no laps have been recorded.
         *
         * @return array|null
         */
        public function slowestLap(): ?array
        {
            if (empty($this->laps)) {
                return null;
            }

            return array_reduce(
                $this->laps,
                fn(?array $carry, array $lap): array => $carry === null || $lap['elapsed'] > $carry['elapsed']
                    ? $lap
                    : $carry,
                null
            );
        }

        // -------------------------------------------------------------------------
        // Memory
        // -------------------------------------------------------------------------

        /**
         * Get current memory usage in bytes.
         *
         * @param  bool  $real  Use real memory allocation rather than emalloc usage.
         * @return int
         */
        public function memoryUsage(bool $real = true): int
        {
            return memory_get_usage($real);
        }

        /**
         * Get peak memory usage in bytes.
         *
         * @param  bool  $real
         * @return int
         */
        public function peakMemoryUsage(bool $real = true): int
        {
            return memory_get_peak_usage($real);
        }

        /**
         * Get memory consumed since the stopwatch was started in bytes.
         *
         * @return int
         */
        public function memoryDelta(): int
        {
            return memory_get_usage(true) - $this->startMemory;
        }

        /**
         * Get memory usage as a human-readable string.
         *
         * @param  bool  $peak  Use peak memory rather than current.
         * @return string
         */
        public function memoryHuman(bool $peak = false): string
        {
            $bytes = $peak ? $this->peakMemoryUsage() : $this->memoryUsage();

            return \KPT\Num::formatBytes($bytes);
        }

        // -------------------------------------------------------------------------
        // Convenience
        // -------------------------------------------------------------------------

        /**
         * Measure the execution time of a callable in milliseconds.
         *
         * @param  callable  $callback
         * @param  int       $precision
         * @return array{result:mixed,elapsed:float,memory:int}
         */
        public static function measure(callable $callback, int $precision = 3): array
        {
            $sw     = self::start();
            $result = $callback();
            $sw->stop();

            return [
                'result'  => $result,
                'elapsed' => $sw->elapsed($precision),
                'memory'  => $sw->memoryDelta(),
            ];
        }

        /**
         * Check whether the stopwatch is currently running.
         *
         * @return bool
         */
        public function isRunning(): bool
        {
            return $this->startTime !== null && $this->stopTime === null;
        }

        /**
         * Check whether the stopwatch has been started.
         *
         * @return bool
         */
        public function hasStarted(): bool
        {
            return $this->startTime !== null;
        }
    }
}
