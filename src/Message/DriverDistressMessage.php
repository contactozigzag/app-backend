<?php

declare(strict_types=1);

namespace App\Message;

final readonly class DriverDistressMessage
{
    public function __construct(
        public int $driverAlertId,
    ) {}
}
