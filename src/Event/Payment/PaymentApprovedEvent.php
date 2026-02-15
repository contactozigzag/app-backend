<?php

declare(strict_types=1);

namespace App\Event\Payment;

use App\Entity\Payment;
use Symfony\Contracts\EventDispatcher\Event;

class PaymentApprovedEvent extends Event
{
    public const NAME = 'payment.approved';

    public function __construct(
        private readonly Payment $payment
    ) {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }
}
