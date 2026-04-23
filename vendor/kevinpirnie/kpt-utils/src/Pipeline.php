<?php

/**
 * Pipeline
 *
 * A simple pipeline for chaining callables
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Pipeline')) {

    /**
     * Pipeline
     *
     * A modern PHP 8.2+ pipeline for passing a value through a series of
     * callables.  Supports both array-based and fluent chained stage addition,
     * exception handling, and both returning and side-effect terminal operations.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Pipeline
    {
        // -------------------------------------------------------------------------
        // Internal state
        // -------------------------------------------------------------------------

        /** @var mixed The value being passed through the pipeline */
        private mixed $passable;

        /** @var array<callable> The stages to pass the value through */
        private array $stages = [];

        /** @var callable|null Exception handler */
        private mixed $exceptionHandler = null;

        // -------------------------------------------------------------------------
        // Factory
        // -------------------------------------------------------------------------

        /**
         * Begin a new pipeline with a given value.
         *
         * @param  mixed  $passable  The value to send through the pipeline.
         * @return static
         */
        public static function send(mixed $passable): static
        {
            $instance           = new static();
            $instance->passable = $passable;

            return $instance;
        }

        // -------------------------------------------------------------------------
        // Stage registration
        // -------------------------------------------------------------------------

        /**
         * Set the pipeline stages from an array of callables.
         *
         * Replaces any previously registered stages.
         *
         * @param  array<callable>  $stages
         * @return static
         */
        public function through(array $stages): static
        {
            $clone         = clone $this;
            $clone->stages = array_values($stages);

            return $clone;
        }

        /**
         * Add a single stage to the pipeline.
         *
         * Can be chained multiple times to build the pipeline fluently.
         *
         * @param  callable  $stage  fn(mixed $value): mixed
         * @return static
         */
        public function pipe(callable $stage): static
        {
            $clone           = clone $this;
            $clone->stages[] = $stage;

            return $clone;
        }

        /**
         * Register an exception handler for the pipeline.
         *
         * When any stage throws, the handler receives the exception and the
         * current value.  The handler's return value becomes the pipeline result.
         *
         * @param  callable  $handler  fn(\Throwable $e, mixed $value): mixed
         * @return static
         */
        public function catch(callable $handler): static
        {
            $clone                   = clone $this;
            $clone->exceptionHandler = $handler;

            return $clone;
        }

        // -------------------------------------------------------------------------
        // Terminal operations
        // -------------------------------------------------------------------------

        /**
         * Execute the pipeline and return the final value.
         *
         * Each stage receives the output of the previous stage.
         * When no stages are registered the original value is returned.
         *
         * @return mixed
         */
        public function thenReturn(): mixed
        {
            return $this->run($this->passable);
        }

        /**
         * Execute the pipeline and pass the result to a final callable.
         *
         * @param  callable  $destination  fn(mixed $value): mixed
         * @return mixed
         */
        public function then(callable $destination): mixed
        {
            $result = $this->run($this->passable);

            return $destination($result);
        }

        /**
         * Execute the pipeline for side effects only — return value is discarded.
         *
         * @param  callable|null  $destination  Optional final callable for side effects.
         * @return void
         */
        public function thenDo(?callable $destination = null): void
        {
            $result = $this->run($this->passable);

            if ($destination !== null) {
                $destination($result);
            }
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Run the value through all registered stages.
         *
         * @param  mixed  $value
         * @return mixed
         */
        private function run(mixed $value): mixed
        {
            try {
                foreach ($this->stages as $stage) {
                    $value = $stage($value);
                }

                return $value;
            } catch (\Throwable $e) {
                if ($this->exceptionHandler !== null) {
                    return ($this->exceptionHandler)($e, $value);
                }

                throw $e;
            }
        }
    }
}
