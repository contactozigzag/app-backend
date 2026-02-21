<?php

declare(strict_types=1);

namespace App\Service\Payment;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Symmetric encryption for OAuth tokens stored in the database.
 *
 * Uses libsodium secretbox (XSalsa20-Poly1305).
 * A random 24-byte nonce is prepended to every ciphertext so the same
 * plaintext produces a different stored value on each call.
 *
 * Generate a key with:
 *   php -r "echo base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));"
 * Store the result in the TOKEN_ENCRYPTION_KEY env var.
 */
class TokenEncryptor
{
    private readonly string $key;

    public function __construct(
        #[Autowire(env: 'TOKEN_ENCRYPTION_KEY')]
        string $encryptionKey,
    ) {
        $decoded = base64_decode($encryptionKey, strict: true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf(
                    'TOKEN_ENCRYPTION_KEY must be a base64-encoded %d-byte string.',
                    SODIUM_CRYPTO_SECRETBOX_KEYBYTES
                )
            );
        }

        $this->key = $decoded;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, strict: true);

        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Encrypted token is malformed.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt token — wrong key or corrupted data.');
        }

        return $plaintext;
    }
}
