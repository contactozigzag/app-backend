<?php

declare(strict_types=1);

namespace App\Dto\Tracking;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LocationUpdateInput
{
    public function __construct(
        #[Groups(['tracking:location:write'])]
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $driverId = null,
        #[Groups(['tracking:location:write'])]
        #[Assert\NotNull]
        public ?float $latitude = null,
        #[Groups(['tracking:location:write'])]
        #[Assert\NotNull]
        public ?float $longitude = null,
        #[Groups(['tracking:location:write'])]
        public ?string $timestamp = null,
        #[Groups(['tracking:location:write'])]
        public ?float $speed = null,
        #[Groups(['tracking:location:write'])]
        public ?float $heading = null,
        #[Groups(['tracking:location:write'])]
        public ?float $accuracy = null,
    ) {
    }
}
