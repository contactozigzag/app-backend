<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\UuidV7;

/**
 * Mixin for domain events that are published to both Mercure SSE and Expo push
 * notifications. Both publishers must embed the same eventId in their payload
 * so the mobile client can deduplicate: the push listener marks the ID on
 * arrival; the SSE consumer checks the mark and skips the local notification
 * banner if a push for the same event already landed.
 */
trait HasEventId
{
    private ?string $eventId = null;

    public function getEventId(): string
    {
        return $this->eventId ??= new UuidV7()->toRfc4122();
    }
}
