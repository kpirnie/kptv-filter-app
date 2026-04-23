<?php

/**
 * Paginator
 *
 * A flexible pagination utility
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Paginator')) {

    /**
     * Paginator
     *
     * A modern PHP 8.2+ pagination utility that works with arrays, iterators,
     * and KPT\Collection instances.  Provides numeric page data, URL generation
     * via a string template or callable, and a window of page links suitable
     * for rendering a pagination UI.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Paginator
    {
        // -------------------------------------------------------------------------
        // Internal state
        // -------------------------------------------------------------------------

        /** @var int Total number of items across all pages */
        private int $total;

        /** @var int Number of items per page */
        private int $perPage;

        /** @var int Current page number (1-based) */
        private int $currentPage;

        /** @var int Total number of pages */
        private int $totalPages;

        /** @var array Items for the current page */
        private array $items;

        /** @var string|callable|null URL template or callable for generating page URLs */
        private mixed $urlGenerator;

        // -------------------------------------------------------------------------
        // Factory
        // -------------------------------------------------------------------------

        /**
         * Create a Paginator from an array, iterator, or Collection.
         *
         * Slices the input to the current page automatically.
         *
         * @param  iterable  $items        All items to paginate.
         * @param  int       $perPage      Items per page (default 15).
         * @param  int       $currentPage  Current page number (default 1).
         * @param  string|callable|null  $urlGenerator
         *         String template with {page} placeholder, or callable fn(int $page): string.
         * @return static
         */
        public static function make(
            iterable $items,
            int $perPage = 15,
            int $currentPage = 1,
            string|callable|null $urlGenerator = null
        ): static {
            $instance = new static();

            // Normalise any iterable to an array
            $all = match (true) {
                $items instanceof \KPT\Collection => $items->toArray(),
                $items instanceof \Traversable    => iterator_to_array($items),
                default                           => (array) $items,
            };

            $instance->total        = count($all);
            $instance->perPage      = max(1, $perPage);
            $instance->totalPages   = (int) ceil($instance->total / $instance->perPage);
            $instance->currentPage  = max(1, min($currentPage, max(1, $instance->totalPages)));
            $instance->urlGenerator = $urlGenerator;

            // Slice the full dataset to the current page
            $offset         = ($instance->currentPage - 1) * $instance->perPage;
            $instance->items = array_slice($all, $offset, $instance->perPage);

            return $instance;
        }

        /**
         * Create a Paginator when you already have the current page's items
         * and only know the total count (e.g. database pagination).
         *
         * @param  iterable  $items        Items for the current page only.
         * @param  int       $total        Total number of items across all pages.
         * @param  int       $perPage      Items per page.
         * @param  int       $currentPage  Current page number.
         * @param  string|callable|null  $urlGenerator
         * @return static
         */
        public static function fromTotal(
            iterable $items,
            int $total,
            int $perPage = 15,
            int $currentPage = 1,
            string|callable|null $urlGenerator = null
        ): static {
            $instance = new static();

            $instance->total        = max(0, $total);
            $instance->perPage      = max(1, $perPage);
            $instance->totalPages   = (int) ceil($instance->total / $instance->perPage);
            $instance->currentPage  = max(1, min($currentPage, max(1, $instance->totalPages)));
            $instance->urlGenerator = $urlGenerator;

            $instance->items = match (true) {
                $items instanceof \KPT\Collection => $items->toArray(),
                $items instanceof \Traversable    => iterator_to_array($items),
                default                           => (array) $items,
            };

            return $instance;
        }

        // -------------------------------------------------------------------------
        // Page data
        // -------------------------------------------------------------------------

        /**
         * Get the items for the current page as a plain array.
         *
         * @return array
         */
        public function items(): array
        {
            return $this->items;
        }

        /**
         * Get the items for the current page as a Collection.
         *
         * @return \KPT\Collection
         */
        public function collection(): \KPT\Collection
        {
            return \KPT\Collection::make($this->items);
        }

        /**
         * Get the total number of items across all pages.
         *
         * @return int
         */
        public function total(): int
        {
            return $this->total;
        }

        /**
         * Get the number of items per page.
         *
         * @return int
         */
        public function perPage(): int
        {
            return $this->perPage;
        }

        /**
         * Get the current page number.
         *
         * @return int
         */
        public function currentPage(): int
        {
            return $this->currentPage;
        }

        /**
         * Get the total number of pages.
         *
         * @return int
         */
        public function totalPages(): int
        {
            return $this->totalPages;
        }

        /**
         * Get the 1-based index of the first item on the current page.
         *
         * @return int
         */
        public function firstItem(): int
        {
            return $this->total === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1;
        }

        /**
         * Get the 1-based index of the last item on the current page.
         *
         * @return int
         */
        public function lastItem(): int
        {
            return min($this->currentPage * $this->perPage, $this->total);
        }

        /**
         * Get the number of items on the current page.
         *
         * @return int
         */
        public function count(): int
        {
            return count($this->items);
        }

        // -------------------------------------------------------------------------
        // Page state
        // -------------------------------------------------------------------------

        /**
         * Check whether there are multiple pages.
         *
         * @return bool
         */
        public function hasPages(): bool
        {
            return $this->totalPages > 1;
        }

        /**
         * Check whether there is a previous page.
         *
         * @return bool
         */
        public function hasPreviousPage(): bool
        {
            return $this->currentPage > 1;
        }

        /**
         * Check whether there is a next page.
         *
         * @return bool
         */
        public function hasNextPage(): bool
        {
            return $this->currentPage < $this->totalPages;
        }

        /**
         * Check whether the current page is the first page.
         *
         * @return bool
         */
        public function onFirstPage(): bool
        {
            return $this->currentPage === 1;
        }

        /**
         * Check whether the current page is the last page.
         *
         * @return bool
         */
        public function onLastPage(): bool
        {
            return $this->currentPage === $this->totalPages;
        }

        // -------------------------------------------------------------------------
        // URL generation
        // -------------------------------------------------------------------------

        /**
         * Generate the URL for a given page number.
         *
         * Returns null when no URL generator is configured.
         *
         * @param  int  $page
         * @return string|null
         */
        public function url(int $page): ?string
        {
            if ($this->urlGenerator === null) {
                return null;
            }

            $page = max(1, min($page, $this->totalPages));

            if (is_callable($this->urlGenerator)) {
                return ($this->urlGenerator)($page);
            }

            return str_replace('{page}', (string) $page, $this->urlGenerator);
        }

        /**
         * Generate the URL for the previous page.
         *
         * Returns null when on the first page or no generator is configured.
         *
         * @return string|null
         */
        public function previousPageUrl(): ?string
        {
            return $this->hasPreviousPage() ? $this->url($this->currentPage - 1) : null;
        }

        /**
         * Generate the URL for the next page.
         *
         * Returns null when on the last page or no generator is configured.
         *
         * @return string|null
         */
        public function nextPageUrl(): ?string
        {
            return $this->hasNextPage() ? $this->url($this->currentPage + 1) : null;
        }

        /**
         * Generate the URL for the first page.
         *
         * @return string|null
         */
        public function firstPageUrl(): ?string
        {
            return $this->url(1);
        }

        /**
         * Generate the URL for the last page.
         *
         * @return string|null
         */
        public function lastPageUrl(): ?string
        {
            return $this->url($this->totalPages);
        }

        // -------------------------------------------------------------------------
        // Page window
        // -------------------------------------------------------------------------

        /**
         * Return a window of page numbers around the current page.
         *
         * Suitable for rendering a pagination UI with ellipsis gaps.
         * Always includes the first and last page; fills the window from
         * the pages surrounding the current page.
         *
         * Example with currentPage=5, totalPages=10, window=2:
         * [1, '...', 3, 4, 5, 6, 7, '...', 10]
         *
         * @param  int  $window  Number of pages on each side of the current page.
         * @return array         Mix of int page numbers and '...' gap markers.
         */
        public function pageWindow(int $window = 2): array
        {
            if ($this->totalPages <= 1) {
                return $this->totalPages === 1 ? [1] : [];
            }

            $start = max(2, $this->currentPage - $window);
            $end   = min($this->totalPages - 1, $this->currentPage + $window);

            $pages = [1];

            // Left gap
            if ($start > 2) {
                $pages[] = '...';
            }

            // Window pages
            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }

            // Right gap
            if ($end < $this->totalPages - 1) {
                $pages[] = '...';
            }

            $pages[] = $this->totalPages;

            return $pages;
        }

        /**
         * Return the full page window as an array of structured link data.
         *
         * Each entry contains: page (int|null), url (string|null),
         * active (bool), and gap (bool).
         *
         * @param  int  $window
         * @return array<array{page:int|null,url:string|null,active:bool,gap:bool}>
         */
        public function links(int $window = 2): array
        {
            return array_map(function (mixed $page): array {
                if ($page === '...') {
                    return ['page' => null, 'url' => null, 'active' => false, 'gap' => true];
                }

                return [
                    'page'   => $page,
                    'url'    => $this->url($page),
                    'active' => $page === $this->currentPage,
                    'gap'    => false,
                ];
            }, $this->pageWindow($window));
        }

        // -------------------------------------------------------------------------
        // Serialization
        // -------------------------------------------------------------------------

        /**
         * Return the paginator state as an associative array.
         *
         * Useful for passing pagination metadata to API responses or templates.
         *
         * @param  int  $window
         * @return array
         */
        public function toArray(int $window = 2): array
        {
            return [
                'total'             => $this->total,
                'per_page'          => $this->perPage,
                'current_page'      => $this->currentPage,
                'total_pages'       => $this->totalPages,
                'first_item'        => $this->firstItem(),
                'last_item'         => $this->lastItem(),
                'has_pages'         => $this->hasPages(),
                'has_previous_page' => $this->hasPreviousPage(),
                'has_next_page'     => $this->hasNextPage(),
                'first_page_url'    => $this->firstPageUrl(),
                'last_page_url'     => $this->lastPageUrl(),
                'previous_page_url' => $this->previousPageUrl(),
                'next_page_url'     => $this->nextPageUrl(),
                'links'             => $this->links($window),
            ];
        }

        /**
         * Return the paginator state as a JSON string.
         *
         * @param  int  $flags   json_encode flags.
         * @param  int  $window
         * @return string
         */
        public function toJson(int $flags = 0, int $window = 2): string
        {
            return json_encode($this->toArray($window), $flags);
        }
    }
}
