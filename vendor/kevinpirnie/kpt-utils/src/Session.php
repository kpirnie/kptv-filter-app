<?php

/**
 * Session Functions
 *
 * This is our primary session management class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Session')) {

    /**
     * Session
     *
     * A modern PHP 8.2+ session management utility providing lifecycle control,
     * typed data access, and flash messaging.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Session
    {
        // -------------------------------------------------------------------------
        // Internal constants
        // -------------------------------------------------------------------------

        /** $_SESSION key used to store flash data */
        private const FLASH_KEY = '__kpt_flash__';

        // -------------------------------------------------------------------------
        // Lifecycle
        // -------------------------------------------------------------------------

        /**
         * Start the session if it is not already active.
         *
         * Registers a shutdown function to write and close the session,
         * preventing lock contention on long-running requests.
         *
         * @param  array  $options  Options passed directly to session_start().
         * @return bool             True when the session is active after the call.
         */
        public static function start(array $options = []): bool
        {
            if (self::isActive()) {
                return true;
            }

            $started = session_start($options);

            if ($started) {
                // Release the session lock as soon as the request finishes
                register_shutdown_function(function (): void {
                    if (self::isActive()) {
                        session_write_close();
                    }
                });
            }

            return $started;
        }

        /**
         * Write and close the session without destroying it.
         *
         * Frees the session lock while preserving session data, allowing
         * other requests from the same client to proceed immediately.
         *
         * @return void
         */
        public static function close(): void
        {
            if (self::isActive()) {
                session_write_close();
            }
        }

        /**
         * Destroy the session entirely.
         *
         * Clears all session data, deletes the session cookie when a name
         * is configured, and calls session_destroy().
         *
         * @return bool
         */
        public static function destroy(): bool
        {
            if (! self::isActive()) {
                return false;
            }

            // Unset all session variables before destroying
            $_SESSION = [];

            // Expire the session cookie on the client
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            return session_destroy();
        }

        /**
         * Regenerate the session ID.
         *
         * Should be called after privilege changes (e.g. login) to prevent
         * session fixation attacks.
         *
         * @param  bool  $deleteOld  Delete the old session file (default true).
         * @return bool
         */
        public static function regenerate(bool $deleteOld = true): bool
        {
            if (! self::isActive()) {
                return false;
            }

            return session_regenerate_id($deleteOld);
        }

        /**
         * Check whether the session is currently active.
         *
         * @return bool
         */
        public static function isActive(): bool
        {
            return session_status() === PHP_SESSION_ACTIVE;
        }

        // -------------------------------------------------------------------------
        // ID management
        // -------------------------------------------------------------------------

        /**
         * Get the current session ID.
         *
         * @return string  Session ID, or empty string when no session is active.
         */
        public static function getId(): string
        {
            return session_id() ?: '';
        }

        /**
         * Set the session ID before the session is started.
         *
         * Has no effect when the session is already active.
         *
         * @param  string  $id  The session ID to use.
         * @return void
         */
        public static function setId(string $id): void
        {
            if (! self::isActive()) {
                session_id($id);
            }
        }

        // -------------------------------------------------------------------------
        // Data management
        // -------------------------------------------------------------------------

        /**
         * Set a session value.
         *
         * Supports dot-notation keys for nested access (e.g. 'user.role').
         *
         * @param  string  $key
         * @param  mixed   $value
         * @return void
         */
        public static function set(string $key, mixed $value): void
        {
            self::ensureActive();
            self::setNested($_SESSION, $key, $value);
        }

        /**
         * Get a session value.
         *
         * Supports dot-notation keys for nested access (e.g. 'user.role').
         *
         * @param  string  $key
         * @param  mixed   $default  Returned when the key does not exist.
         * @return mixed
         */
        public static function get(string $key, mixed $default = null): mixed
        {
            self::ensureActive();
            return self::getNested($_SESSION, $key, $default);
        }

        /**
         * Check whether a session key exists and is not null.
         *
         * Supports dot-notation keys for nested access.
         *
         * @param  string  $key
         * @return bool
         */
        public static function has(string $key): bool
        {
            self::ensureActive();
            return self::getNested($_SESSION, $key) !== null;
        }

        /**
         * Remove a session key.
         *
         * Supports dot-notation keys for nested access.
         *
         * @param  string  $key
         * @return void
         */
        public static function remove(string $key): void
        {
            self::ensureActive();
            self::removeNested($_SESSION, $key);
        }

        /**
         * Clear all session data.
         *
         * @return void
         */
        public static function clear(): void
        {
            self::ensureActive();
            $_SESSION = [];
        }

        /**
         * Retrieve all session data as an array.
         *
         * @return array
         */
        public static function all(): array
        {
            self::ensureActive();
            return $_SESSION;
        }

        // -------------------------------------------------------------------------
        // Flash messaging
        // -------------------------------------------------------------------------

        /**
         * Store a flash value that survives exactly one subsequent retrieval.
         *
         * Useful for passing messages across redirects.
         *
         * @param  string  $key
         * @param  mixed   $value
         * @return void
         */
        public static function flash(string $key, mixed $value): void
        {
            self::ensureActive();
            $_SESSION[self::FLASH_KEY][$key] = $value;
        }

        /**
         * Retrieve a flash value and remove it from the session.
         *
         * @param  string  $key
         * @param  mixed   $default  Returned when the key does not exist.
         * @return mixed
         */
        public static function getFlash(string $key, mixed $default = null): mixed
        {
            self::ensureActive();

            $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;

            // Remove immediately after retrieval — flash survives one read only
            unset($_SESSION[self::FLASH_KEY][$key]);

            return $value;
        }

        /**
         * Check whether a flash value exists.
         *
         * @param  string  $key
         * @return bool
         */
        public static function hasFlash(string $key): bool
        {
            self::ensureActive();
            return isset($_SESSION[self::FLASH_KEY][$key]);
        }

        /**
         * Retrieve all pending flash values and clear them.
         *
         * @return array
         */
        public static function allFlash(): array
        {
            self::ensureActive();

            $flash = $_SESSION[self::FLASH_KEY] ?? [];
            unset($_SESSION[self::FLASH_KEY]);

            return $flash;
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Start the session automatically when a data operation is attempted
         * without an active session.
         *
         * @return void
         */
        private static function ensureActive(): void
        {
            if (! self::isActive()) {
                self::start();
            }
        }

        /**
         * Read a value from a nested array using dot-notation.
         *
         * @param  array   $data
         * @param  string  $key
         * @param  mixed   $default
         * @return mixed
         */
        private static function getNested(array $data, string $key, mixed $default = null): mixed
        {
            // Fast path — no dot means top-level key only
            if (! str_contains($key, '.')) {
                return $data[$key] ?? $default;
            }

            foreach (explode('.', $key) as $segment) {
                if (! is_array($data) || ! array_key_exists($segment, $data)) {
                    return $default;
                }

                $data = $data[$segment];
            }

            return $data;
        }

        /**
         * Write a value into a nested array using dot-notation.
         *
         * @param  array   &$data
         * @param  string  $key
         * @param  mixed   $value
         * @return void
         */
        private static function setNested(array &$data, string $key, mixed $value): void
        {
            // Fast path — no dot means top-level key only
            if (! str_contains($key, '.')) {
                $data[$key] = $value;
                return;
            }

            $segments = explode('.', $key);
            $current  = &$data;

            foreach ($segments as $segment) {
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }

                $current = &$current[$segment];
            }

            $current = $value;
        }

        /**
         * Remove a key from a nested array using dot-notation.
         *
         * @param  array   &$data
         * @param  string  $key
         * @return void
         */
        private static function removeNested(array &$data, string $key): void
        {
            // Fast path — no dot means top-level key only
            if (! str_contains($key, '.')) {
                unset($data[$key]);
                return;
            }

            $segments = explode('.', $key);
            $last     = array_pop($segments);
            $current  = &$data;

            foreach ($segments as $segment) {
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    return;
                }

                $current = &$current[$segment];
            }

            unset($current[$last]);
        }
    }
}
