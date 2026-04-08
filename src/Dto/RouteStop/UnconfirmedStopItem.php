<?php

declare(strict_types=1);

namespace App\Dto\RouteStop;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Represents a single unconfirmed route stop in the driver's review list.
 */
final readonly class UnconfirmedStopItem
{
    /**
     * @param list<string>                                                                          $parentNames  Full names of all registered parents
     * @param array{id: int, street: string|null, latitude: string|null, longitude: string|null}|null $address
     */
    public function __construct(
        #[Groups(['route_stop:unconfirmed:read'])]
        public int $id,
        #[Groups(['route_stop:unconfirmed:read'])]
        public ?int $routeId,
        #[Groups(['route_stop:unconfirmed:read'])]
        public ?string $routeName,
        #[Groups(['route_stop:unconfirmed:read'])]
        public ?int $studentId,
        #[Groups(['route_stop:unconfirmed:read'])]
        public string $studentName,
        #[Groups(['route_stop:unconfirmed:read'])]
        public ?string $parentName,
        #[Groups(['route_stop:unconfirmed:read'])]
        public array $parentNames,
        #[Groups(['route_stop:unconfirmed:read'])]
        public ?array $address,
        #[Groups(['route_stop:unconfirmed:read'])]
        public ?string $notes,
        #[Groups(['route_stop:unconfirmed:read'])]
        public string $createdAt,
    ) {
    }
}
