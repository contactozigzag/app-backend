<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\RouteStop\UnconfirmedStopsOutput;
use App\State\RouteStop\UnconfirmedRouteStopsProvider;

/**
 * Virtual resource for listing unconfirmed route stops assigned to a driver.
 *
 * The ROLE_DRIVER check is performed inside the provider (before any DB queries)
 * because AP4 evaluates `security:` after the provider returns for GET operations.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/route-stops/unconfirmed',
            openapi: new Operation(
                responses: [
                    '200' => new Response('Unconfirmed stops list'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROLE_DRIVER'),
                ],
                summary: 'List unconfirmed route stops for the driver',
                description: "Returns all active, unconfirmed route stops across the authenticated driver's routes. Requires ROLE_DRIVER.",
            ),
            normalizationContext: [
                'groups' => ['route_stop:unconfirmed:read'],
            ],
            security: "is_granted('ROLE_USER')",
            output: UnconfirmedStopsOutput::class,
            provider: UnconfirmedRouteStopsProvider::class,
        ),
    ],
)]
final class UnconfirmedRouteStop
{
}
