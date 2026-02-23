<?php

declare(strict_types=1);

namespace App\Service;

class GeoCalculatorService
{
    private const int EARTH_RADIUS_METERS = 6_371_000;

    /**
     * Calculate the Haversine distance between two coordinates.
     *
     * @return float Distance in meters
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Filter and sort cached driver positions by proximity to a center point.
     *
     * Each element of $positions must have keys: driverId, lat, lng.
     * Returns elements that are within $radiusKm, sorted by distance ascending,
     * with an additional 'distanceMeters' key injected.
     *
     * @param array<int, array{driverId: int, lat: float, lng: float}> $positions
     * @return array<int, array{driverId: int, lat: float, lng: float, distanceMeters: float}>
     */
    public function getNearbyFromCachedPositions(
        array $positions,
        float $centerLat,
        float $centerLng,
        float $radiusKm,
    ): array {
        $radiusMeters = $radiusKm * 1000;
        $nearby = [];

        foreach ($positions as $pos) {
            $distance = $this->calculateDistance(
                $centerLat,
                $centerLng,
                $pos['lat'],
                $pos['lng'],
            );

            if ($distance <= $radiusMeters) {
                $nearby[] = array_merge($pos, ['distanceMeters' => $distance]);
            }
        }

        usort($nearby, static fn (array $a, array $b): int => $a['distanceMeters'] <=> $b['distanceMeters']);

        return $nearby;
    }
}
