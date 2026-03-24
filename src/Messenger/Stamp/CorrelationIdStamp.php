<?php

declare(strict_types=1);

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the correlation ID through the Messenger envelope so that
 * handlers running in a worker process can log with the same ID as
 * the original HTTP request that dispatched the message.
 */
final readonly class CorrelationIdStamp implements StampInterface
{
    public function __construct(
        public string $correlationId,
    ) {
    }
}
