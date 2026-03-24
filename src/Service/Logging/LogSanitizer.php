<?php

declare(strict_types=1);

namespace App\Service\Logging;

/**
 * Static utility for masking sensitive data in log context arrays.
 *
 * Log Levels Reference:
 * EMERGENCY — System is unusable (never use in app code — reserved for infrastructure)
 * ALERT     — Action must be taken immediately (never use in app code)
 * CRITICAL  — Critical conditions: uncaught exceptions, data corruption risk
 * ERROR     — Runtime errors: external service failures, failed payments, failed message processing
 * WARNING   — Exceptional occurrences that are not errors: slow queries, fallback to cache, auth failures
 * INFO      — Interesting events: user login, payment created, message dispatched, handler completed
 * DEBUG     — Detailed debug info: cache hits/misses, query details, skipped operations
 */
class LogSanitizer
{
    private const array SENSITIVE_KEYS = [
        'password',
        'secret',
        'token',
        'key',
        'authorization',
        'passphrase',
        'credential',
        'private_key',
        'access_token',
        'refresh_token',
        'api_key',
    ];

    /**
     * Mask an email: first char + *** + @domain.
     */
    public static function maskEmail(string $email): string
    {
        $atPos = strpos($email, '@');
        if ($atPos === false || $atPos === 0) {
            return '***';
        }

        return $email[0] . '***' . substr($email, $atPos);
    }

    /**
     * Mask a token: show only last 4 chars.
     */
    public static function maskToken(string $token): string
    {
        if (strlen($token) <= 4) {
            return '[REDACTED]';
        }

        return '...' . substr($token, -4);
    }

    /**
     * Recursively scan context array and redact values for sensitive keys.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function sanitizeContext(array $context): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $result[$key] = self::sanitizeContext($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        return array_any(self::SENSITIVE_KEYS, fn ($sensitive): bool => str_contains($lower, (string) $sensitive));
    }
}
