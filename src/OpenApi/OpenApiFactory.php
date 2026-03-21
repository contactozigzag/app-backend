<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Adds custom (non-API-Platform) endpoints to the OpenAPI specification.
 *
 * Controllers that use plain Symfony #[Route] attributes are not picked up
 * by API Platform's OpenAPI generator. This decorator appends them manually
 * so the frontend can consume a complete spec.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $this->addOAuthEndpoints($openApi);
        $this->addMercureTokenEndpoint($openApi);

        return $openApi;
    }

    private function addOAuthEndpoints(OpenApi $openApi): void
    {
        // GET /oauth/mercadopago/connect
        $openApi->getPaths()->addPath('/oauth/mercadopago/connect', new PathItem(
            get: new Operation(
                operationId: 'oauthMercadoPagoConnect',
                tags: ['MercadoPago OAuth'],
                responses: [
                    '302' => new Response(
                        description: 'Redirects the driver to Mercado Pago authorization page.',
                    ),
                    '401' => new Response(description: 'Unauthorized — JWT token missing or invalid.'),
                    '403' => new Response(description: 'Forbidden — requires ROLE_DRIVER.'),
                    '404' => new Response(description: 'No driver profile found for this user.'),
                ],
                summary: 'Initiate MercadoPago OAuth flow',
                description: 'Generates a CSRF state, stores it in Redis, then redirects the browser to Mercado Pago\'s authorization page. Requires ROLE_DRIVER.',
                security: [[
                    'JWT' => [],
                ]],
            ),
        ));

        // GET /oauth/mercadopago/callback
        $openApi->getPaths()->addPath('/oauth/mercadopago/callback', new PathItem(
            get: new Operation(
                operationId: 'oauthMercadoPagoCallback',
                tags: ['MercadoPago OAuth'],
                responses: [
                    '200' => new Response(
                        description: 'OAuth completed successfully.',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Mercado Pago account connected successfully.',
                                        ],
                                        'driver_id' => [
                                            'type' => 'integer',
                                            'example' => 42,
                                        ],
                                        'mp_account_id' => [
                                            'type' => 'string',
                                            'example' => '123456789',
                                            'nullable' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                    '400' => new Response(
                        description: 'Authorization denied or missing parameters.',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                ],
                summary: 'MercadoPago OAuth callback',
                description: 'Handles the redirect back from Mercado Pago with an authorization code. This is a PUBLIC endpoint — the browser is redirected here by MP without the driver\'s JWT. The driver is identified via the cryptographic state parameter.',
                parameters: [
                    new Parameter(name: 'code', in: 'query', description: 'Authorization code from Mercado Pago', required: false, schema: [
                        'type' => 'string',
                    ]),
                    new Parameter(name: 'state', in: 'query', description: 'CSRF state token', required: false, schema: [
                        'type' => 'string',
                    ]),
                    new Parameter(name: 'error', in: 'query', description: 'Error code if driver denied access', required: false, schema: [
                        'type' => 'string',
                    ]),
                    new Parameter(name: 'error_description', in: 'query', description: 'Error description from Mercado Pago', required: false, schema: [
                        'type' => 'string',
                    ]),
                ],
                security: [],
            ),
        ));

        // GET /oauth/mercadopago/status
        $openApi->getPaths()->addPath('/oauth/mercadopago/status', new PathItem(
            get: new Operation(
                operationId: 'oauthMercadoPagoStatus',
                tags: ['MercadoPago OAuth'],
                responses: [
                    '200' => new Response(
                        description: 'Connection status returned.',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'connected' => [
                                            'type' => 'boolean',
                                            'example' => true,
                                        ],
                                        'mp_account_id' => [
                                            'type' => 'string',
                                            'example' => '123456789',
                                            'nullable' => true,
                                        ],
                                        'token_expires_at' => [
                                            'type' => 'string',
                                            'format' => 'date-time',
                                            'nullable' => true,
                                        ],
                                        'needs_refresh' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                    '401' => new Response(description: 'Unauthorized — JWT token missing or invalid.'),
                    '403' => new Response(description: 'Forbidden — requires ROLE_DRIVER.'),
                    '404' => new Response(description: 'No driver profile found for this user.'),
                ],
                summary: 'Get MercadoPago OAuth connection status',
                description: 'Returns the OAuth connection status for the authenticated driver. Useful for the frontend to decide whether to show the "Connect MercadoPago" button. Requires ROLE_DRIVER.',
                security: [[
                    'JWT' => [],
                ]],
            ),
        ));
    }

    private function addMercureTokenEndpoint(OpenApi $openApi): void
    {
        $openApi->getPaths()->addPath('/api/mercure/token', new PathItem(
            get: new Operation(
                operationId: 'getMercureSubscriberToken',
                tags: ['Mercure'],
                responses: [
                    '200' => new Response(
                        description: 'Mercure subscriber JWT returned.',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => [
                                            'type' => 'string',
                                            'description' => 'Mercure subscriber JWT (HMAC-SHA256 signed)',
                                        ],
                                        'hub_url' => [
                                            'type' => 'string',
                                            'format' => 'uri',
                                            'description' => 'Public URL of the Mercure hub',
                                        ],
                                        'topics' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string',
                                            ],
                                            'example' => ['/payments/42'],
                                            'description' => 'Topics the token authorizes subscription to',
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                    '400' => new Response(
                        description: 'Missing or invalid payment_id.',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Missing or invalid payment_id query parameter.',
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                    '401' => new Response(description: 'Unauthorized — JWT token missing or invalid.'),
                    '403' => new Response(description: 'Access denied — payment belongs to another user.'),
                    '404' => new Response(description: 'Payment not found.'),
                ],
                summary: 'Get a Mercure subscriber JWT for payment updates',
                description: 'Issues a short-lived (1 hour) Mercure subscriber JWT scoped to a single payment topic (/payments/{id}). The client uses this token to subscribe to real-time payment status updates from the Mercure hub. Requires ROLE_USER.',
                parameters: [
                    new Parameter(name: 'payment_id', in: 'query', description: 'ID of the payment to subscribe to', required: true, schema: [
                        'type' => 'integer',
                    ]),
                ],
                security: [[
                    'JWT' => [],
                ]],
            ),
        ));
    }
}
