<?php

declare(strict_types=1);

namespace App\Dto\Tracking;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BatchLocationInput
{
    public function __construct(
        #[Groups(['tracking:batch:write'])]
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $driverId = null,

        /**
         * @var list<array<string, mixed>>
         */
        #[Groups(['tracking:batch:write'])]
        #[Assert\NotNull]
        public ?array $locations = null,
    ) {
    }
}
