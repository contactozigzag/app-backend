<?php

declare(strict_types=1);

namespace App\Service\OpenSearch;

/**
 * Immutable DTO representing a single search hit from the drivers index.
 */
final readonly class DriverSearchHit
{
    public function __construct(
        public int $driverId,
        public string $nickname,
        public string $firstName,
        public string $lastName,
        public string $identificationNumber,
        public float $score,
    ) {
    }
}
