<?php

/**
 * Retry
 *
 * A retry utility with exponential backoff and jitter
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Retry')) {

    /**
     * Retry
     *
     * A modern PHP 8.2+ retry utility supporting fixed and exponential backoff
     * with optional jitter, specific exception class filtering, and a per-attempt
     * callback for logging or reacting between retries.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Retry
    {
        // -------------------------------------------------------------------------
        // Internal state
        // -------------------------------------------------------------------------

        /** @var callable The operation to retry */
        private mixed $operation;

        /** @var int Maximum number of attempts */
        private int $maxAttempts = 3;

        /** @var int Base delay between attempts in milliseconds */
        private int $delayMs = 100;

        /** @var float Exponential backoff multiplier (1.0 = fixed delay) */
        private float $multiplier = 2.0;

        /** @var int Maximum delay cap in milliseconds */
        private int $maxDelayMs = 10000;

        /** @var bool Whether to add random jitter to the delay */
        private bool $jitter = true;

        /** @var array<class-string<\Throwable>> Exception classes to retry on — empty means any */
        private array $retryOn = [];

        /** @var array<class-string<\Throwable>> Exception classes to never retry on */
        private array $dontRetryOn = [];

        /** @var callable|null Called after each failed attempt */
        private mixed $onRetry = null;

        // -------------------------------------------------------------------------
        // Factory
        // -------------------------------------------------------------------------

        /**
         * Begin a new Retry for the given operation.
         *
         * @param  callable  $operation  fn(): mixed
         * @return static
         */
        public static function operation(callable $operation): static
        {
            $instance            = new static();
            $instance->operation = $operation;

            return $instance;
        }

        // -------------------------------------------------------------------------
        // Configuration — fluent
        // -------------------------------------------------------------------------

        /**
         * Set the maximum number of attempts (including the first try).
         *
         * @param  int  $attempts
         * @return static
         */
        public function times(int $attempts): static
        {
            $clone              = clone $this;
            $clone->maxAttempts = max(1, $attempts);

            return $clone;
        }

        /**
         * Set the base delay between attempts in milliseconds.
         *
         * @param  int  $milliseconds
         * @return static
         */
        public function waitMs(int $milliseconds): static
        {
            $clone          = clone $this;
            $clone->delayMs = max(0, $milliseconds);

            return $clone;
        }

        /**
         * Enable exponential backoff with a configurable multiplier.
         *
         * Each attempt's delay is: min(baseDelay * multiplier^attempt, maxDelay)
         * Set multiplier to 1.0 for a fixed delay.
         *
         * @param  float  $multiplier  Backoff multiplier (default 2.0).
         * @param  int    $maxDelayMs  Maximum delay cap in milliseconds.
         * @return static
         */
        public function exponential(float $multiplier = 2.0, int $maxDelayMs = 10000): static
        {
            $clone             = clone $this;
            $clone->multiplier = max(1.0, $multiplier);
            $clone->maxDelayMs = max(0, $maxDelayMs);

            return $clone;
        }

        /**
         * Enable or disable random jitter on the delay.
         *
         * When enabled, a random fraction of the delay is added to prevent
         * thundering herd problems when many clients retry simultaneously.
         *
         * @param  bool  $enabled
         * @return static
         */
        public function withJitter(bool $enabled = true): static
        {
            $clone         = clone $this;
            $clone->jitter = $enabled;

            return $clone;
        }

        /**
         * Retry only when the thrown exception is one of the given classes.
         *
         * Non-matching exceptions are re-thrown immediately without retrying.
         *
         * @param  class-string<\Throwable>  ...$exceptions
         * @return static
         */
        public function retryOn(string ...$exceptions): static
        {
            $clone            = clone $this;
            $clone->retryOn   = $exceptions;

            return $clone;
        }

        /**
         * Never retry when the thrown exception is one of the given classes.
         *
         * Takes precedence over retryOn() when both match.
         *
         * @param  class-string<\Throwable>  ...$exceptions
         * @return static
         */
        public function dontRetryOn(string ...$exceptions): static
        {
            $clone                = clone $this;
            $clone->dontRetryOn   = $exceptions;

            return $clone;
        }

        /**
         * Register a callback to be called after each failed attempt.
         *
         * Receives the exception, the attempt number, and the delay in ms
         * before the next attempt.
         *
         * @param  callable  $callback  fn(\Throwable $e, int $attempt, int $delayMs): void
         * @return static
         */
        public function onRetry(callable $callback): static
        {
            $clone          = clone $this;
            $clone->onRetry = $callback;

            return $clone;
        }

        // -------------------------------------------------------------------------
        // Execution
        // -------------------------------------------------------------------------

        /**
         * Execute the operation, retrying on failure.
         *
         * @return mixed  The return value of the operation on success.
         *
         * @throws \Throwable  The last exception when all attempts are exhausted.
         */
        public function run(): mixed
        {
            $attempt   = 0;
            $lastError = null;

            while ($attempt < $this->maxAttempts) {
                try {
                    return ($this->operation)();
                } catch (\Throwable $e) {
                    $lastError = $e;

                    // Never retry on explicitly excluded exception classes
                    foreach ($this->dontRetryOn as $class) {
                        if ($e instanceof $class) {
                            throw $e;
                        }
                    }

                    // When retryOn is set, only retry on matching exception classes
                    if (! empty($this->retryOn)) {
                        $shouldRetry = false;

                        foreach ($this->retryOn as $class) {
                            if ($e instanceof $class) {
                                $shouldRetry = true;
                                break;
                            }
                        }

                        if (! $shouldRetry) {
                            throw $e;
                        }
                    }

                    $attempt++;

                    // No delay or callback needed after the final attempt
                    if ($attempt >= $this->maxAttempts) {
                        break;
                    }

                    $delay = $this->calculateDelay($attempt);

                    if ($this->onRetry !== null) {
                        ($this->onRetry)($e, $attempt, $delay);
                    }

                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }
                }
            }

            throw $lastError;
        }

        /**
         * Execute the operation and return a default value on failure.
         *
         * Unlike run(), this never throws — it returns $default when all
         * attempts are exhausted.
         *
         * @param  mixed  $default  Returned when all attempts fail.
         * @return mixed
         */
        public function runOrDefault(mixed $default = null): mixed
        {
            try {
                return $this->run();
            } catch (\Throwable) {
                return $default;
            }
        }

        /**
         * Execute the operation and return null on failure.
         *
         * Convenience wrapper around runOrDefault(null).
         *
         * @return mixed
         */
        public function runOrNull(): mixed
        {
            return $this->runOrDefault(null);
        }

        // -------------------------------------------------------------------------
        // Static convenience
        // -------------------------------------------------------------------------

        /**
         * Quickly retry a callable with default settings.
         *
         * @param  callable  $operation
         * @param  int       $times    Maximum attempts (default 3).
         * @param  int       $delayMs  Base delay in milliseconds (default 100).
         * @return mixed
         *
         * @throws \Throwable
         */
        public static function attempt(callable $operation, int $times = 3, int $delayMs = 100): mixed
        {
            return self::operation($operation)
                ->times($times)
                ->waitMs($delayMs)
                ->run();
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Calculate the delay for a given attempt number.
         *
         * Applies exponential backoff, caps at maxDelayMs, and optionally
         * adds jitter to spread retries from concurrent callers.
         *
         * @param  int  $attempt  Current attempt number (1-based).
         * @return int            Delay in milliseconds.
         */
        private function calculateDelay(int $attempt): int
        {
            // Exponential backoff: baseDelay * multiplier^(attempt-1)
            $delay = (int) min(
                $this->delayMs * pow($this->multiplier, $attempt - 1),
                $this->maxDelayMs
            );

            if ($this->jitter && $delay > 0) {
                // Add up to 25% random jitter to spread concurrent retries
                $delay += random_int(0, (int) ($delay * 0.25));
            }

            return $delay;
        }
    }
}
