<?php

declare(strict_types=1);

namespace App\Dto\Alert;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class DistressOutput
{
    public function __construct(
        #[Groups(['distress:read'])]
        public string $alertId,
    ) {
    }
}
