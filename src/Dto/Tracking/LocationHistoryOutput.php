<?php

declare(strict_types=1);

namespace App\Dto\Tracking;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class LocationHistoryOutput
{
    public function __construct(
        #[Groups(['tracking:history:read'])]
        public int $driverId,
        #[Groups(['tracking:history:read'])]
        public int $count,

        /**
         * @var list<array{latitude: string, longitude: string, speed: string|null, heading: string|null, accuracy: string|null, timestamp: string}>
         */
        #[Groups(['tracking:history:read'])]
        public array $locations,
    ) {
    }
}
