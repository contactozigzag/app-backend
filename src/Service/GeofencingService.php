<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Repository\ActiveRouteStopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

class GeofencingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveRouteStopRepository $stopRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check if a location is within a geofence
     *
     * @param array{lat: float, lng: float} $location
     * @param array{lat: float, lng: float} $geofenceCenter
     * @param int $radius in meters
     */
    public function isWithinGeofence(array $location, array $geofenceCenter, int $radius): bool
    {
        $distance = $this->calculateDistance(
            $location['lat'],
            $location['lng'],
            $geofenceCenter['lat'],
            $geofenceCenter['lng']
        );

        return $distance <= $radius;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     *
     * @return float distance in meters
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check active route against all stops and update statuses
     *
     * @return array{approaching: array, arrived: array}
     */
    public function checkActiveRoute(ActiveRoute $activeRoute): array
    {
        if ($activeRoute->getStatus() !== 'in_progress') {
            return [
                'approaching' => [],
                'arrived' => [],
            ];
        }

        $currentLat = (float) $activeRoute->getCurrentLatitude();
        $currentLng = (float) $activeRoute->getCurrentLongitude();

        if ($currentLat === 0.0 || $currentLng === 0.0) {
            return [
                'approaching' => [],
                'arrived' => [],
            ];
        }

        $currentLocation = [
            'lat' => $currentLat,
            'lng' => $currentLng,
        ];
        $approaching = [];
        $arrived = [];

        $stops = $this->stopRepository->findByActiveRouteOrdered($activeRoute);

        foreach ($stops as $stop) {
            if (! in_array($stop->getStatus(), ['pending', 'approaching'], true)) {
                continue;
            }

            $address = $stop->getAddress();
            $stopLocation = [
                'lat' => (float) $address->getLatitude(),
                'lng' => (float) $address->getLongitude(),
            ];

            $distance = $this->calculateDistance(
                $currentLocation['lat'],
                $currentLocation['lng'],
                $stopLocation['lat'],
                $stopLocation['lng']
            );

            $geofenceRadius = $stop->getGeofenceRadius();

            // Check if within geofence (arrived)
            if ($distance <= $geofenceRadius) {
                if ($stop->getStatus() !== 'arrived') {
                    $stop->setStatus('arrived');
                    $stop->setArrivedAt(new \DateTimeImmutable());
                    $arrived[] = $stop;

                    $this->logger->info('Stop arrived', [
                        'stop_id' => $stop->getId(),
                        'student_id' => $stop->getStudent()->getId(),
                        'distance' => $distance,
                    ]);

                    // Dispatch event
                    $this->eventDispatcher->dispatch(
                        new StopArrivedEvent($stop),
                        'stop.arrived'
                    );
                }
            }
            // Check if approaching (within 2x radius)
            elseif ($distance <= ($geofenceRadius * 2) && $stop->getStatus() === 'pending') {
                $stop->setStatus('approaching');
                $approaching[] = $stop;

                $this->logger->info('Stop approaching', [
                    'stop_id' => $stop->getId(),
                    'student_id' => $stop->getStudent()->getId(),
                    'distance' => $distance,
                ]);

                // Dispatch event
                $this->eventDispatcher->dispatch(
                    new StopApproachingEvent($stop),
                    'stop.approaching'
                );
            }
        }

        if ($approaching !== [] || $arrived !== []) {
            $this->entityManager->flush();
        }

        return [
            'approaching' => array_map(fn (\App\Entity\ActiveRouteStop $s): ?int => $s->getId(), $approaching),
            'arrived' => array_map(fn (\App\Entity\ActiveRouteStop $s): ?int => $s->getId(), $arrived),
        ];
    }

    /**
     * Process all active routes for geofencing
     *
     * @param ActiveRoute[] $activeRoutes
     */
    public function processActiveRoutes(array $activeRoutes): array
    {
        $results = [];

        foreach ($activeRoutes as $activeRoute) {
            $result = $this->checkActiveRoute($activeRoute);
            if (! empty($result['approaching']) || ! empty($result['arrived'])) {
                $results[$activeRoute->getId()] = $result;
            }
        }

        return $results;
    }

    /**
     * Get distance to next stop
     *
     * @return array{distance: float, stop_id: int}|null
     */
    public function getDistanceToNextStop(ActiveRoute $activeRoute): ?array
    {
        if ($activeRoute->getCurrentLatitude() === null || $activeRoute->getCurrentLongitude() === null) {
            return null;
        }

        $nextStop = $this->stopRepository->findNextPendingStop($activeRoute);

        if (! $nextStop instanceof \App\Entity\ActiveRouteStop) {
            return null;
        }

        $currentLocation = [
            'lat' => (float) $activeRoute->getCurrentLatitude(),
            'lng' => (float) $activeRoute->getCurrentLongitude(),
        ];

        $address = $nextStop->getAddress();
        $stopLocation = [
            'lat' => (float) $address->getLatitude(),
            'lng' => (float) $address->getLongitude(),
        ];

        $distance = $this->calculateDistance(
            $currentLocation['lat'],
            $currentLocation['lng'],
            $stopLocation['lat'],
            $stopLocation['lng']
        );

        return [
            'distance' => $distance,
            'stop_id' => $nextStop->getId(),
            'student_name' => $nextStop->getStudent()->getFirstName() . ' ' . $nextStop->getStudent()->getLastName(),
        ];
    }
}

/**
 * Event dispatched when a stop is approaching
 */
class StopApproachingEvent extends Event
{
    public function __construct(
        private readonly ActiveRouteStop $stop
    ) {
    }

    public function getStop(): ActiveRouteStop
    {
        return $this->stop;
    }
}

/**
 * Event dispatched when a stop is arrived
 */
class StopArrivedEvent extends Event
{
    public function __construct(
        private readonly ActiveRouteStop $stop
    ) {
    }

    public function getStop(): ActiveRouteStop
    {
        return $this->stop;
    }
}
