<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ChatMessageCreatedMessage
{
    public function __construct(
        public int $chatMessageId,
        public string $alertId,
    ) {}
}
