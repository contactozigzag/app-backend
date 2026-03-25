<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message to trigger cancellation of expired pending payments.
 * Scheduled to run every hour via Symfony Scheduler.
 */
final readonly class ExpireStalePaymentsMessage
{
    public function __construct(
        private int $batchSize = 500,
    ) {
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }
}
