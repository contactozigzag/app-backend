<?php

declare(strict_types=1);

namespace App\Service\Logging;

use OpenTelemetry\API\Trace\Span;

/**
 * Request-scoped service that holds the current correlation ID.
 *
 * The correlation ID is set by CorrelationIdSubscriber (HTTP requests) or
 * CorrelationIdMiddleware (Messenger workers) and propagated through the
 * CorrelationIdProcessor to every log record.
 *
 * When OTel tracing is active, the correlation ID is derived from the trace_id
 * (first 8 chars) so that logs and traces share the same identifier.
 */
class CorrelationIdService
{
    private const string INVALID_TRACE_ID = '00000000000000000000000000000000';

    private string $correlationId = '';

    public function get(): string
    {
        // If OTel trace is active, derive correlation ID from trace_id
        if (class_exists(Span::class)) {
            $traceId = Span::getCurrent()->getContext()->getTraceId();
            if ($traceId !== self::INVALID_TRACE_ID) {
                return substr($traceId, 0, 8);
            }
        }

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
