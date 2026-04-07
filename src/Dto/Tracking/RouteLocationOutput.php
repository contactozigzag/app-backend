<?php

declare(strict_types=1);

namespace App\Dto\Tracking;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Response shape for GET /tracking/route/{routeId}/location/latest.
 *
 * Returned to authenticated parents (and drivers/school-admins) as the
 * initial "gap-fill" snapshot when opening the tracking screen or returning
 * from background. After this call, the client subscribes to the Mercure
 * topic listed in the `mercure` block for real-time updates.
 */
final readonly class RouteLocationOutput
{
    /**
     * @param array{stopId: int|null, stopAddress: string|null, distanceMeters: float|null}|null $nextStop
     * @param array{topicUrl: string, driverTopicUrl: string|null, hubUrl: string}              $mercure
     */
    public function __construct(
        #[Groups(['tracking:route:read'])]
        public float $latitude,
        #[Groups(['tracking:route:read'])]
        public float $longitude,
        #[Groups(['tracking:route:read'])]
        public float|null $heading,
        #[Groups(['tracking:route:read'])]
        public float|null $speed,
        #[Groups(['tracking:route:read'])]
        public string $recordedAt,
        #[Groups(['tracking:route:read'])]
        public string $routeStatus,
        #[Groups(['tracking:route:read'])]
        public array|null $nextStop,
        #[Groups(['tracking:route:read'])]
        public array $mercure,
    ) {
    }
}
