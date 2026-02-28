<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\Payment\MercadoPagoWebhookInput;
use App\State\Payment\WebhookProcessor;
use ArrayObject;

/**
 * Virtual resource representing a Mercado Pago webhook notification.
 *
 * There is no backing Doctrine entity — Mercado Pago POSTs events to this
 * endpoint and we immediately enqueue them to RabbitMQ for async processing.
 *
 * Security: PUBLIC_ACCESS (no JWT). Authentication is performed by HMAC
 * signature validation inside WebhookProcessor. This matches the access_control
 * rule `path: ^/api/webhooks, roles: PUBLIC_ACCESS` in security.yaml.
 *
 * Response: 202 Accepted (fire-and-forget). The body is intentionally empty.
 *
 * MP Webhook payload documentation:
 * https://www.mercadopago.com.ar/developers/en/docs/your-integrations/notifications/webhooks
 *
 * Expected JSON body:
 * ```json
 * {
 *   "action": "payment.updated",
 *   "api_version": "v1",
 *   "data": { "id": "12345678" },
 *   "date_created": "2025-01-01T00:00:00.000-04:00",
 *   "id": 12345678,
 *   "live_mode": true,
 *   "type": "payment",
 *   "user_id": "12345678"
 * }
 * ```
 */
#[ApiResource(
    description: 'Receives and enqueues Mercado Pago webhook notifications.',
    operations: [
        new Post(
            uriTemplate: '/webhooks/mercadopago',
            status: 202,
            openapi: new Operation(
                responses: [
                    '202' => new Response('Accepted — event enqueued for async processing'),
                    '401' => new Response('Invalid HMAC signature'),
                ],
                summary: 'Receive Mercado Pago webhook',
                description: <<<'MD'
Receives a Mercado Pago payment notification. The request body is validated
using an HMAC-SHA256 signature carried in the `x-signature` and `x-request-id`
headers. Only payment-type events are processed; all other types are silently
acknowledged.

**Authentication:** None (JWT not required). Security is enforced by the
HMAC signature. If the signature is invalid the endpoint returns 401.
MD,
                requestBody: new RequestBody(
                    description: 'Mercado Pago webhook payload',
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'action' => [
                                        'type' => 'string',
                                        'example' => 'payment.updated',
                                    ],
                                    'api_version' => [
                                        'type' => 'string',
                                        'example' => 'v1',
                                    ],
                                    'data' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => [
                                                'type' => 'string',
                                                'example' => '12345678',
                                            ],
                                        ],
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'example' => 'payment',
                                    ],
                                    'live_mode' => [
                                        'type' => 'boolean',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('PUBLIC_ACCESS')",
            input: MercadoPagoWebhookInput::class,
            output: false,
            deserialize: false,
            validate: false,
            processor: WebhookProcessor::class,
        ),
    ],
)]
final class MercadoPagoWebhook
{
}
