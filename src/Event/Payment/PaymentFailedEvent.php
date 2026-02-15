<?php

declare(strict_types=1);

namespace App\Event\Payment;

use App\Entity\Payment;
use Symfony\Contracts\EventDispatcher\Event;

class PaymentFailedEvent extends Event
{
    public const NAME = 'payment.failed';

    public function __construct(
        private readonly Payment $payment,
        private readonly ?string $reason = null
    ) {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
