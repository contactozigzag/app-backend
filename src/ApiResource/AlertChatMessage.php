<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\Chat\ChatMessageIdOutput;
use App\Dto\Chat\ChatMessageInput;
use App\Dto\Chat\ChatMessageListOutput;
use App\State\Chat\ChatMessageListProvider;
use App\State\Chat\ChatMessagePostProcessor;

/**
 * Virtual resource representing the emergency chat thread for a DriverAlert.
 *
 * Two operations are exposed:
 *  - POST /driver-alerts/{alertId}/messages — post a message to the thread
 *  - GET  /driver-alerts/{alertId}/messages — fetch paginated messages
 *
 * Access is restricted to chat participants (distressed driver, responding
 * driver, or school admin). The participant check is performed inside the
 * processor/provider since it requires the alert to be loaded first.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/driver-alerts/{alertId}/messages',
            status: 201,
            openapi: new Operation(
                responses: [
                    '201' => new Response('Message created'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Not a chat participant'),
                    '404' => new Response('Alert not found'),
                    '422' => new Response('Chat is read-only (alert resolved) or missing content'),
                ],
                summary: 'Post a message to an alert chat thread',
                description: 'Creates a new encrypted chat message for the emergency thread. Only the distressed driver, responding driver, or a school admin may post.',
            ),
            normalizationContext: [
                'groups' => ['chat:message:read'],
            ],
            denormalizationContext: [
                'groups' => ['chat:message:write'],
            ],
            security: "is_granted('ROLE_USER')",
            input: ChatMessageInput::class,
            output: ChatMessageIdOutput::class,
            processor: ChatMessagePostProcessor::class,
        ),
        new Get(
            uriTemplate: '/driver-alerts/{alertId}/messages',
            openapi: new Operation(
                responses: [
                    '200' => new Response('Messages list'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Not a chat participant'),
                    '404' => new Response('Alert not found'),
                ],
                summary: 'Get messages for an alert chat thread',
                description: 'Returns paginated, decrypted messages. Only the distressed driver, responding driver, or a school admin may read. Supports `?page=` and `?limit=` (max 50).',
            ),
            normalizationContext: [
                'groups' => ['chat:messages:read'],
            ],
            security: "is_granted('ROLE_USER')",
            output: ChatMessageListOutput::class,
            provider: ChatMessageListProvider::class,
        ),
    ],
)]
final class AlertChatMessage
{
}
