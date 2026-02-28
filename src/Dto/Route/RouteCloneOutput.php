<?php

declare(strict_types=1);

namespace App\Dto\Route;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class RouteCloneOutput
{
    public function __construct(
        #[Groups(['route:clone:read'])]
        public bool $success,
        #[Groups(['route:clone:read'])]
        public int $routeId,
    ) {
    }
}
