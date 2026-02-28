<?php

declare(strict_types=1);

namespace App\Dto\Route;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class RouteOptimizePreviewOutput
{
    public function __construct(
        /**
         * @var array<int, array{order: int, stopId: int, studentName: string, address: string}>
         */
        #[Groups(['route:optimize:preview:read'])]
        public array $optimizedStops,
        #[Groups(['route:optimize:preview:read'])]
        public int $totalDistance,
        #[Groups(['route:optimize:preview:read'])]
        public int $totalDuration,
        #[Groups(['route:optimize:preview:read'])]
        public float $distanceKm,
        #[Groups(['route:optimize:preview:read'])]
        public float $durationMinutes,
        /**
         * @var array<int, array{from: mixed, to: mixed, distance: int, duration: int}>
         */
        #[Groups(['route:optimize:preview:read'])]
        public array $segments,
    ) {
    }
}
