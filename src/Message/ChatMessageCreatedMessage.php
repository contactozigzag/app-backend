<?php

declare(strict_types=1);

namespace App\Message;

use Stringable;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;

#[TagStamp('safety')]
final readonly class ChatMessageCreatedMessage implements Stringable
{
    public function __construct(
        public int $chatMessageId,
        public string $alertId,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('Chat → Alert #%s', $this->alertId);
    }
}
