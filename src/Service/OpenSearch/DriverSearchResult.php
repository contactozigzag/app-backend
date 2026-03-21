<?php

declare(strict_types=1);

namespace App\Service\OpenSearch;

/**
 * Immutable DTO wrapping paginated search results from the drivers index.
 */
final readonly class DriverSearchResult
{
    /**
     * @param DriverSearchHit[] $results
     */
    public function __construct(
        public array $results,
        public int $total,
        public int $page,
        public int $limit,
    ) {
    }
}
