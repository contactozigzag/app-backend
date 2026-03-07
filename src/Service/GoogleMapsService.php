<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GoogleMapsService
{
    private readonly Client $client;

    public function __construct(
        #[Autowire(env: 'GOOGLE_MAPS_API_KEY')]
        private readonly string $apiKey,
        private readonly LoggerInterface $logger
    ) {
        $this->client = new Client([
            'base_uri' => 'https://maps.googleapis.com/maps/api/',
            'timeout' => 10.0,
        ]);
    }

    /**
     * Validate and geocode an address using Google Places API
     *
     * @param string $address The address to validate
     * @return array{lat: float, lng: float, formatted_address: string, place_id: string}|null
     */
    public function geocodeAddress(string $address): ?array
    {
        try {
            $response = $this->client->get('geocode/json', [
                'query' => [
                    'address' => $address,
                    'key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'OK' && ! empty($data['results'])) {
                $result = $data['results'][0];

                return [
                    'lat' => $result['geometry']['location']['lat'],
                    'lng' => $result['geometry']['location']['lng'],
                    'formatted_address' => $result['formatted_address'],
                    'place_id' => $result['place_id'],
                ];
            }

            $this->logger->warning('Geocoding failed', [
                'address' => $address,
                'status' => $data['status'],
            ]);

            return null;
        } catch (GuzzleException $guzzleException) {
            $this->logger->error('Geocoding API error', [
                'address' => $address,
                'error' => $guzzleException->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse address components from Google Geocoding API response
     *
     * @param string $address The address to parse
     * @param string|null $language Language code (e.g., 'en', 'es') for API response
     * @param string $addressFormat Address format: 'number_first' (e.g., "123 Main St") or 'street_first' (e.g., "Calle Principal 123")
     * @return array{street_address: string, city: string, state: string, country: string, postal_code: string, lat: float, lng: float, place_id: string, country_code: string}|null
     */
    public function parseAddressComponents(string $address, ?string $language = null, string $addressFormat = 'number_first'): ?array
    {
        try {
            $queryParams = [
                'address' => $address,
                'key' => $this->apiKey,
            ];

            // Add language parameter if specified
            if ($language !== null) {
                $queryParams['language'] = $language;
            }

            $response = $this->client->get('geocode/json', [
                'query' => $queryParams,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] !== 'OK' || empty($data['results'])) {
                $this->logger->warning('Geocoding failed for address components', [
                    'address' => $address,
                    'language' => $language,
                    'status' => $data['status'],
                ]);

                return null;
            }

            $result = $data['results'][0];
            $components = $result['address_components'];

            // Initialize with empty values
            $parsed = [
                'street_number' => '',
                'route' => '',
                'city' => '',
                'state' => '',
                'country' => '',
                'country_code' => '',
                'postal_code' => '',
            ];

            // Extract address components
            foreach ($components as $component) {
                if (! is_array($component)) {
                    continue;
                }

                if (! isset($component['types'])) {
                    continue;
                }

                if (! is_array($component['types'])) {
                    continue;
                }

                $types = $component['types'];

                if (in_array('street_number', $types, true) && isset($component['long_name']) && is_scalar($component['long_name'])) {
                    $parsed['street_number'] = (string) $component['long_name'];
                } elseif (in_array('route', $types, true) && isset($component['long_name']) && is_scalar($component['long_name'])) {
                    $parsed['route'] = (string) $component['long_name'];
                } elseif (in_array('locality', $types, true) && isset($component['long_name']) && is_scalar($component['long_name'])) {
                    $parsed['city'] = (string) $component['long_name'];
                } elseif (in_array('administrative_area_level_1', $types, true) && isset($component['long_name']) && is_scalar($component['long_name'])) {
                    $parsed['state'] = (string) $component['long_name'];
                } elseif (in_array('country', $types, true)) {
                    if (isset($component['long_name']) && is_scalar($component['long_name'])) {
                        $parsed['country'] = (string) $component['long_name'];
                    }

                    if (isset($component['short_name']) && is_scalar($component['short_name'])) {
                        $parsed['country_code'] = (string) $component['short_name'];
                    }
                } elseif (in_array('postal_code', $types, true) && isset($component['long_name']) && is_scalar($component['long_name'])) {
                    $parsed['postal_code'] = (string) $component['long_name'];
                }
            }

            // Construct street address based on format
            $streetAddress = $this->formatStreetAddress(
                $parsed['street_number'],
                $parsed['route'],
                $addressFormat
            );

            return [
                'street_address' => $streetAddress,
                'city' => $parsed['city'],
                'state' => $parsed['state'],
                'country' => $parsed['country'],
                'country_code' => $parsed['country_code'],
                'postal_code' => $parsed['postal_code'],
                'lat' => $result['geometry']['location']['lat'],
                'lng' => $result['geometry']['location']['lng'],
                'place_id' => $result['place_id'],
            ];
        } catch (GuzzleException $guzzleException) {
            $this->logger->error('Geocoding API error for address components', [
                'address' => $address,
                'language' => $language,
                'error' => $guzzleException->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Format street address based on locale conventions
     *
     * @param string $streetNumber The street number
     * @param string $route The street name
     * @param string $format Format type: 'number_first' or 'street_first'
     * @return string Formatted street address
     */
    private function formatStreetAddress(string $streetNumber, string $route, string $format): string
    {
        if ($streetNumber === '' || $route === '') {
            return trim(sprintf('%s %s', $streetNumber, $route));
        }

        return match ($format) {
            'street_first' => trim(sprintf('%s %s', $route, $streetNumber)), // Spanish: "Avenida Benavídez 1632"
            'number_first' => trim(sprintf('%s %s', $streetNumber, $route)), // English: "1632 Benavídez Avenue"
            default => trim(sprintf('%s %s', $streetNumber, $route)),
        };
    }

    /**
     * Get distance and duration between two points using Distance Matrix API
     *
     * @param array{lat: float, lng: float} $origin
     * @param array{lat: float, lng: float} $destination
     * @return array{distance: int, duration: int}|null Distance in meters, duration in seconds
     */
    public function getDistanceMatrix(array $origin, array $destination): ?array
    {
        try {
            $originStr = sprintf('%s,%s', $origin['lat'], $origin['lng']);
            $destinationStr = sprintf('%s,%s', $destination['lat'], $destination['lng']);

            $response = $this->client->get('distancematrix/json', [
                'query' => [
                    'origins' => $originStr,
                    'destinations' => $destinationStr,
                    'key' => $this->apiKey,
                    'mode' => 'driving',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'OK' && ! empty($data['rows'])) {
                $element = $data['rows'][0]['elements'][0];

                if ($element['status'] === 'OK') {
                    return [
                        'distance' => $element['distance']['value'], // in meters
                        'duration' => $element['duration']['value'], // in seconds
                    ];
                }
            }

            $this->logger->warning('Distance Matrix failed', [
                'origin' => $originStr,
                'destination' => $destinationStr,
                'status' => $data['status'] ?? 'UNKNOWN',
            ]);

            return null;
        } catch (GuzzleException $guzzleException) {
            $this->logger->error('Distance Matrix API error', [
                'error' => $guzzleException->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get optimized route using Directions API
     *
     * @param array{lat: float, lng: float} $origin
     * @param array{lat: float, lng: float} $destination
     * @param array<array{lat: float, lng: float}> $waypoints
     * @param bool $optimize Whether to optimize waypoint order
     * @return array{optimized_order: int[], total_distance: int, total_duration: int, polyline: string}|null
     */
    public function getOptimizedRoute(
        array $origin,
        array $destination,
        array $waypoints = [],
        bool $optimize = true
    ): ?array {
        try {
            $originStr = sprintf('%s,%s', $origin['lat'], $origin['lng']);
            $destinationStr = sprintf('%s,%s', $destination['lat'], $destination['lng']);

            $waypointsStr = '';
            if ($waypoints !== []) {
                $waypointCoords = array_map(
                    fn (array $wp): string => sprintf('%s,%s', $wp['lat'], $wp['lng']),
                    $waypoints
                );
                $prefix = $optimize ? 'optimize:true|' : '';
                $waypointsStr = $prefix . implode('|', $waypointCoords);
            }

            $params = [
                'origin' => $originStr,
                'destination' => $destinationStr,
                'key' => $this->apiKey,
                'mode' => 'driving',
            ];

            if ($waypointsStr !== '' && $waypointsStr !== '0') {
                $params['waypoints'] = $waypointsStr;
            }

            $response = $this->client->get('directions/json', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'OK' && ! empty($data['routes'])) {
                $route = $data['routes'][0];

                $totalDistance = 0;
                $totalDuration = 0;

                foreach ($route['legs'] as $leg) {
                    $totalDistance += $leg['distance']['value'];
                    $totalDuration += $leg['duration']['value'];
                }

                $result = [
                    'total_distance' => $totalDistance,
                    'total_duration' => $totalDuration,
                    'polyline' => $route['overview_polyline']['points'],
                ];

                if (isset($route['waypoint_order'])) {
                    $result['optimized_order'] = $route['waypoint_order'];
                }

                return $result;
            }

            $this->logger->warning('Directions API failed', [
                'status' => $data['status'] ?? 'UNKNOWN',
            ]);

            return null;
        } catch (GuzzleException $guzzleException) {
            $this->logger->error('Directions API error', [
                'error' => $guzzleException->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate a place ID using Places API
     *
     * @return array{name: string, formatted_address: string, lat: float, lng: float}|null
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        try {
            $response = $this->client->get('place/details/json', [
                'query' => [
                    'place_id' => $placeId,
                    'key' => $this->apiKey,
                    'fields' => 'name,formatted_address,geometry',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'OK' && ! empty($data['result'])) {
                $result = $data['result'];

                return [
                    'name' => $result['name'] ?? '',
                    'formatted_address' => $result['formatted_address'],
                    'lat' => $result['geometry']['location']['lat'],
                    'lng' => $result['geometry']['location']['lng'],
                ];
            }

            return null;
        } catch (GuzzleException $guzzleException) {
            $this->logger->error('Place Details API error', [
                'place_id' => $placeId,
                'error' => $guzzleException->getMessage(),
            ]);

            return null;
        }
    }
}
