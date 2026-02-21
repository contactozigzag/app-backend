<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Event\Payment\PaymentApprovedEvent;
use App\Event\Payment\PaymentFailedEvent;
use App\Message\ProcessWebhookMessage;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles webhook processing asynchronously.
 *
 * The WebhookController returns HTTP 200 to Mercado Pago as soon as the message
 * is enqueued. This handler runs in a worker process and performs the slow work:
 * fetching the authoritative payment status from the MP API, persisting the
 * PaymentTransaction, and publishing real-time Mercure updates via events.
 *
 * Idempotency: if this handler runs twice for the same webhook (RabbitMQ
 * at-least-once delivery), PaymentProcessor::updatePaymentFromWebhook() is a
 * no-op when the status has not changed, so duplicate delivery is safe.
 */
#[AsMessageHandler]
class ProcessWebhookMessageHandler
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessWebhookMessage $message): void
    {
        $this->logger->info('Webhook handler started', [
            'payment_id' => $message->paymentId,
            'payment_provider_id' => $message->paymentProviderId,
            'request_id' => $message->requestId,
        ]);

        $payment = $this->paymentRepository->find($message->paymentId);

        if ($payment === null) {
            // Payment was deleted between dispatch and handling — safe to discard.
            // Throwing here would trigger AMQP retries with no chance of success.
            $this->logger->warning('Webhook handler: payment not found, discarding message', [
                'payment_id' => $message->paymentId,
                'request_id' => $message->requestId,
            ]);

            return;
        }

        $oldStatus = $payment->getStatus();

        // Fetches authoritative status from MP API, updates entity fields,
        // creates a PaymentTransaction record, and flushes — all in one call.
        $payment = $this->paymentProcessor->updatePaymentFromWebhook(
            $payment,
            $message->paymentProviderId,
            $message->webhookData,
        );

        $newStatus = $payment->getStatus();

        if ($oldStatus !== $newStatus) {
            $this->dispatchStatusEvent($payment, $newStatus, $message->webhookData);
        }

        $this->logger->info('Webhook handler completed', [
            'payment_id' => $message->paymentId,
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
            'request_id' => $message->requestId,
        ]);
    }

    /**
     * @param array<string, mixed> $webhookData
     */
    private function dispatchStatusEvent(
        Payment $payment,
        PaymentStatus $newStatus,
        array $webhookData,
    ): void {
        match ($newStatus) {
            PaymentStatus::APPROVED => $this->eventDispatcher->dispatch(
                new PaymentApprovedEvent($payment),
                PaymentApprovedEvent::NAME,
            ),
            PaymentStatus::REJECTED,
            PaymentStatus::CANCELLED => $this->eventDispatcher->dispatch(
                new PaymentFailedEvent($payment, $webhookData['status_detail'] ?? null),
                PaymentFailedEvent::NAME,
            ),
            default => null,
        };
    }
}
