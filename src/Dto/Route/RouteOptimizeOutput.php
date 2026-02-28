<?php

declare(strict_types=1);

namespace App\Dto\Route;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class RouteOptimizeOutput
{
    public function __construct(
        #[Groups(['route:optimize:read'])]
        public bool $success,
        /**
         * @var int[]
         */
        #[Groups(['route:optimize:read'])]
        public array $optimizedOrder,
        #[Groups(['route:optimize:read'])]
        public int $totalDistance,
        #[Groups(['route:optimize:read'])]
        public int $totalDuration,
        #[Groups(['route:optimize:read'])]
        public float $distanceKm,
        #[Groups(['route:optimize:read'])]
        public float $durationMinutes,
    ) {
    }
}
