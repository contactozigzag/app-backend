<?php

declare(strict_types=1);

namespace App\Dto\Payment;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class PaymentStatusOutput
{
    public function __construct(
        #[Groups(['payment:status:read'])]
        public int $paymentId,
        #[Groups(['payment:status:read'])]
        public string $status,
        #[Groups(['payment:status:read'])]
        public ?string $paymentMethod,
        #[Groups(['payment:status:read'])]
        public string $amount,
        #[Groups(['payment:status:read'])]
        public string $currency,
        #[Groups(['payment:status:read'])]
        public ?string $paidAt,
        #[Groups(['payment:status:read'])]
        public string $createdAt,
        #[Groups(['payment:status:read'])]
        public ?string $mercadoPagoId,

        /**
         * @var array{id: int, nickname: string, mpAccountId: string|null}|null
         */
        #[Groups(['payment:status:read'])]
        public ?array $driver,

        /**
         * @var array<int, array{id: int, name: string}>
         */
        #[Groups(['payment:status:read'])]
        public array $students,
    ) {
    }
}
