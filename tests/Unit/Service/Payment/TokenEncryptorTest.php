<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Payment;

use App\Service\Payment\TokenEncryptor;
use PHPUnit\Framework\TestCase;

final class TokenEncryptorTest extends TestCase
{
    private TokenEncryptor $encryptor;

    protected function setUp(): void
    {
        // Generate a fresh valid key for every test
        $validKey = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $this->encryptor = new TokenEncryptor($validKey);
    }

    public function testEncryptAndDecryptRoundTrip(): void
    {
        $plaintext = 'APP-1234567890abcdef';

        $encrypted = $this->encryptor->encrypt($plaintext);
        $decrypted = $this->encryptor->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesDifferentCiphertextsForSamePlaintext(): void
    {
        $plaintext = 'same-token-value';

        $first = $this->encryptor->encrypt($plaintext);
        $second = $this->encryptor->encrypt($plaintext);

        // Nonce is random per call → ciphertexts must differ
        $this->assertNotSame($first, $second);
        // But both decrypt to the same value
        $this->assertSame($plaintext, $this->encryptor->decrypt($first));
        $this->assertSame($plaintext, $this->encryptor->decrypt($second));
    }

    public function testEncryptOutputIsValidBase64(): void
    {
        $encrypted = $this->encryptor->encrypt('some-token');

        $this->assertNotFalse(base64_decode($encrypted, strict: true));
    }

    public function testDecryptThrowsOnInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('malformed');

        $this->encryptor->decrypt('not-valid-base64!!@@##');
    }

    public function testDecryptThrowsOnTruncatedCiphertext(): void
    {
        // Too short: only the nonce length, no ciphertext appended
        $truncated = base64_encode(str_repeat("\x00", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES));

        $this->expectException(\RuntimeException::class);
        $this->encryptor->decrypt($truncated);
    }

    public function testDecryptThrowsWithWrongKey(): void
    {
        $encrypted = $this->encryptor->encrypt('secret-token');

        // Build a different encryptor with a different key
        $wrongKey = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $wrongEncryptor = new TokenEncryptor($wrongKey);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt token');

        $wrongEncryptor->decrypt($encrypted);
    }

    public function testConstructorThrowsOnKeyTooShort(): void
    {
        $shortKey = base64_encode(random_bytes(16)); // 16 bytes instead of 32

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TOKEN_ENCRYPTION_KEY');

        new TokenEncryptor($shortKey);
    }

    public function testConstructorThrowsOnNonBase64Key(): void
    {
        // PHP typed property rejects the false returned by base64_decode(strict:true)
        // before the manual check can run — either exception signals invalid input.
        $this->expectException(\Throwable::class);

        new TokenEncryptor('this-is-not-base64!@#$');
    }

    public function testRoundTripPreservesUnicodeContent(): void
    {
        $plaintext = "Ñoño token with 🚀 emoji and \n newlines";

        $this->assertSame($plaintext, $this->encryptor->decrypt($this->encryptor->encrypt($plaintext)));
    }
}
