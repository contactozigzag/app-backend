<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\Tracking\BatchLocationInput;
use App\Dto\Tracking\BatchLocationOutput;
use App\Dto\Tracking\DriverLocationOutput;
use App\Dto\Tracking\LocationHistoryOutput;
use App\Dto\Tracking\LocationUpdateInput;
use App\Dto\Tracking\LocationUpdateOutput;
use App\State\Tracking\BatchLocationProcessor;
use App\State\Tracking\DriverLocationProvider;
use App\State\Tracking\LocationHistoryProvider;
use App\State\Tracking\LocationUpdateProcessor;

/**
 * Virtual resource for GPS location tracking operations.
 *
 * Four operations are exposed:
 *  - POST /tracking/location           — ingest a single GPS fix (ROLE_DRIVER)
 *  - POST /tracking/location/batch     — batch-ingest offline GPS points (ROLE_DRIVER)
 *  - GET  /tracking/location/driver/{driverId}         — latest location (ROLE_USER)
 *  - GET  /tracking/location/driver/{driverId}/history — location history (ROUTE_MANAGE)
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/tracking/location',
            status: 201,
            openapi: new Operation(
                responses: [
                    '201' => new Response('Location recorded'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROLE_DRIVER'),
                    '404' => new Response('Driver not found'),
                    '422' => new Response('Validation error'),
                    '429' => new Response('GPS rate limit exceeded'),
                ],
                summary: 'Post a GPS location update',
                description: 'Ingests a GPS fix for a driver. Rate-limited to 1 update per 3 seconds per driver. Dispatches DriverLocationUpdatedMessage for async processing.',
            ),
            normalizationContext: [
                'groups' => ['tracking:location:read'],
            ],
            denormalizationContext: [
                'groups' => ['tracking:location:write'],
            ],
            security: "is_granted('ROLE_DRIVER')",
            input: LocationUpdateInput::class,
            output: LocationUpdateOutput::class,
            processor: LocationUpdateProcessor::class,
        ),
        new Post(
            uriTemplate: '/tracking/location/batch',
            openapi: new Operation(
                responses: [
                    '200' => new Response('Batch processed'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROLE_DRIVER'),
                    '404' => new Response('Driver not found'),
                    '422' => new Response('Validation error'),
                ],
                summary: 'Batch-upload GPS location updates',
                description: 'Ingests multiple GPS fixes for offline sync. Each item is individually validated; item-level failures are collected in the `errors` field.',
            ),
            normalizationContext: [
                'groups' => ['tracking:batch:read'],
            ],
            denormalizationContext: [
                'groups' => ['tracking:batch:write'],
            ],
            security: "is_granted('ROLE_DRIVER')",
            input: BatchLocationInput::class,
            output: BatchLocationOutput::class,
            processor: BatchLocationProcessor::class,
        ),
        new Get(
            uriTemplate: '/tracking/location/driver/{driverId}',
            openapi: new Operation(
                responses: [
                    '200' => new Response('Latest location'),
                    '401' => new Response('Unauthenticated'),
                    '404' => new Response('Driver not found or no location data'),
                ],
                summary: 'Get latest driver location',
                description: "Returns the driver's latest GPS position. Checks Redis cache first (15s TTL); falls back to DB.",
            ),
            normalizationContext: [
                'groups' => ['tracking:driver:read'],
            ],
            security: "is_granted('ROLE_USER')",
            output: DriverLocationOutput::class,
            provider: DriverLocationProvider::class,
        ),
        new Get(
            uriTemplate: '/tracking/location/driver/{driverId}/history',
            openapi: new Operation(
                responses: [
                    '200' => new Response('Location history'),
                    '400' => new Response('Missing or invalid date params'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROUTE_MANAGE permission'),
                    '404' => new Response('Driver not found'),
                ],
                summary: 'Get driver location history',
                description: 'Returns GPS history for a driver within a date range. Required query params: `start` and `end` (ISO 8601).',
            ),
            normalizationContext: [
                'groups' => ['tracking:history:read'],
            ],
            security: "is_granted('ROUTE_MANAGE')",
            output: LocationHistoryOutput::class,
            provider: LocationHistoryProvider::class,
        ),
    ],
)]
final class DriverLocation
{
}
