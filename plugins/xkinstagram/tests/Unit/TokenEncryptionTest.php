<?php
/**
 * Unit tests for TokenEncryption
 */

declare(strict_types=1);

namespace Xkinstagram\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkinstagram\Utils\TokenEncryption;

final class TokenEncryptionTest extends TestCase {
    public function test_encrypt_decrypt_roundtrip(): void {
        $plaintext = 'IGQWRVJYa2ZAZAj...long-lived-token...';
        $encrypted = TokenEncryption::encrypt($plaintext);
        $decrypted = TokenEncryption::decrypt($encrypted);
        
        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_encrypt_empty_string(): void {
        $this->assertEquals('', TokenEncryption::encrypt(''));
    }

    public function test_decrypt_empty_string(): void {
        $this->assertEquals('', TokenEncryption::decrypt(''));
    }

    public function test_different_inputs_produce_different_ciphertexts(): void {
        $plaintext1 = 'token_one';
        $plaintext2 = 'token_two';
        
        $encrypted1 = TokenEncryption::encrypt($plaintext1);
        $encrypted2 = TokenEncryption::encrypt($plaintext2);
        
        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function test_same_input_different_iv_each_time(): void {
        $plaintext = 'same_token';
        
        $encrypted1 = TokenEncryption::encrypt($plaintext);
        $encrypted2 = TokenEncryption::encrypt($plaintext);
        
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        $this->assertEquals($plaintext, TokenEncryption::decrypt($encrypted1));
        $this->assertEquals($plaintext, TokenEncryption::decrypt($encrypted2));
    }

    public function test_decrypt_corrupted_data_throws(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data');
        
        TokenEncryption::decrypt('not-valid-base64!!!');
    }

    public function test_decrypt_tampered_ciphertext_throws(): void {
        $plaintext = 'test_token';
        $encrypted = TokenEncryption::encrypt($plaintext);
        
        $decoded = base64_decode($encrypted, true);
        $corrupted = $decoded;
        $corrupted[16] = chr(ord($corrupted[16]) ^ 1);
        $corrupted_b64 = base64_encode($corrupted);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        
        TokenEncryption::decrypt($corrupted_b64);
    }

    public function test_decrypt_truncated_data_throws(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encrypted data too short');
        
        TokenEncryption::decrypt(base64_encode('short'));
    }
}