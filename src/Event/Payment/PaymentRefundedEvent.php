<?php

declare(strict_types=1);

namespace App\Event\Payment;

use App\Entity\Payment;
use Symfony\Contracts\EventDispatcher\Event;

class PaymentRefundedEvent extends Event
{
    public const NAME = 'payment.refunded';

    public function __construct(
        private readonly Payment $payment,
        private readonly string $refundAmount,
        private readonly ?string $reason = null
    ) {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getRefundAmount(): string
    {
        return $this->refundAmount;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function isPartialRefund(): bool
    {
        return bccomp($this->refundAmount, $this->payment->getAmount(), 2) < 0;
    }
}
