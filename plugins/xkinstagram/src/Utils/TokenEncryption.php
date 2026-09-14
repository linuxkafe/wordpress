<?php
/**
 * Token Encryption Utility
 * Encrypts/decrypts Instagram access tokens using AES-256-CBC
 */

declare(strict_types=1);

namespace Xkinstagram\Utils;

final class TokenEncryption {
    private const CIPHER = 'AES-256-CBC';
    private const KEY_LENGTH = 32;
    private const IV_LENGTH = 16;

    public static function encrypt(string $plaintext): string {
        if (empty($plaintext)) {
            return '';
        }

        $key = self::derive_key();
        $iv = random_bytes(self::IV_LENGTH);
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt(string $ciphertext_b64): string {
        if (empty($ciphertext_b64)) {
            return '';
        }

        $data = base64_decode($ciphertext_b64, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        if (strlen($data) < self::IV_LENGTH) {
            throw new \RuntimeException('Encrypted data too short');
        }

        $iv = substr($data, 0, self::IV_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH);
        $key = self::derive_key();

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed - possibly corrupted or wrong key');
        }

        return $plaintext;
    }

    private static function derive_key(): string {
        $salt = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'fallback-salt-change-in-wp-config';
        return hash_pbkdf2('sha256', $salt, 'xkinstagram-token-key', 100000, self::KEY_LENGTH, true);
    }
}