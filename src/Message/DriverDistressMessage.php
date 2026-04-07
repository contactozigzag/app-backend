<?php

declare(strict_types=1);

namespace App\Message;

use Stringable;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;

#[TagStamp('safety')]
final readonly class DriverDistressMessage implements Stringable
{
    public function __construct(
        public int $driverAlertId,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('SOS Alert → Alert #%d', $this->driverAlertId);
    }
}
