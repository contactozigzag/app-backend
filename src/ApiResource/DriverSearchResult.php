<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\DriverSearch\DriverSearchProvider;

#[ApiResource(
    shortName: 'DriverSearch',
    operations: [
        new GetCollection(
            uriTemplate: '/drivers/search',
            outputFormats: [
                'json' => ['application/json'],
            ],
            openapi: new Operation(
                summary: 'Search for drivers by name, nickname, or identification number',
                parameters: [
                    new Parameter(name: 'q', in: 'query', required: true, schema: [
                        'type' => 'string',
                    ]),
                    new Parameter(name: 'school', in: 'query', description: 'School ID (required for parents, ignored for school admins)', required: false, schema: [
                        'type' => 'integer',
                    ]),
                    new Parameter(name: 'page', in: 'query', required: false, schema: [
                        'type' => 'integer',
                        'default' => 1,
                    ]),
                    new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: [
                        'type' => 'integer',
                        'default' => 10,
                    ]),
                ],
            ),
            paginationEnabled: false,
            security: "is_granted('ROLE_PARENT') or is_granted('ROLE_SCHOOL_ADMIN')",
            provider: DriverSearchProvider::class,
        ),
    ],
)]
final class DriverSearchResult
{
}
