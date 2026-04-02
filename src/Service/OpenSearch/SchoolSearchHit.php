<?php

declare(strict_types=1);

namespace App\Service\OpenSearch;

/**
 * Immutable DTO representing a single search hit from the schools index.
 */
final readonly class SchoolSearchHit
{
    public function __construct(
        public int $schoolId,
        public string $name,
        public string $city,
        public float $score,
    ) {
    }
}
