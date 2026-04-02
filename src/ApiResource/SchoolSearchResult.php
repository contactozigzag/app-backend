<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\SchoolSearch\SchoolSearchProvider;

#[ApiResource(
    shortName: 'SchoolSearch',
    operations: [
        new GetCollection(
            uriTemplate: '/schools/search',
            outputFormats: [
                'json' => ['application/json'],
            ],
            openapi: new Operation(
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
