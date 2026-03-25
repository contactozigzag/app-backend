<?php

declare(strict_types=1);

namespace App\Service\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\API\Trace\Span;

/**
 * Monolog processor that enriches every log record with OTel trace_id and span_id.
 * Enables Loki → Tempo correlation in Grafana.
 */
final class TraceContextProcessor implements ProcessorInterface
{
    private const string INVALID_TRACE_ID = '00000000000000000000000000000000';

    public function __invoke(LogRecord $record): LogRecord
    {
        if (! class_exists(Span::class)) {
            return $record;
        }

        $context = Span::getCurrent()->getContext();
        $traceId = $context->getTraceId();

        if ($traceId === self::INVALID_TRACE_ID) {
            return $record;
        }

        return $record->with(
            extra: array_merge($record->extra, [
                'trace_id' => $traceId,
                'span_id' => $context->getSpanId(),
            ]),
        );
    }
}
