<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message to trigger subscription payment processing.
 * Scheduled to run every 5 minutes via Symfony Scheduler.
 */
final readonly class ProcessSubscriptionsMessage
{
    public function __construct(
        private int $limit = 100,
        private bool $processRetries = true
    ) {
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function shouldProcessRetries(): bool
    {
        return $this->processRetries;
    }
}
