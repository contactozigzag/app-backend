<?php

declare(strict_types=1);

namespace App\Service\Tracing;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Throwable;

class TracingService
{
    private readonly TracerInterface $tracer;

    public function __construct()
    {
        $this->tracer = Globals::tracerProvider()->getTracer(
            'zigzag-api',
            '1.0.0',
        );
    }

    /**
     * Wraps a callable in a traced span. Automatically sets status and records exceptions.
     *
     * @template T
     *
     * @param non-empty-string              $spanName   Descriptive name (e.g., "DriverSearch.search")
     * @param callable(): T                $fn         The operation to trace
     * @param array<non-empty-string, string|int|float|bool> $attributes Span attributes
     * @param 0|1|2|3|4                    $kind       Span kind constant (default: SpanKind::KIND_INTERNAL)
     *
     * @return T
     *
     * @throws Throwable Re-throws any exception from $fn
     */
    public function trace(
        string $spanName,
        callable $fn,
        array $attributes = [],
        int $kind = SpanKind::KIND_INTERNAL,
    ): mixed {
        $span = $this->tracer
            ->spanBuilder($spanName)
            ->setSpanKind($kind)
            ->startSpan();

        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        $scope = $span->activate();

        try {
            $result = $fn();
            $span->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (Throwable $throwable) {
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
            $span->recordException($throwable);

            throw $throwable;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function currentSpan(): SpanInterface
    {
        return Span::getCurrent();
    }
}
