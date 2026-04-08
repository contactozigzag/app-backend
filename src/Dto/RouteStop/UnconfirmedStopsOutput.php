<?php

declare(strict_types=1);

namespace App\Dto\RouteStop;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class UnconfirmedStopsOutput
{
    public function __construct(
        /**
         * @var list<UnconfirmedStopItem>
         */
        #[Groups(['route_stop:unconfirmed:read'])]
        public array $unconfirmedStops,
        #[Groups(['route_stop:unconfirmed:read'])]
        public int $total,
    ) {
    }
}
