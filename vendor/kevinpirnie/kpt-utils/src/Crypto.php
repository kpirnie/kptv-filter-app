<?php

/**
 * Crypto Functions
 *
 * This is our primary crypto class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Crypto')) {

    /**
     * Crypto
     *
     * A modern PHP 8.2+ cryptography utility providing authenticated encryption
     * via AES-256-GCM, HKDF key derivation, HMAC hashing, timing-safe comparison,
     * and cryptographically secure random string generation.
     *
     * Encryption payload format (before base64): IV[12] . ciphertext . tag[16]
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Crypto
    {
        // -------------------------------------------------------------------------
        // Internal constants
        // -------------------------------------------------------------------------

        /** GCM provides both confidentiality and integrity in a single pass */
        private const CIPHER    = 'aes-256-gcm';

        /** Derived key length in bytes (256 bits) */
        private const KEY_BYTES = 32;

        /** 96-bit IV is the NIST-recommended size for GCM */
        private const IV_BYTES  = 12;

        /** 128-bit authentication tag */
        private const TAG_BYTES = 16;

        // -------------------------------------------------------------------------
        // Encryption / Decryption
        // -------------------------------------------------------------------------

        /**
         * Encrypt a string using AES-256-GCM.
         *
         * A unique IV is generated for every call. The IV and GCM authentication
         * tag are embedded in the returned payload so nothing extra needs to be
         * stored alongside the ciphertext.
         *
         * @param  string  $value  Plaintext to encrypt.
         * @param  string  $key    Passphrase or raw key material.
         * @param  string  $salt   Optional salt for HKDF key derivation.
         * @return string          Base64-encoded payload, or empty string on failure.
         *
         * @throws \RuntimeException When the openssl extension is unavailable.
         */
        public static function encrypt(string $value, string $key, string $salt = ''): string
        {
            if (! extension_loaded('openssl')) {
                throw new \RuntimeException('The openssl extension is required for encryption.');
            }

            // Derive a fixed-length 256-bit key from any-length passphrase
            $derivedKey = hash_hkdf('sha256', $key, self::KEY_BYTES, 'kpt-encrypt', $salt);

            // Fresh random IV on every call — never reuse a nonce with GCM
            $iv = random_bytes(self::IV_BYTES);

            $tag        = '';
            $ciphertext = openssl_encrypt(
                $value,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                self::TAG_BYTES
            );

            if ($ciphertext === false) {
                return '';
            }

            // Pack IV + ciphertext + tag into a single self-contained payload
            return base64_encode($iv . $ciphertext . $tag);
        }

        /**
         * Decrypt a payload produced by self::encrypt().
         *
         * GCM authentication is verified automatically; any tampered payload
         * returns an empty string rather than corrupt or attacker-controlled data.
         *
         * @param  string  $value  Base64-encoded payload from self::encrypt().
         * @param  string  $key    Passphrase used during encryption.
         * @param  string  $salt   Salt used during encryption.
         * @return string          Decrypted plaintext, or empty string on failure.
         *
         * @throws \RuntimeException When the openssl extension is unavailable.
         */
        public static function decrypt(string $value, string $key, string $salt = ''): string
        {
            if (! extension_loaded('openssl')) {
                throw new \RuntimeException('The openssl extension is required for decryption.');
            }

            $raw = base64_decode($value, strict: true);

            // Minimum viable payload: IV + 1 byte of ciphertext + tag
            if ($raw === false || strlen($raw) < self::IV_BYTES + self::TAG_BYTES + 1) {
                return '';
            }

            // Re-derive the same key — salt must match what was used during encryption
            $derivedKey = hash_hkdf('sha256', $key, self::KEY_BYTES, 'kpt-encrypt', $salt);

            // Unpack the three components from the raw payload
            $iv         = substr($raw, 0, self::IV_BYTES);
            $tag        = substr($raw, -self::TAG_BYTES);
            $ciphertext = substr($raw, self::IV_BYTES, -self::TAG_BYTES);

            $plaintext = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            // openssl_decrypt returns false when tag verification fails
            return $plaintext !== false ? $plaintext : '';
        }

        /**
         * Encrypt a string using a human-provided passphrase.
         *
         * Argon2id stretches the passphrase into a high-entropy key before
         * handing off to AES-256-GCM.  Use this when $key is user-supplied;
         * use encrypt() when $key is already machine-generated key material.
         *
         * Payload format (before base64): argonSalt[16] . IV[12] . ciphertext . tag[16]
         *
         * @param  string  $value       Plaintext to encrypt.
         * @param  string  $passphrase  Human-provided passphrase.
         * @return string               Base64-encoded payload, or empty string on failure.
         *
         * @throws \RuntimeException When openssl or sodium is unavailable.
         */
        public static function encryptWithPassphrase(string $value, string $passphrase): string
        {
            if (! extension_loaded('openssl') || ! extension_loaded('sodium')) {
                throw new \RuntimeException('The openssl and sodium extensions are required for passphrase encryption');
            }

            // Random Argon2id salt — stored in the payload so decrypt() can re-derive the same key
            $argonSalt  = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

            // Argon2id key stretching — deliberately slow and memory-hard against brute-force
            $derivedKey = sodium_crypto_pwhash(
                self::KEY_BYTES,
                $passphrase,
                $argonSalt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );

            // Fresh IV for every call
            $iv = random_bytes(self::IV_BYTES);

            $tag        = '';
            $ciphertext = openssl_encrypt(
                $value,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                self::TAG_BYTES
            );

            if ($ciphertext === false) {
                return '';
            }

            // Wipe the derived key from memory before returning
            sodium_memzero($derivedKey);

            return base64_encode($argonSalt . $iv . $ciphertext . $tag);
        }

        /**
         * Decrypt a payload produced by self::encryptWithPassphrase().
         *
         * @param  string  $value       Base64-encoded payload.
         * @param  string  $passphrase  Passphrase used during encryption.
         * @return string               Decrypted plaintext, or empty string on failure.
         *
         * @throws \RuntimeException When openssl or sodium is unavailable.
         */
        public static function decryptWithPassphrase(string $value, string $passphrase): string
        {
            if (! extension_loaded('openssl') || ! extension_loaded('sodium')) {
                throw new \RuntimeException('The openssl and sodium extensions are required for passphrase decryption');
            }

            $raw = base64_decode($value, strict: true);

            // Minimum: argonSalt + IV + 1 byte ciphertext + tag
            $minLength = SODIUM_CRYPTO_PWHASH_SALTBYTES + self::IV_BYTES + self::TAG_BYTES + 1;

            if ($raw === false || strlen($raw) < $minLength) {
                return '';
            }

            // Unpack the three components — argonSalt must come first to re-derive the key
            $argonSalt  = substr($raw, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $iv         = substr($raw, SODIUM_CRYPTO_PWHASH_SALTBYTES, self::IV_BYTES);
            $tag        = substr($raw, -self::TAG_BYTES);
            $ciphertext = substr($raw, SODIUM_CRYPTO_PWHASH_SALTBYTES + self::IV_BYTES, -self::TAG_BYTES);

            $derivedKey = sodium_crypto_pwhash(
                self::KEY_BYTES,
                $passphrase,
                $argonSalt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );

            $plaintext = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            // Wipe the derived key from memory regardless of outcome
            sodium_memzero($derivedKey);

            return $plaintext !== false ? $plaintext : '';
        }

        // -------------------------------------------------------------------------
        // Hashing
        // -------------------------------------------------------------------------

        /**
         * Hash a value using the given algorithm.
         *
         * @param  string  $value
         * @param  string  $algo  Any algorithm accepted by hash() — see hash_algos().
         * @param  bool    $raw   Return raw binary output instead of lowercase hex.
         * @return string
         */
        public static function hash(string $value, string $algo = 'sha256', bool $raw = false): string
        {
            return hash($algo, $value, $raw);
        }

        /**
         * Generate an HMAC for a value.
         *
         * @param  string  $value
         * @param  string  $key
         * @param  string  $algo  Any algorithm accepted by hash_hmac().
         * @param  bool    $raw   Return raw binary output instead of lowercase hex.
         * @return string
         */
        public static function hmac(string $value, string $key, string $algo = 'sha256', bool $raw = false): string
        {
            return hash_hmac($algo, $value, $key, $raw);
        }

        /**
         * Timing-safe string comparison.
         *
         * Prevents timing side-channel attacks when comparing hashes or tokens.
         *
         * @param  string  $a
         * @param  string  $b
         * @return bool
         */
        public static function timingSafeEquals(string $a, string $b): bool
        {
            return hash_equals($a, $b);
        }

        // -------------------------------------------------------------------------
        // Key / token generation
        // -------------------------------------------------------------------------

        /**
         * Generate a cryptographically secure hex-encoded key.
         *
         * @param  int  $bytes  Random bytes before hex encoding (default 32 → 64 hex chars).
         * @return string
         */
        public static function generateKey(int $bytes = 32): string
        {
            return bin2hex(random_bytes(max(1, $bytes)));
        }

        /**
         * Generate a URL-safe cryptographically secure token.
         *
         * Uses base64url encoding (no padding) so the result is safe for use
         * in URLs, cookies, and HTTP headers without further encoding.
         *
         * @param  int  $bytes  Random bytes before encoding (default 32).
         * @return string
         */
        public static function generateToken(int $bytes = 32): string
        {
            return rtrim(strtr(base64_encode(random_bytes(max(1, $bytes))), '+/', '-_'), '=');
        }

        /**
         * Generate a cryptographically secure password.
         *
         * Draws from the full printable + symbol set for maximum entropy.
         *
         * @param  int  $minLength  Minimum length (default 32, capped at 128).
         * @return string
         */
        public static function generatePassword(int $minLength = 32): string
        {
            $alphabet   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%*';
            $randomizer = new \Random\Randomizer(new \Random\Engine\Secure());
            $length     = $randomizer->getInt(max(1, $minLength), 128);

            return $randomizer->getBytesFromString($alphabet, $length);
        }

        /**
         * Generate a cryptographically secure alphanumeric string.
         *
         * Suitable for nonces, session identifiers, and non-sensitive tokens
         * where symbol characters are not permitted.
         *
         * @param  int  $minLength  Minimum length (default 8, capped at 128).
         * @return string
         */
        public static function generateRandString(int $minLength = 8): string
        {
            $alphabet   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
            $randomizer = new \Random\Randomizer(new \Random\Engine\Secure());
            $length     = $randomizer->getInt(max(1, $minLength), 128);

            return $randomizer->getBytesFromString($alphabet, $length);
        }
    }
}
