<?php

declare(strict_types=1);

namespace App\Dto\Tracking;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class DriverLocationOutput
{
    public function __construct(
        #[Groups(['tracking:driver:read'])]
        public int $driverId,
        #[Groups(['tracking:driver:read'])]
        public string $latitude,
        #[Groups(['tracking:driver:read'])]
        public string $longitude,
        #[Groups(['tracking:driver:read'])]
        public string|float|null $speed,
        #[Groups(['tracking:driver:read'])]
        public string|float|null $heading,
        #[Groups(['tracking:driver:read'])]
        public ?string $accuracy,
        #[Groups(['tracking:driver:read'])]
        public string $timestamp,
        #[Groups(['tracking:driver:read'])]
        public string $source,
    ) {
    }
}
