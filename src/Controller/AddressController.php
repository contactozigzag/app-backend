<?php

namespace App\Controller;

use App\Service\GoogleMapsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/address', name: 'api_address_')]
class AddressController extends AbstractController
{
    public function __construct(
        private readonly GoogleMapsService $googleMapsService
    ) {
    }

    /**
     * Validate and geocode an address
     */
    #[Route('/validate', name: 'validate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function validateAddress(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['address']) || empty($data['address'])) {
            return $this->json([
                'error' => 'Address is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->googleMapsService->geocodeAddress($data['address']);

        if ($result === null) {
            return $this->json([
                'error' => 'Could not validate address'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'validated' => true,
            'data' => $result
        ]);
    }

    /**
     * Get place details by place ID
     */
    #[Route('/place/{placeId}', name: 'place_details', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getPlaceDetails(string $placeId): JsonResponse
    {
        $result = $this->googleMapsService->getPlaceDetails($placeId);

        if ($result === null) {
            return $this->json([
                'error' => 'Place not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }

    /**
     * Calculate distance and duration between two addresses
     */
    #[Route('/distance', name: 'distance', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function calculateDistance(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['origin']) || !isset($data['destination'])) {
            return $this->json([
                'error' => 'Origin and destination are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Support both address strings and lat/lng coordinates
        $origin = $data['origin'];
        if (is_string($origin)) {
            $geocoded = $this->googleMapsService->geocodeAddress($origin);
            if ($geocoded === null) {
                return $this->json([
                    'error' => 'Could not geocode origin address'
                ], Response::HTTP_BAD_REQUEST);
            }
            $origin = ['lat' => $geocoded['lat'], 'lng' => $geocoded['lng']];
        }

        $destination = $data['destination'];
        if (is_string($destination)) {
            $geocoded = $this->googleMapsService->geocodeAddress($destination);
            if ($geocoded === null) {
                return $this->json([
                    'error' => 'Could not geocode destination address'
                ], Response::HTTP_BAD_REQUEST);
            }
            $destination = ['lat' => $geocoded['lat'], 'lng' => $geocoded['lng']];
        }

        $result = $this->googleMapsService->getDistanceMatrix($origin, $destination);

        if ($result === null) {
            return $this->json([
                'error' => 'Could not calculate distance'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'distance_meters' => $result['distance'],
            'duration_seconds' => $result['duration'],
            'distance_km' => round($result['distance'] / 1000, 2),
            'duration_minutes' => round($result['duration'] / 60, 2),
        ]);
    }
}
