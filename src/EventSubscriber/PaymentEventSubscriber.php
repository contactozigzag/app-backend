<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Payment\PaymentApprovedEvent;
use App\Event\Payment\PaymentCreatedEvent;
use App\Event\Payment\PaymentFailedEvent;
use App\Event\Payment\PaymentRefundedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

readonly class PaymentEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCreatedEvent::NAME => 'onPaymentCreated',
            PaymentApprovedEvent::NAME => 'onPaymentApproved',
            PaymentFailedEvent::NAME => 'onPaymentFailed',
            PaymentRefundedEvent::NAME => 'onPaymentRefunded',
        ];
    }

    public function onPaymentCreated(PaymentCreatedEvent $event): void
    {
        $payment = $event->getPayment();

        $this->logger->info('Payment created event received', [
            'payment_id' => $payment->getId(),
            'user_id' => $payment->getUser()?->getId(),
        ]);

        $this->publishUpdate((int) $payment->getId(), [
            'event' => 'payment.created',
            'payment_id' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'timestamp' => time(),
        ]);
    }

    public function onPaymentApproved(PaymentApprovedEvent $event): void
    {
        $payment = $event->getPayment();

        $this->logger->info('Payment approved event received', [
            'payment_id' => $payment->getId(),
            'user_id' => $payment->getUser()?->getId(),
        ]);

        $this->publishUpdate((int) $payment->getId(), [
            'event' => 'payment.approved',
            'payment_id' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'paid_at' => $payment->getPaidAt()?->format('c'),
            'payment_method' => $payment->getPaymentMethod()?->value,
            'timestamp' => time(),
        ]);
    }

    public function onPaymentFailed(PaymentFailedEvent $event): void
    {
        $payment = $event->getPayment();

        $this->logger->info('Payment failed event received', [
            'payment_id' => $payment->getId(),
            'user_id' => $payment->getUser()?->getId(),
            'reason' => $event->getReason(),
        ]);

        $this->publishUpdate((int) $payment->getId(), [
            'event' => 'payment.failed',
            'payment_id' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'reason' => $event->getReason(),
            'timestamp' => time(),
        ]);
    }

    public function onPaymentRefunded(PaymentRefundedEvent $event): void
    {
        $payment = $event->getPayment();

        $this->logger->info('Payment refunded event received', [
            'payment_id' => $payment->getId(),
            'user_id' => $payment->getUser()?->getId(),
            'refund_amount' => $event->getRefundAmount(),
            'is_partial' => $event->isPartialRefund(),
        ]);

        $this->publishUpdate((int) $payment->getId(), [
            'event' => 'payment.refunded',
            'payment_id' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'refund_amount' => $event->getRefundAmount(),
            'total_refunded' => $payment->getRefundedAmount(),
            'is_partial' => $event->isPartialRefund(),
            'reason' => $event->getReason(),
            'timestamp' => time(),
        ]);
    }

    private function publishUpdate(int $paymentId, array $data): void
    {
        try {
            $update = new Update(
                topics: ['/payments/' . $paymentId],
                data: (string) json_encode($data),
                private: true
            );

            $this->hub->publish($update);

            $this->logger->debug('Published Mercure update for payment', [
                'payment_id' => $paymentId,
                'event' => $data['event'],
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to publish Mercure update', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
