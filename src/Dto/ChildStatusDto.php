<?php

namespace App\Dto;

class ChildStatusDto
{
    public function __construct(
        public readonly int $studentId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $currentStatus,
        public readonly ?int $activeRouteId,
        public readonly ?string $routeStatus,
        public readonly ?array $busLocation,
        public readonly ?string $estimatedArrival,
        public readonly ?string $lastUpdate,
    ) {
    }
}
