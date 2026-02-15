<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PaymentTransaction;
use App\Enum\PaymentStatus;
use App\Enum\TransactionEvent;
use App\Event\Payment\PaymentApprovedEvent;
use App\Event\Payment\PaymentFailedEvent;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentProcessor;
use App\Service\Payment\WebhookValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookValidator $webhookValidator,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/api/webhooks/mercadopago', name: 'api_webhook_mercadopago', methods: ['POST'])]
    public function handleMercadoPago(Request $request): JsonResponse
    {
        $requestId = $request->headers->get('x-request-id', 'unknown');

        $this->logger->info('Mercado Pago webhook received', [
            'request_id' => $requestId,
            'ip' => $request->getClientIp(),
        ]);

        // Validate webhook signature
        if (!$this->webhookValidator->isValid($request)) {
            $this->logger->warning('Webhook signature validation failed', [
                'request_id' => $requestId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'error' => 'Invalid signature',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $webhookData = json_decode($request->getContent(), true);

            if (!$webhookData) {
                $this->logger->warning('Invalid webhook payload', [
                    'request_id' => $requestId,
                ]);

                return new JsonResponse([
                    'error' => 'Invalid payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if this is a payment event
            if (!$this->webhookValidator->isPaymentEvent($webhookData)) {
                $this->logger->info('Non-payment webhook event received', [
                    'request_id' => $requestId,
                    'type' => $webhookData['type'] ?? 'unknown',
                ]);

                // Return 200 to acknowledge receipt
                return new JsonResponse(['status' => 'ignored'], Response::HTTP_OK);
            }

            // Extract payment ID
            $paymentProviderId = $this->webhookValidator->extractPaymentId($webhookData);

            if (!$paymentProviderId) {
                $this->logger->warning('Could not extract payment ID from webhook', [
                    'request_id' => $requestId,
                    'data' => $webhookData,
                ]);

                return new JsonResponse([
                    'error' => 'Missing payment ID',
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->logger->info('Processing webhook for payment', [
                'request_id' => $requestId,
                'payment_provider_id' => $paymentProviderId,
            ]);

            // Find payment by provider ID or external reference
            $payment = $this->paymentRepository->findByPaymentProviderId($paymentProviderId);

            if (!$payment && isset($webhookData['data']['external_reference'])) {
                $payment = $this->paymentRepository->find((int) $webhookData['data']['external_reference']);
            }

            if (!$payment) {
                $this->logger->warning('Payment not found for webhook', [
                    'request_id' => $requestId,
                    'payment_provider_id' => $paymentProviderId,
                    'external_reference' => $webhookData['data']['external_reference'] ?? null,
                ]);

                // Return 200 to prevent retries for non-existent payments
                return new JsonResponse(['status' => 'payment_not_found'], Response::HTTP_OK);
            }

            // Process webhook asynchronously to return 200 quickly
            // In production, you would dispatch this to RabbitMQ
            // For now, process synchronously
            $this->processWebhook($payment, $paymentProviderId, $webhookData, $requestId);

            $this->logger->info('Webhook processed successfully', [
                'request_id' => $requestId,
                'payment_id' => $payment->getId(),
            ]);

            return new JsonResponse(['status' => 'received'], Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent infinite retries
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Processing failed',
            ], Response::HTTP_OK);
        }
    }

    private function processWebhook(
        \App\Entity\Payment $payment,
        string $paymentProviderId,
        array $webhookData,
        string $requestId
    ): void {
        try {
            // Update payment from webhook
            $oldStatus = $payment->getStatus();
            $payment = $this->paymentProcessor->updatePaymentFromWebhook(
                $payment,
                $paymentProviderId,
                $webhookData
            );
            $newStatus = $payment->getStatus();

            // Log webhook transaction
            $transaction = new PaymentTransaction();
            $transaction->setPayment($payment);
            $transaction->setEventType(TransactionEvent::WEBHOOK_RECEIVED);
            $transaction->setStatus($newStatus);
            $transaction->setProviderResponse($webhookData);
            $transaction->setNotes("Webhook request ID: {$requestId}");

            $payment->addTransaction($transaction);
            $this->entityManager->flush();

            // Dispatch events based on status changes
            if ($oldStatus !== $newStatus) {
                if ($newStatus === PaymentStatus::APPROVED) {
                    $this->eventDispatcher->dispatch(
                        new PaymentApprovedEvent($payment),
                        PaymentApprovedEvent::NAME
                    );
                } elseif ($newStatus === PaymentStatus::REJECTED || $newStatus === PaymentStatus::CANCELLED) {
                    $this->eventDispatcher->dispatch(
                        new PaymentFailedEvent($payment, $webhookData['status_detail'] ?? null),
                        PaymentFailedEvent::NAME
                    );
                }
            }

            $this->logger->info('Webhook processing completed', [
                'payment_id' => $payment->getId(),
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook for payment', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
