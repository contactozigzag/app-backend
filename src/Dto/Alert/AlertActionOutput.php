<?php

declare(strict_types=1);

namespace App\Dto\Alert;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class AlertActionOutput
{
    public function __construct(
        #[Groups(['alert:action:read'])]
        public bool $success,
        #[Groups(['alert:action:read'])]
        public string $alertId,
        #[Groups(['alert:action:read'])]
        public string $status,
    ) {
    }
}
