<?php

declare(strict_types=1);

namespace App\Message;

final readonly class StudentReadyForPickupMessage
{
    public function __construct(
        public int $specialEventRouteId,
        public int $studentId,
        public string $lockKey,
    ) {
    }
}
