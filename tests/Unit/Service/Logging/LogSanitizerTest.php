<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Logging;

use App\Service\Logging\LogSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogSanitizerTest extends TestCase
{
    #[DataProvider('emailProvider')]
    public function testMaskEmail(string $input, string $expected): void
    {
        $this->assertSame($expected, LogSanitizer::maskEmail($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function emailProvider(): iterable
    {
        yield 'normal email' => ['alice@gmail.com', 'a***@gmail.com'];
        yield 'short local part' => ['a@example.com', 'a***@example.com'];
        yield 'no at sign' => ['invalid', '***'];
        yield 'empty string' => ['', '***'];
    }

    public function testMaskToken(): void
    {
        $this->assertSame('...a4Rg', LogSanitizer::maskToken('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9a4Rg'));
        $this->assertSame('[REDACTED]', LogSanitizer::maskToken('abc'));
        $this->assertSame('[REDACTED]', LogSanitizer::maskToken('1234'));
    }

    public function testSanitizeContextRedactsSensitiveKeys(): void
    {
        $context = [
            'user_id' => 42,
            'password' => 'secret123',
            'api_key' => 'sk-1234567890',
            'authorization' => 'Bearer eyJ...',
            'email' => 'alice@example.com',
        ];

        $sanitized = LogSanitizer::sanitizeContext($context);

        $this->assertSame(42, $sanitized['user_id']);
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['api_key']);
        $this->assertSame('[REDACTED]', $sanitized['authorization']);
        $this->assertSame('alice@example.com', $sanitized['email']); // email is not a sensitive key
    }

    public function testSanitizeContextHandlesNestedArrays(): void
    {
        $context = [
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer token123',
                    'Content-Type' => 'application/json',
                ],
            ],
        ];

        $sanitized = LogSanitizer::sanitizeContext($context);

        $this->assertIsArray($sanitized['request']);
        /** @var array<string, mixed> $request */
        $request = $sanitized['request'];
        $this->assertIsArray($request['headers']);
        /** @var array<string, mixed> $headers */
        $headers = $request['headers'];
        $this->assertSame('[REDACTED]', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function testSanitizeContextHandlesEmptyArray(): void
    {
        $this->assertSame([], LogSanitizer::sanitizeContext([]));
    }

    public function testSanitizeContextCatchesVariousKeyPatterns(): void
    {
        $context = [
            'client_secret' => 'abc',
            'refresh_token' => 'xyz',
            'private_key' => 'pem-data',
            'credential' => 'cred123',
            'passphrase' => 'pass',
            'access_token' => 'at123',
        ];

        $sanitized = LogSanitizer::sanitizeContext($context);

        foreach ($sanitized as $value) {
            $this->assertSame('[REDACTED]', $value);
        }
    }
}
