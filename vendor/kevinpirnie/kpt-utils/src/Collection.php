<?php

/**
 * Collection
 *
 * A fluent, immutable, lazy-evaluated array wrapper
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Collection')) {

    /**
     * Collection
     *
     * A modern PHP 8.2+ immutable, lazy-evaluated fluent array wrapper
     * implementing Countable, IteratorAggregate, and ArrayAccess.
     *
     * Operations are pipelined as generator closures and not executed until
     * a terminal operation materializes the collection.  Terminal operations
     * are: toArray(), toJson(), count(), sort(), sortBy(), reverse(),
     * unique(), sum(), avg(), min(), max(), and chunk().
     *
     * Short-circuit terminal operations — first(), last(), contains(), each()
     * — consume only as many items as needed without materializing the full set.
     *
     * All transformation methods return a new Collection instance — the
     * original is never modified.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Collection implements \Countable, \IteratorAggregate, \ArrayAccess
    {
        // -------------------------------------------------------------------------
        // Internal state
        // -------------------------------------------------------------------------

        /**
         * Eagerly-stored items — non-null only at the start of a pipeline
         * or after materialization.
         *
         * @var array|null
         */
        private ?array $eager = null;

        /**
         * Lazy pipeline — a closure that returns a Generator when invoked.
         * Non-null when at least one lazy operation has been chained.
         *
         * @var \Closure|null
         */
        private ?\Closure $pipeline = null;

        // -------------------------------------------------------------------------
        // Construction
        // -------------------------------------------------------------------------

        /**
         * Private constructor — use Collection::make() to instantiate.
         *
         * @param  array|\Closure  $source  Eager array or lazy generator factory.
         */
        private function __construct(array|\Closure $source)
        {
            if ($source instanceof \Closure) {
                $this->pipeline = $source;
            } else {
                $this->eager = $source;
            }
        }

        // -------------------------------------------------------------------------
        // Factory
        // -------------------------------------------------------------------------

        /**
         * Create a new Collection from an array or iterable.
         *
         * The source is stored eagerly — laziness begins with the first
         * chained transformation.
         *
         * @param  iterable  $items
         * @return static
         */
        public static function make(iterable $items = []): static
        {
            $array = match (true) {
                $items instanceof static      => $items->materialize(),
                $items instanceof \Traversable => iterator_to_array($items),
                default                        => (array) $items,
            };

            return new static($array);
        }

        // -------------------------------------------------------------------------
        // Lazy transformations — each wraps the previous pipeline in a generator
        // -------------------------------------------------------------------------

        /**
         * Filter items through a callback.
         *
         * Lazy — executes only when the collection is consumed.
         * When no callback is provided, removes all falsy values.
         *
         * @param  callable|null  $callback  fn(mixed $value, mixed $key): bool
         * @return static
         */
        public function filter(?callable $callback = null): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $callback): \Generator {
                foreach ($source() as $key => $item) {
                    $pass = $callback !== null ? $callback($item, $key) : (bool) $item;

                    if ($pass) {
                        yield $key => $item;
                    }
                }
            });
        }

        /**
         * Apply a callback to every item.
         *
         * Lazy — executes only when the collection is consumed.
         *
         * @param  callable  $callback  fn(mixed $value, mixed $key): mixed
         * @return static
         */
        public function map(callable $callback): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $callback): \Generator {
                foreach ($source() as $key => $item) {
                    yield $key => $callback($item, $key);
                }
            });
        }

        /**
         * Map over items and flatten the result by one level.
         *
         * Lazy — executes only when the collection is consumed.
         *
         * @param  callable  $callback  fn(mixed $value, mixed $key): iterable
         * @return static
         */
        public function flatMap(callable $callback): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $callback): \Generator {
                foreach ($source() as $key => $item) {
                    yield from $callback($item, $key);
                }
            });
        }

        /**
         * Map over items, re-keying the result via the callback.
         *
         * The callback must return a single-element array of [key => value].
         * Lazy — executes only when the collection is consumed.
         *
         * @param  callable  $callback  fn(mixed $value, mixed $key): array
         * @return static
         */
        public function mapWithKeys(callable $callback): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $callback): \Generator {
                foreach ($source() as $key => $item) {
                    foreach ($callback($item, $key) as $newKey => $newValue) {
                        yield $newKey => $newValue;
                    }
                }
            });
        }

        /**
         * Pluck values for a given field, optionally keyed by another field.
         *
         * Lazy — executes only when the collection is consumed.
         *
         * @param  string       $value  Field to pluck as the value.
         * @param  string|null  $key    Field to use as the key (optional).
         * @return static
         */
        public function pluck(string $value, ?string $key = null): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $value, $key): \Generator {
                foreach ($source() as $item) {
                    $val = is_array($item) ? ($item[$value] ?? null) : ($item->$value ?? null);

                    if ($key !== null) {
                        $k = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
                        yield $k => $val;
                    } else {
                        yield $val;
                    }
                }
            });
        }

        /**
         * Take the first N items.
         *
         * Lazy — stops consuming the source after N items.
         *
         * @param  int  $limit
         * @return static
         */
        public function take(int $limit): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $limit): \Generator {
                $count = 0;

                foreach ($source() as $key => $item) {
                    if ($count >= $limit) {
                        break;
                    }

                    yield $key => $item;
                    $count++;
                }
            });
        }

        /**
         * Skip the first N items.
         *
         * Lazy — discards the first N items from the source.
         *
         * @param  int  $offset
         * @return static
         */
        public function skip(int $offset): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $offset): \Generator {
                $count = 0;

                foreach ($source() as $key => $item) {
                    if ($count++ < $offset) {
                        continue;
                    }

                    yield $key => $item;
                }
            });
        }

        /**
         * Filter items where a field equals a given value.
         *
         * Lazy — executes only when the collection is consumed.
         *
         * @param  string  $key
         * @param  mixed   $value
         * @param  bool    $strict  Use strict comparison.
         * @return static
         */
        public function where(string $key, mixed $value, bool $strict = false): static
        {
            return $this->filter(function (mixed $item) use ($key, $value, $strict): bool {
                $field = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);

                return $strict ? $field === $value : $field == $value;
            });
        }

        /**
         * Flatten nested arrays to a single level or to a given depth.
         *
         * Lazy — executes only when the collection is consumed.
         *
         * @param  float  $depth  Depth limit (INF for full flattening).
         * @return static
         */
        public function flatten(float $depth = INF): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $depth): \Generator {
                yield from self::flattenGenerator($source(), $depth);
            });
        }

        /**
         * Merge additional items or another Collection.
         *
         * Lazy — chains both sources into a single generator.
         *
         * @param  array|static  $items
         * @return static
         */
        public function merge(array|self $items): static
        {
            $source = $this->pipelineFactory();
            $other  = $items instanceof static ? $items->pipelineFactory() : fn() => yield from $items;

            return new static(function () use ($source, $other): \Generator {
                yield from $source();
                yield from $other();
            });
        }

        /**
         * Append one or more values.
         *
         * Lazy — appends to the end of the pipeline.
         *
         * @param  mixed  ...$values
         * @return static
         */
        public function push(mixed ...$values): static
        {
            return $this->merge($values);
        }

        /**
         * Prepend a value with an optional key.
         *
         * Lazy — prepends to the start of the pipeline.
         *
         * @param  mixed  $value
         * @param  mixed  $key
         * @return static
         */
        public function prepend(mixed $value, mixed $key = null): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $value, $key): \Generator {
                if ($key !== null) {
                    yield $key => $value;
                } else {
                    yield $value;
                }

                yield from $source();
            });
        }

        /**
         * Return a new Collection with keys reset to sequential integers.
         *
         * Lazy — re-indexes keys during consumption.
         *
         * @return static
         */
        public function values(): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source): \Generator {
                foreach ($source() as $item) {
                    yield $item;
                }
            });
        }

        /**
         * Return a new Collection containing only the keys.
         *
         * Lazy — yields keys during consumption.
         *
         * @return static
         */
        public function keys(): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source): \Generator {
                foreach ($source() as $key => $item) {
                    yield $key;
                }
            });
        }

        /**
         * Return only the items whose keys are in the given list.
         *
         * Lazy — filters by key during consumption.
         *
         * @param  array  $keys
         * @return static
         */
        public function only(array $keys): static
        {
            $source = $this->pipelineFactory();
            $lookup = array_flip($keys);

            return new static(function () use ($source, $lookup): \Generator {
                foreach ($source() as $key => $item) {
                    if (array_key_exists($key, $lookup)) {
                        yield $key => $item;
                    }
                }
            });
        }

        /**
         * Return all items except those whose keys are in the given list.
         *
         * Lazy — filters by key during consumption.
         *
         * @param  array  $keys
         * @return static
         */
        public function except(array $keys): static
        {
            $source = $this->pipelineFactory();
            $lookup = array_flip($keys);

            return new static(function () use ($source, $lookup): \Generator {
                foreach ($source() as $key => $item) {
                    if (! array_key_exists($key, $lookup)) {
                        yield $key => $item;
                    }
                }
            });
        }

        /**
         * Zip the collection together with one or more arrays.
         *
         * Lazy — pairs corresponding elements during consumption.
         * Result length is bounded by the shortest input.
         *
         * @param  array  ...$arrays
         * @return static
         */
        public function zip(array ...$arrays): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $arrays): \Generator {
                $iterators = array_map(fn(array $a) => (function () use ($a): \Generator {
                    yield from $a;
                })(), $arrays);

                foreach ($source() as $item) {
                    $row = [$item];

                    foreach ($iterators as $iterator) {
                        if (! $iterator->valid()) {
                            return;
                        }

                        $row[] = $iterator->current();
                        $iterator->next();
                    }

                    yield new static($row);
                }
            });
        }

        // -------------------------------------------------------------------------
        // Eager transformations — must materialize to operate
        // -------------------------------------------------------------------------

        /**
         * Reduce the collection to a single value.
         *
         * Terminal — consumes the full pipeline.
         *
         * @param  callable  $callback  fn(mixed $carry, mixed $item): mixed
         * @param  mixed     $initial
         * @return mixed
         */
        public function reduce(callable $callback, mixed $initial = null): mixed
        {
            $result = $initial;

            foreach ($this->getIterator() as $item) {
                $result = $callback($result, $item);
            }

            return $result;
        }

        /**
         * Chunk the collection into smaller Collections of a given size.
         *
         * Terminal — materializes before chunking.
         * Returns a Collection of Collections.
         *
         * @param  int  $size
         * @return static
         */
        public function chunk(int $size): static
        {
            return new static(array_map(
                fn(array $chunk): static => new static($chunk),
                array_chunk($this->materialize(), max(1, $size), true)
            ));
        }

        /**
         * Group items into Collections keyed by a field or callback result.
         *
         * Terminal — materializes before grouping.
         * Returns a Collection of Collections.
         *
         * @param  string|callable  $key
         * @return static
         */
        public function groupBy(string|callable $key): static
        {
            $groups = [];

            foreach ($this->getIterator() as $item) {
                $groupKey = is_callable($key)
                    ? $key($item)
                    : (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null));

                $groups[$groupKey][] = $item;
            }

            return new static(array_map(fn(array $group): static => new static($group), $groups));
        }

        /**
         * Sort the collection using an optional callback.
         *
         * Terminal — materializes before sorting.
         *
         * @param  callable|null  $callback
         * @return static
         */
        public function sort(?callable $callback = null): static
        {
            $items = $this->materialize();

            $callback ? usort($items, $callback) : sort($items);

            return new static($items);
        }

        /**
         * Sort the collection by a field value.
         *
         * Terminal — materializes before sorting.
         *
         * @param  string  $key
         * @param  bool    $ascending
         * @return static
         */
        public function sortBy(string $key, bool $ascending = true): static
        {
            $items = $this->materialize();

            usort($items, function (mixed $a, mixed $b) use ($key): int {
                $valA = is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null);
                $valB = is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null);

                return is_numeric($valA) && is_numeric($valB)
                    ? $valA <=> $valB
                    : strcasecmp((string) $valA, (string) $valB);
            });

            return new static($ascending ? $items : array_reverse($items));
        }

        /**
         * Reverse the order of items.
         *
         * Terminal — materializes before reversing.
         *
         * @return static
         */
        public function reverse(): static
        {
            return new static(array_reverse($this->materialize(), true));
        }

        /**
         * Return only unique items, optionally de-duplicated by field.
         *
         * Terminal — materializes to track seen values.
         *
         * @param  string|null  $key
         * @return static
         */
        public function unique(?string $key = null): static
        {
            if ($key === null) {
                return new static(array_unique($this->materialize()));
            }

            $seen  = [];
            $items = [];

            foreach ($this->getIterator() as $k => $item) {
                $val = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);

                if (! in_array($val, $seen, true)) {
                    $seen[]    = $val;
                    $items[$k] = $item;
                }
            }

            return new static($items);
        }

        // -------------------------------------------------------------------------
        // Short-circuit terminal operations
        // -------------------------------------------------------------------------

        /**
         * Get the first item, or the first item matching a callback.
         *
         * Short-circuit — stops consuming the pipeline as soon as a match is found.
         *
         * @param  callable|null  $callback
         * @param  mixed          $default
         * @return mixed
         */
        public function first(?callable $callback = null, mixed $default = null): mixed
        {
            foreach ($this->getIterator() as $key => $item) {
                if ($callback === null || $callback($item, $key)) {
                    return $item;
                }
            }

            return $default;
        }

        /**
         * Get the last item, or the last item matching a callback.
         *
         * Consumes the full pipeline — cannot short-circuit on an unknown-length source.
         *
         * @param  callable|null  $callback
         * @param  mixed          $default
         * @return mixed
         */
        public function last(?callable $callback = null, mixed $default = null): mixed
        {
            $match = $default;

            foreach ($this->getIterator() as $key => $item) {
                if ($callback === null || $callback($item, $key)) {
                    $match = $item;
                }
            }

            return $match;
        }

        /**
         * Check whether the collection contains a value or a matching item.
         *
         * Short-circuit — stops consuming the pipeline as soon as a match is found.
         *
         * @param  mixed        $value
         * @param  string|null  $key
         * @return bool
         */
        public function contains(mixed $value, ?string $key = null): bool
        {
            if (is_callable($value)) {
                foreach ($this->getIterator() as $k => $item) {
                    if ($value($item, $k)) {
                        return true;
                    }
                }

                return false;
            }

            if ($key !== null) {
                foreach ($this->getIterator() as $item) {
                    $field = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);

                    if ($field === $value) {
                        return true;
                    }
                }

                return false;
            }

            foreach ($this->getIterator() as $item) {
                if ($item === $value) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Apply a callback to each item for side effects.
         *
         * Short-circuit — returning false from the callback stops iteration.
         * Returns the same Collection to allow continued chaining.
         *
         * @param  callable  $callback  fn(mixed $value, mixed $key): mixed
         * @return static
         */
        public function each(callable $callback): static
        {
            foreach ($this->getIterator() as $key => $item) {
                if ($callback($item, $key) === false) {
                    break;
                }
            }

            return $this;
        }

        /**
         * Pass each item through a callback for side effects without interrupting the pipeline.
         *
         * Unlike each(), returning false does not stop iteration.
         * Lazy — executes only when the collection is consumed.
         *
         * @param  callable  $callback  fn(mixed $value, mixed $key): mixed
         * @return static
         */
        public function tap(callable $callback): static
        {
            $source = $this->pipelineFactory();

            return new static(function () use ($source, $callback): \Generator {
                foreach ($source() as $key => $item) {
                    $callback($item, $key);
                    yield $key => $item;
                }
            });
        }

        /**
         * Pass the entire Collection into a callable and return the result.
         *
         * Breaks out of the fluent chain — the callable can return anything.
         * Terminal — the Collection is passed as-is, lazy or not.
         *
         * @param  callable  $callback  fn(static $collection): mixed
         * @return mixed
         */
        public function pipe(callable $callback): mixed
        {
            return $callback($this);
        }

        /**
         * Check whether the collection is empty.
         *
         * Short-circuit — checks only the first item.
         *
         * @return bool
         */
        public function isEmpty(): bool
        {
            foreach ($this->getIterator() as $_) {
                return false;
            }

            return true;
        }

        /**
         * Check whether the collection is not empty.
         *
         * @return bool
         */
        public function isNotEmpty(): bool
        {
            return ! $this->isEmpty();
        }

        // -------------------------------------------------------------------------
        // Aggregation — terminal, consumes the full pipeline
        // -------------------------------------------------------------------------

        /**
         * Sum the values, or the values of a given field.
         *
         * @param  string|callable|null  $key
         * @return int|float
         */
        public function sum(string|callable|null $key = null): int|float
        {
            $total = 0;

            foreach ($this->getIterator() as $item) {
                $total += is_callable($key)
                    ? $key($item)
                    : ($key !== null ? (is_array($item) ? ($item[$key] ?? 0) : ($item->$key ?? 0)) : $item);
            }

            return $total;
        }

        /**
         * Average the values, or the values of a given field.
         *
         * Returns 0.0 when the collection is empty.
         *
         * @param  string|callable|null  $key
         * @return float
         */
        public function avg(string|callable|null $key = null): float
        {
            $total = 0;
            $count = 0;

            foreach ($this->getIterator() as $item) {
                $total += is_callable($key)
                    ? $key($item)
                    : ($key !== null ? (is_array($item) ? ($item[$key] ?? 0) : ($item->$key ?? 0)) : $item);
                $count++;
            }

            return $count > 0 ? $total / $count : 0.0;
        }

        /**
         * Get the minimum value, or the minimum value of a given field.
         *
         * @param  string|callable|null  $key
         * @return mixed
         */
        public function min(string|callable|null $key = null): mixed
        {
            return min($this->resolveValues($key));
        }

        /**
         * Get the maximum value, or the maximum value of a given field.
         *
         * @param  string|callable|null  $key
         * @return mixed
         */
        public function max(string|callable|null $key = null): mixed
        {
            return max($this->resolveValues($key));
        }

        // -------------------------------------------------------------------------
        // Conversion — terminal
        // -------------------------------------------------------------------------

        /**
         * Materialize and return the collection as a plain array.
         *
         * Nested Collections are recursively converted.
         *
         * @return array
         */
        public function toArray(): array
        {
            return array_map(
                fn(mixed $item): mixed => $item instanceof self ? $item->toArray() : $item,
                $this->materialize()
            );
        }

        /**
         * Materialize and return the collection as a JSON string.
         *
         * @param  int  $flags
         * @return string
         */
        public function toJson(int $flags = 0): string
        {
            return json_encode($this->toArray(), $flags);
        }

        // -------------------------------------------------------------------------
        // Countable — terminal
        // -------------------------------------------------------------------------

        /**
         * Return the number of items — materializes the full pipeline.
         *
         * @return int
         */
        public function count(): int
        {
            return count($this->materialize());
        }

        // -------------------------------------------------------------------------
        // IteratorAggregate
        // -------------------------------------------------------------------------

        /**
         * Return an iterator.
         *
         * When a pipeline is active, returns the generator directly so foreach
         * loops consume lazily without materializing.
         * When eager, wraps the array in an ArrayIterator.
         *
         * @return \Traversable
         */
        public function getIterator(): \Traversable
        {
            if ($this->pipeline !== null) {
                return ($this->pipeline)();
            }

            return new \ArrayIterator($this->eager ?? []);
        }

        // -------------------------------------------------------------------------
        // ArrayAccess — read-only (immutable)
        // -------------------------------------------------------------------------

        /** @param  mixed  $offset */
        public function offsetExists(mixed $offset): bool
        {
            return isset($this->materialize()[$offset]);
        }

        /** @param  mixed  $offset */
        public function offsetGet(mixed $offset): mixed
        {
            return $this->materialize()[$offset];
        }

        /**
         * @param  mixed  $offset
         * @param  mixed  $value
         * @throws \LogicException
         */
        public function offsetSet(mixed $offset, mixed $value): void
        {
            throw new \LogicException('Collection is immutable — use push() or merge() to add items.');
        }

        /**
         * @param  mixed  $offset
         * @throws \LogicException
         */
        public function offsetUnset(mixed $offset): void
        {
            throw new \LogicException('Collection is immutable — use filter() or except() to remove items.');
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Materialize the pipeline into an array, caching the result.
         *
         * Once materialized, the eager array is stored and the pipeline
         * is cleared so subsequent terminal calls don't re-run the generators.
         *
         * @return array
         */
        private function materialize(): array
        {
            if ($this->eager !== null) {
                return $this->eager;
            }

            // Run the generator pipeline and cache the result
            $this->eager    = iterator_to_array($this->getIterator(), false);
            $this->pipeline = null;

            return $this->eager;
        }

        /**
         * Return a closure that produces a fresh generator from the current source.
         *
         * When the source is already eager, wraps it in a generator so lazy
         * operations have a consistent interface to build on.
         *
         * @return \Closure(): \Generator
         */
        private function pipelineFactory(): \Closure
        {
            if ($this->pipeline !== null) {
                return $this->pipeline;
            }

            $items = $this->eager ?? [];

            return function () use ($items): \Generator {
                yield from $items;
            };
        }

        /**
         * Resolve a flat list of values from the pipeline using a key or callable.
         *
         * @param  string|callable|null  $key
         * @return array
         */
        private function resolveValues(string|callable|null $key): array
        {
            $values = [];

            foreach ($this->getIterator() as $item) {
                $values[] = is_callable($key)
                    ? $key($item)
                    : ($key !== null ? (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null)) : $item);
            }

            return $values;
        }

        /**
         * Recursively flatten a generator to a given depth.
         *
         * @param  iterable  $source
         * @param  float     $depth
         * @return \Generator
         */
        private static function flattenGenerator(iterable $source, float $depth): \Generator
        {
            foreach ($source as $item) {
                if (is_iterable($item) && $depth > 0) {
                    yield from self::flattenGenerator($item, $depth - 1);
                } else {
                    yield $item;
                }
            }
        }
    }
}
