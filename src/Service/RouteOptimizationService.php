<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class RouteOptimizationService
{
    public function __construct(
        private readonly GoogleMapsService $googleMapsService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Optimize route stops using nearest neighbor heuristic
     * This is a greedy TSP approximation algorithm suitable for real-time use
     *
     * @param array{lat: float, lng: float} $startPoint
     * @param array{lat: float, lng: float} $endPoint
     * @param array<int, array{id: int, lat: float, lng: float}> $stops
     * @return array{
     *     optimized_order: array<int>,
     *     total_distance: int,
     *     total_duration: int,
     *     segments: array<array{from: int, to: int, distance: int, duration: int}>
     * }|null
     */
    public function optimizeRoute(array $startPoint, array $endPoint, array $stops): ?array
    {
        if (empty($stops)) {
            // Direct route from start to end
            $distance = $this->googleMapsService->getDistanceMatrix($startPoint, $endPoint);
            if ($distance === null) {
                return null;
            }

            return [
                'optimized_order' => [],
                'total_distance' => $distance['distance'],
                'total_duration' => $distance['duration'],
                'segments' => [
                    [
                        'from' => 'start',
                        'to' => 'end',
                        'distance' => $distance['distance'],
                        'duration' => $distance['duration'],
                    ]
                ],
            ];
        }

        // For small number of stops (<=10), use Google's built-in optimization
        if (count($stops) <= 10) {
            return $this->optimizeWithGoogleDirections($startPoint, $endPoint, $stops);
        }

        // For larger routes, use nearest neighbor heuristic
        return $this->optimizeWithNearestNeighbor($startPoint, $endPoint, $stops);
    }

    /**
     * Use Google Directions API with waypoint optimization
     */
    private function optimizeWithGoogleDirections(
        array $startPoint,
        array $endPoint,
        array $stops
    ): ?array {
        $waypoints = array_map(
            fn($stop) => ['lat' => $stop['lat'], 'lng' => $stop['lng']],
            $stops
        );

        $result = $this->googleMapsService->getOptimizedRoute(
            $startPoint,
            $endPoint,
            $waypoints,
            true
        );

        if ($result === null) {
            return null;
        }

        // Map optimized order to stop IDs
        $optimizedOrder = [];
        if (isset($result['optimized_order'])) {
            foreach ($result['optimized_order'] as $index) {
                $optimizedOrder[] = $stops[$index]['id'];
            }
        } else {
            // If no optimization was performed, use original order
            $optimizedOrder = array_column($stops, 'id');
        }

        return [
            'optimized_order' => $optimizedOrder,
            'total_distance' => $result['total_distance'],
            'total_duration' => $result['total_duration'],
            'segments' => [],
        ];
    }

    /**
     * Nearest neighbor TSP heuristic for larger routes
     */
    private function optimizeWithNearestNeighbor(
        array $startPoint,
        array $endPoint,
        array $stops
    ): ?array {
        $unvisited = $stops;
        $route = [];
        $currentPoint = $startPoint;
        $totalDistance = 0;
        $totalDuration = 0;
        $segments = [];

        // Build route by always choosing nearest unvisited stop
        while (!empty($unvisited)) {
            $nearestIndex = null;
            $nearestDistance = PHP_FLOAT_MAX;
            $nearestDuration = 0;

            foreach ($unvisited as $index => $stop) {
                $distance = $this->calculateHaversineDistance(
                    $currentPoint['lat'],
                    $currentPoint['lng'],
                    $stop['lat'],
                    $stop['lng']
                );

                if ($distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            if ($nearestIndex === null) {
                break;
            }

            $nearestStop = $unvisited[$nearestIndex];

            // Get actual distance from Google
            $distanceData = $this->googleMapsService->getDistanceMatrix(
                $currentPoint,
                ['lat' => $nearestStop['lat'], 'lng' => $nearestStop['lng']]
            );

            if ($distanceData === null) {
                // Fallback to Haversine estimate
                $distanceData = [
                    'distance' => (int)($nearestDistance * 1000), // Convert km to meters
                    'duration' => (int)($nearestDistance * 1000 / 10), // Rough estimate: 36 km/h average
                ];
            }

            $segments[] = [
                'from' => empty($route) ? 'start' : $route[count($route) - 1],
                'to' => $nearestStop['id'],
                'distance' => $distanceData['distance'],
                'duration' => $distanceData['duration'],
            ];

            $route[] = $nearestStop['id'];
            $totalDistance += $distanceData['distance'];
            $totalDuration += $distanceData['duration'];
            $currentPoint = ['lat' => $nearestStop['lat'], 'lng' => $nearestStop['lng']];

            unset($unvisited[$nearestIndex]);
        }

        // Add final segment to end point
        $finalDistance = $this->googleMapsService->getDistanceMatrix($currentPoint, $endPoint);
        if ($finalDistance !== null) {
            $segments[] = [
                'from' => $route[count($route) - 1],
                'to' => 'end',
                'distance' => $finalDistance['distance'],
                'duration' => $finalDistance['duration'],
            ];
            $totalDistance += $finalDistance['distance'];
            $totalDuration += $finalDistance['duration'];
        }

        return [
            'optimized_order' => $route,
            'total_distance' => $totalDistance,
            'total_duration' => $totalDuration,
            'segments' => $segments,
        ];
    }

    /**
     * Calculate Haversine distance between two coordinates (in kilometers)
     */
    private function calculateHaversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Optimize route with time windows (advanced)
     * For future implementation when student pickup times are added
     *
     * @param array $startPoint
     * @param array $endPoint
     * @param array<array{id: int, lat: float, lng: float, time_window_start: int, time_window_end: int}> $stops
     * @return array|null
     */
    public function optimizeRouteWithTimeWindows(
        array $startPoint,
        array $endPoint,
        array $stops
    ): ?array {
        // TODO: Implement time window constraints
        // This would require a more sophisticated algorithm like:
        // - Genetic Algorithm
        // - Simulated Annealing
        // - Or integration with Google OR-Tools

        $this->logger->info('Time window optimization not yet implemented, falling back to basic optimization');

        return $this->optimizeRoute($startPoint, $endPoint, $stops);
    }
}
