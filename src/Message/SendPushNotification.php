<?php

declare(strict_types=1);

namespace App\Message;

use Stringable;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;

#[TagStamp('push')]
final readonly class SendPushNotification implements Stringable
{
    /**
     * @param list<int> $recipientUserIds
     * @param array<string, mixed> $extraData
     * @param string $eventId Shared with the Mercure SSE payload for client-side
     *                        deduplication. Pass the same ID to both dispatchers so
     *                        the mobile app can suppress the in-app banner when the
     *                        push notification already landed. Defaults to a new
     *                        UUIDv7 when omitted.
     */
    public function __construct(
        public array $recipientUserIds,
        public string $title,
        public string $body,
        public string $notificationType,
        public ?string $deepLink = null,
        public array $extraData = [],
        public string $priority = 'default',
        public ?string $channelId = null,
        public string $eventId = '',
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            'Push → %d recipient(s) [%s]',
            count($this->recipientUserIds),
            $this->notificationType,
        );
    }
}
