<?php

declare(strict_types=1);

namespace App\Message;

final readonly class DriverLocationUpdatedMessage
{
    public function __construct(
        public int $driverId,
        public int|null $activeRouteId,
        public float $latitude,
        public float $longitude,
        public float|null $speed,
        public float|null $heading,
        public string $correlationId,
        public \DateTimeImmutable $recordedAt,
    ) {}
}
