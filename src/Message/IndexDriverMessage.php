<?php

declare(strict_types=1);

namespace App\Message;

use Zenstruck\Messenger\Monitor\Stamp\TagStamp;

#[TagStamp('search')]
final readonly class IndexDriverMessage
{
    public function __construct(
        public int $driverId,
    ) {
    }
}
