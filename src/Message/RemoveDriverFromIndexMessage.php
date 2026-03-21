<?php

declare(strict_types=1);

namespace App\Message;

final readonly class RemoveDriverFromIndexMessage
{
    public function __construct(
        public int $driverId,
    ) {
    }
}
