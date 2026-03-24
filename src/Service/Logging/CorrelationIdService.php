<?php

declare(strict_types=1);

namespace App\Service\Logging;

/**
 * Request-scoped service that holds the current correlation ID.
 *
 * The correlation ID is set by CorrelationIdSubscriber (HTTP requests) or
 * CorrelationIdMiddleware (Messenger workers) and propagated through the
 * CorrelationIdProcessor to every log record.
 */
class CorrelationIdService
{
    private string $correlationId = '';

    public function get(): string
    {
        if ($this->correlationId === '') {
            $this->correlationId = self::generate();
        }

        return $this->correlationId;
    }

    public function set(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    /**
     * Generate a short UUID v4 (first 8 hex chars).
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(4));
    }
}
