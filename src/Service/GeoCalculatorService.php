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
}
