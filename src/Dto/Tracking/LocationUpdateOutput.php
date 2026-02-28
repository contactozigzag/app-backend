<?php

declare(strict_types=1);

namespace App\Dto\Tracking;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class LocationUpdateOutput
{
    public function __construct(
        #[Groups(['tracking:location:read'])]
        public bool $success,
        #[Groups(['tracking:location:read'])]
        public int $locationId,
        #[Groups(['tracking:location:read'])]
        public bool $hasActiveRoute,
    ) {
    }
}
