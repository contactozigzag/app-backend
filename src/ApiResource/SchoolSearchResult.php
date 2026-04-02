<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\Response;
use App\State\SchoolSearch\SchoolSearchProvider;
use ArrayObject;

#[ApiResource(
    shortName: 'SchoolSearch',
    operations: [
        new GetCollection(
            uriTemplate: '/schools/search',
            outputFormats: [
                'json' => ['application/json'],
            ],
            openapi: new Operation(
                responses: [
                    '200' => new Response(
                        description: 'School search results',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'results' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'schoolId' => ['type' => 'integer', 'example' => 42],
                                                    'name' => ['type' => 'string', 'example' => 'Escuela San Martín'],
                                                    'city' => ['type' => 'string', 'example' => 'Buenos Aires'],
                                                    'address' => ['type' => 'string', 'example' => 'Av. Corrientes 1234'],
                                                    'score' => ['type' => 'number', 'format' => 'float', 'example' => 9.2],
                                                ],
                                            ],
                                        ],
                                        'total' => ['type' => 'integer', 'example' => 1],
                                        'page' => ['type' => 'integer', 'example' => 1],
                                        'itemsPerPage' => ['type' => 'integer', 'example' => 10],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                ],
                summary: 'Search schools by name',
                description: 'Full-text search powered by OpenSearch with Spanish stop words, asciifolding, and edge-ngram prefix matching. Falls back to a Doctrine LIKE query if OpenSearch is unavailable.',
                parameters: [
                    new Parameter(
                        name: 'q',
                        in: 'query',
                        description: 'Search query (minimum 2 characters)',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                    new Parameter(
                        name: 'page',
                        in: 'query',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'default' => 1,
                        ],
                    ),
                    new Parameter(
                        name: 'itemsPerPage',
                        in: 'query',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'default' => 10,
                        ],
                    ),
                ],
            ),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            provider: SchoolSearchProvider::class,
        ),
    ],
)]
final class SchoolSearchResult
{
}
