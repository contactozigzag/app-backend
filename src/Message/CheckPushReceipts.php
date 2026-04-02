<?php

declare(strict_types=1);

namespace App\Message;

use DateTimeImmutable;

final readonly class CheckPushReceipts
{
    public function __construct(
        public DateTimeImmutable $olderThan,
    ) {
    }
}
