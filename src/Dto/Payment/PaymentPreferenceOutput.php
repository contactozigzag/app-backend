<?php

declare(strict_types=1);

namespace App\Dto\Payment;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class PaymentPreferenceOutput
{
    public function __construct(
        #[Groups(['payment:preference:read'])]
        public int $paymentId,
        #[Groups(['payment:preference:read'])]
        public string $preferenceId,
        #[Groups(['payment:preference:read'])]
        public string $initPoint,
        #[Groups(['payment:preference:read'])]
        public ?string $sandboxInitPoint,
        #[Groups(['payment:preference:read'])]
        public string $status,
        #[Groups(['payment:preference:read'])]
        public string $amount,
        #[Groups(['payment:preference:read'])]
        public string $currency,
        #[Groups(['payment:preference:read'])]
        public ?string $expiresAt,
    ) {
    }
}
