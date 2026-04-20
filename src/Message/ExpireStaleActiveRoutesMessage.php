<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Cancels non-terminal ActiveRoute rows whose trip date is in the past.
 * Scheduled nightly via Symfony Scheduler.
 */
final readonly class ExpireStaleActiveRoutesMessage
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
