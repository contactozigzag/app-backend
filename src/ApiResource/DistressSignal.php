<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\Alert\DistressOutput;
use App\State\Alert\DistressProcessor;

/**
 * Virtual resource representing a driver distress signal.
 *
 * There is no backing Doctrine entity. The driver POSTs to this endpoint to
 * trigger a distress alert for their active route session. The alert is
 * persisted and DriverDistressMessage is dispatched for async processing
 * (nearby driver notification, Mercure broadcast, etc.).
 *
 * Security: ROLE_DRIVER required. The processor additionally validates
 * that the authenticated driver owns the given route session.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/routes/sessions/{id}/distress',
            status: 202,
            openapi: new Operation(
                responses: [
                    '202' => new Response('Alert created — DriverDistressMessage enqueued'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Caller is not the session driver'),
                    '404' => new Response('Route session not found'),
                    '409' => new Response('An active distress alert already exists'),
                    '422' => new Response('Route session is not in progress'),
                ],
                summary: 'Trigger a distress signal',
                description: 'Triggers a distress alert for an in-progress route session. The caller must be the driver of the session.',
            ),
            normalizationContext: [
                'groups' => ['distress:read'],
            ],
            security: "is_granted('ROLE_DRIVER')",
            input: false,
            output: DistressOutput::class,
            read: false,
            processor: DistressProcessor::class,
        ),
    ],
)]
final class DistressSignal
{
}
