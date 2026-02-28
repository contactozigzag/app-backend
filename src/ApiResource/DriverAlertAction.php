<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\Alert\AlertActionOutput;
use App\State\Alert\AlertResolveProcessor;
use App\State\Alert\AlertRespondProcessor;

/**
 * Virtual resource for DriverAlert state-transition operations.
 *
 * There is no backing Doctrine entity — operations look up the DriverAlert
 * by its UUID alertId (URI variable) via DriverAlertRepository.
 *
 * Two operations are exposed:
 *  - respond: ROLE_DRIVER; caller must be in nearbyDriverIds
 *  - resolve: ROLE_DRIVER (or ROLE_SCHOOL_ADMIN via hierarchy); caller must
 *             be distressedDriver, respondingDriver, or a school admin
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/driver-alerts/{alertId}/respond',
            openapi: new Operation(
                responses: [
                    '200' => new Response('Alert accepted — status set to responded'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Caller was not notified of this alert'),
                    '404' => new Response('Alert not found'),
                    '422' => new Response('Alert is not in PENDING state'),
                ],
                summary: 'Respond to a distress alert',
                description: "Sets the alert to RESPONDED and notifies the distressed driver via Mercure. The caller must be listed in the alert's nearbyDriverIds.",
            ),
            normalizationContext: [
                'groups' => ['alert:action:read'],
            ],
            security: "is_granted('ROLE_DRIVER')",
            input: false,
            output: AlertActionOutput::class,
            read: false,
            processor: AlertRespondProcessor::class,
        ),
        new Post(
            uriTemplate: '/driver-alerts/{alertId}/resolve',
            openapi: new Operation(
                responses: [
                    '200' => new Response('Alert resolved'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Caller is not authorised to resolve this alert'),
                    '404' => new Response('Alert not found'),
                    '422' => new Response('Alert is already resolved'),
                ],
                summary: 'Resolve a distress alert',
                description: 'Marks the alert as RESOLVED. The caller must be the distressed driver, the responding driver, or a school admin.',
            ),
            normalizationContext: [
                'groups' => ['alert:action:read'],
            ],
            security: "is_granted('ROLE_DRIVER')",
            input: false,
            output: AlertActionOutput::class,
            read: false,
            processor: AlertResolveProcessor::class,
        ),
    ],
)]
final class DriverAlertAction
{
}
