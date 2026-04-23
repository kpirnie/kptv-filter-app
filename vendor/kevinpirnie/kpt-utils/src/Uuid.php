<?php

/**
 * UUID Functions
 *
 * This is our primary UUID utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Uuid')) {

    /**
     * Uuid
     *
     * A modern PHP 8.2+ UUID utility supporting generation of v1, v3, v4, v5,
     * and v7 UUIDs and validation of v1–v7.  Output is available in standard
     * hyphenated, compact (no hyphens), and raw binary formats.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Uuid
    {
        // -------------------------------------------------------------------------
        // Output format constants
        // -------------------------------------------------------------------------

        /** Standard hyphenated format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx */
        public const FORMAT_STANDARD = 'standard';

        /** Compact format without hyphens: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
        public const FORMAT_COMPACT  = 'compact';

        /** Raw 16-byte binary string */
        public const FORMAT_BINARY   = 'binary';

        // -------------------------------------------------------------------------
        // Namespace constants (RFC 4122 Appendix C)
        // -------------------------------------------------------------------------

        /** DNS namespace UUID */
        public const NS_DNS  = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

        /** URL namespace UUID */
        public const NS_URL  = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

        /** OID namespace UUID */
        public const NS_OID  = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';

        /** X.500 namespace UUID */
        public const NS_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

        // -------------------------------------------------------------------------
        // Generation
        // -------------------------------------------------------------------------

        /**
         * Generate a version 1 UUID (time-based, MAC address).
         *
         * Uses the current system time and a random node identifier.
         * The node is randomized per call since actual MAC addresses are
         * not reliably available in PHP without system-specific calls.
         *
         * @param  string  $format  OUTPUT format constant (default FORMAT_STANDARD).
         * @return string
         */
        public static function v1(string $format = self::FORMAT_STANDARD): string
        {
            // 100-nanosecond intervals since 15 Oct 1582 (UUID epoch)
            $uuidEpoch  = 122192928000000000;
            $now        = (int) (microtime(true) * 10000000) + $uuidEpoch;

            $timeLow  = $now & 0xFFFFFFFF;
            $timeMid  = ($now >> 32) & 0xFFFF;
            $timeHigh = (($now >> 48) & 0x0FFF) | 0x1000; // version 1

            // Random clock sequence (14 bits) with RFC 4122 variant bits
            $clockSeq = random_int(0, 0x3FFF) | 0x8000;

            // Random 48-bit node (multicast bit set to indicate non-hardware address)
            $node = random_bytes(6);
            $node[0] = chr(ord($node[0]) | 0x01);

            $binary = pack('NnnNn', $timeLow, $timeMid, $timeHigh, $clockSeq, 0)
                . $node;

            // Repack properly — clock_seq_hi and low are separate fields
            $binary = pack('N', $timeLow)
                . pack('n', $timeMid)
                . pack('n', $timeHigh)
                . pack('n', $clockSeq)
                . $node;

            return self::format($binary, $format);
        }

        /**
         * Generate a version 3 UUID (name-based, MD5).
         *
         * @param  string  $namespace  A UUID string to use as the namespace.
         * @param  string  $name       The name to hash within the namespace.
         * @param  string  $format     Output format constant.
         * @return string
         *
         * @throws \InvalidArgumentException When the namespace UUID is invalid.
         */
        public static function v3(
            string $namespace,
            string $name,
            string $format = self::FORMAT_STANDARD
        ): string {
            return self::nameBasedUuid($namespace, $name, 3, $format);
        }

        /**
         * Generate a version 4 UUID (random).
         *
         * Uses PHP's cryptographically secure random bytes.
         *
         * @param  string  $format  Output format constant.
         * @return string
         */
        public static function v4(string $format = self::FORMAT_STANDARD): string
        {
            $bytes = random_bytes(16);

            // Set version 4
            $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);

            // Set RFC 4122 variant (10xx)
            $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

            return self::format($bytes, $format);
        }

        /**
         * Generate a version 5 UUID (name-based, SHA-1).
         *
         * @param  string  $namespace  A UUID string to use as the namespace.
         * @param  string  $name       The name to hash within the namespace.
         * @param  string  $format     Output format constant.
         * @return string
         *
         * @throws \InvalidArgumentException When the namespace UUID is invalid.
         */
        public static function v5(
            string $namespace,
            string $name,
            string $format = self::FORMAT_STANDARD
        ): string {
            return self::nameBasedUuid($namespace, $name, 5, $format);
        }

        /**
         * Generate a version 7 UUID (time-ordered, random).
         *
         * UUIDv7 embeds a Unix timestamp in milliseconds in the most significant
         * bits, making it monotonically sortable — ideal for database primary keys.
         *
         * @param  string  $format  Output format constant.
         * @return string
         */
        public static function v7(string $format = self::FORMAT_STANDARD): string
        {
            // 48-bit Unix timestamp in milliseconds
            $ms = (int) (microtime(true) * 1000);

            // Pack timestamp into 6 bytes (48 bits)
            $timeBytes = pack('J', $ms);            // 8 bytes big-endian
            $timeBytes = substr($timeBytes, 2);     // take the lower 6 bytes

            // 10 random bytes for the remaining fields
            $rand = random_bytes(10);

            // Combine: 48-bit timestamp + 10 random bytes = 16 bytes
            $bytes = $timeBytes . $rand;

            // Set version 7 in bits 12–15 of byte 6
            $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);

            // Set RFC 4122 variant (10xx) in bits 6–7 of byte 8
            $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

            return self::format($bytes, $format);
        }

        // -------------------------------------------------------------------------
        // Validation
        // -------------------------------------------------------------------------

        /**
         * Validate a UUID string (v1–v7), case-insensitive.
         *
         * Accepts standard hyphenated format only.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function isValid(mixed $value): bool
        {
            return (bool) preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-7][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                (string) $value
            );
        }

        /**
         * Validate a UUID of a specific version (1–7).
         *
         * @param  mixed  $value
         * @param  int    $version  Expected version number.
         * @return bool
         */
        public static function isVersion(mixed $value, int $version): bool
        {
            if ($version < 1 || $version > 7) {
                return false;
            }

            return (bool) preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-' . $version . '[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                (string) $value
            );
        }

        /**
         * Validate a compact UUID (no hyphens, 32 hex characters).
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function isCompact(mixed $value): bool
        {
            return (bool) preg_match('/^[0-9a-f]{32}$/i', (string) $value);
        }

        // -------------------------------------------------------------------------
        // Conversion
        // -------------------------------------------------------------------------

        /**
         * Convert a standard hyphenated UUID to compact format.
         *
         * @param  string  $uuid
         * @return string  Compact UUID, or empty string on invalid input.
         */
        public static function toCompact(string $uuid): string
        {
            if (! self::isValid($uuid)) {
                return '';
            }

            return str_replace('-', '', strtolower($uuid));
        }

        /**
         * Convert a standard hyphenated UUID to raw binary (16 bytes).
         *
         * @param  string  $uuid
         * @return string  Binary string, or empty string on invalid input.
         */
        public static function toBinary(string $uuid): string
        {
            if (! self::isValid($uuid)) {
                return '';
            }

            return (string) hex2bin(str_replace('-', '', $uuid));
        }

        /**
         * Convert a compact UUID to standard hyphenated format.
         *
         * @param  string  $compact
         * @return string  Standard UUID, or empty string on invalid input.
         */
        public static function fromCompact(string $compact): string
        {
            if (! self::isCompact($compact)) {
                return '';
            }

            $hex = strtolower($compact);

            return implode('-', [
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12),
            ]);
        }

        /**
         * Convert raw binary (16 bytes) to standard hyphenated UUID.
         *
         * @param  string  $binary
         * @return string  Standard UUID, or empty string on invalid input.
         */
        public static function fromBinary(string $binary): string
        {
            if (strlen($binary) !== 16) {
                return '';
            }

            return self::fromCompact(bin2hex($binary));
        }

        /**
         * Extract the version number from a UUID string.
         *
         * @param  string  $uuid
         * @return int|null  Version number, or null when the UUID is invalid.
         */
        public static function version(string $uuid): ?int
        {
            if (! self::isValid($uuid)) {
                return null;
            }

            return (int) $uuid[14];
        }

        /**
         * Extract the Unix timestamp (in milliseconds) from a v7 UUID.
         *
         * Returns null for non-v7 UUIDs.
         *
         * @param  string  $uuid
         * @return int|null
         */
        public static function timestamp(string $uuid): ?int
        {
            if (! self::isVersion($uuid, 7)) {
                return null;
            }

            // First 12 hex chars (48 bits) encode the millisecond timestamp
            $hex = str_replace('-', '', $uuid);
            $ms  = hexdec(substr($hex, 0, 12));

            return (int) $ms;
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Generate a name-based UUID (v3 or v5).
         *
         * v3 uses MD5; v5 uses SHA-1.  Both follow RFC 4122 Section 4.3.
         *
         * @param  string  $namespace
         * @param  string  $name
         * @param  int     $version    3 or 5.
         * @param  string  $format
         * @return string
         *
         * @throws \InvalidArgumentException
         */
        private static function nameBasedUuid(
            string $namespace,
            string $name,
            int $version,
            string $format
        ): string {
            if (! self::isValid($namespace)) {
                throw new \InvalidArgumentException('Invalid namespace UUID: ' . $namespace);
            }

            // Convert namespace UUID to binary
            $nsBin = (string) hex2bin(str_replace('-', '', $namespace));

            // Hash namespace + name
            $hash = $version === 3
                ? md5($nsBin . $name, true)
                : sha1($nsBin . $name, true);

            // Truncate SHA-1 to 16 bytes
            $bytes = substr($hash, 0, 16);

            // Set version bits
            $bytes[6] = chr((ord($bytes[6]) & 0x0F) | ($version === 3 ? 0x30 : 0x50));

            // Set RFC 4122 variant (10xx)
            $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

            return self::format($bytes, $format);
        }

        /**
         * Format a 16-byte binary UUID string into the requested output format.
         *
         * @param  string  $bytes   Raw 16-byte binary string.
         * @param  string  $format  One of the FORMAT_* constants.
         * @return string
         */
        private static function format(string $bytes, string $format): string
        {
            return match ($format) {
                self::FORMAT_BINARY  => $bytes,
                self::FORMAT_COMPACT => bin2hex($bytes),
                default              => vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)),
            };
        }
    }
}
