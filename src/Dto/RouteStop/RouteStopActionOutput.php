<?php

declare(strict_types=1);

namespace App\Dto\RouteStop;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class RouteStopActionOutput
{
    public function __construct(
        #[Groups(['route_stop:action:read'])]
        public bool $success,
        #[Groups(['route_stop:action:read'])]
        public string $message,
        #[Groups(['route_stop:action:read'])]
        public int $routeStopId,
    ) {
    }
}
