<?php

declare(strict_types=1);

namespace App\Dto\Payment;

final readonly class CalculatedRate
{
    /**
     * @param array<string, mixed> $rateSnapshot
     */
    public function __construct(
        public string $amount,
        public string $currency,
        public array $rateSnapshot,
    ) {
    }
}
