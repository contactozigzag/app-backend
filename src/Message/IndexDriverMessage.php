<?php

declare(strict_types=1);

namespace App\Message;

final readonly class IndexDriverMessage
{
    public function __construct(
        public int $driverId,
    ) {
    }
}
