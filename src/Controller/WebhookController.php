<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Payment;
use App\Message\ProcessWebhookMessage;
use App\Repository\PaymentRepository;
use App\Service\Payment\WebhookValidator;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives Mercado Pago webhook notifications and enqueues them for async
 * processing. The only work done synchronously is:
 *   1. Signature validation (fast, cryptographic)
 *   2. Payload parsing and event-type check
 *   3. Payment existence check (one indexed DB query)
 *   4. Message dispatch to RabbitMQ
 *
 * Everything else (MP API call, DB writes, Mercure publish) happens in the
 * worker via ProcessWebhookMessageHandler, keeping this endpoint well under
 * the ~500 ms window before Mercado Pago marks a delivery as failed.
 */
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookValidator $webhookValidator,
        private readonly PaymentRepository $paymentRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/webhooks/mercadopago', name: 'api_webhook_mercadopago', methods: ['POST'])]
    public function handleMercadoPago(Request $request): JsonResponse
    {
        $requestId = $request->headers->get('x-request-id', 'unknown');

        $this->logger->info('MP webhook received', [
            'request_id' => $requestId,
            'ip' => $request->getClientIp(),
        ]);

        // ── 1. Signature validation ───────────────────────────────────────────
        if (! $this->webhookValidator->isValid($request)) {
            $this->logger->warning('MP webhook signature validation failed', [
                'request_id' => $requestId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'error' => 'Invalid signature',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // ── 2. Parse payload ──────────────────────────────────────────────
            $webhookData = json_decode($request->getContent(), true);

            if (! $webhookData) {
                $this->logger->warning('MP webhook: empty or invalid JSON payload', [
                    'request_id' => $requestId,
                ]);

                return new JsonResponse([
                    'error' => 'Invalid payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            // ── 3a. Event type filter ─────────────────────────────────────────
            if (! $this->webhookValidator->isPaymentEvent($webhookData)) {
                $this->logger->info('MP webhook: non-payment event, ignoring', [
                    'request_id' => $requestId,
                    'type' => $webhookData['type'] ?? 'unknown',
                ]);

                return new JsonResponse([
                    'status' => 'ignored',
                ], Response::HTTP_OK);
            }

            // ── 3b. Extract provider payment ID ──────────────────────────────
            $paymentProviderId = $this->webhookValidator->extractPaymentId($webhookData);

            if ($paymentProviderId === null) {
                $this->logger->warning('MP webhook: could not extract payment ID', [
                    'request_id' => $requestId,
                    'data' => $webhookData,
                ]);

                return new JsonResponse([
                    'error' => 'Missing payment ID',
                ], Response::HTTP_BAD_REQUEST);
            }

            // ── 3c. Resolve internal payment ──────────────────────────────────
            // Try provider ID first (set after the first approved webhook),
            // then fall back to the external_reference we stamped on the preference.
            $payment = $this->paymentRepository->findByPaymentProviderId($paymentProviderId);

            if (! $payment instanceof Payment && isset($webhookData['data']['external_reference'])) {
                $payment = $this->paymentRepository->find(
                    (int) $webhookData['data']['external_reference']
                );
            }

            if ($payment === null) {
                // Return 200 so MP stops retrying for a payment we don't know about.
                $this->logger->warning('MP webhook: payment not found, acknowledging without processing', [
                    'request_id' => $requestId,
                    'payment_provider_id' => $paymentProviderId,
                    'external_reference' => $webhookData['data']['external_reference'] ?? null,
                ]);

                return new JsonResponse([
                    'status' => 'payment_not_found',
                ], Response::HTTP_OK);
            }

            // ── 4. Enqueue async processing ───────────────────────────────────
            $this->messageBus->dispatch(new ProcessWebhookMessage(
                paymentId: $payment->getId(),
                paymentProviderId: $paymentProviderId,
                webhookData: $webhookData,
                requestId: $requestId,
            ));

            $this->logger->info('MP webhook enqueued for async processing', [
                'request_id' => $requestId,
                'payment_id' => $payment->getId(),
            ]);

            return new JsonResponse([
                'status' => 'received',
            ], Response::HTTP_OK);
        } catch (Exception $exception) {
            $this->logger->error('MP webhook controller error', [
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Always return 200 — a 5xx would cause MP to retry, flooding the queue
            // with messages we may not be able to process during an outage.
            return new JsonResponse([
                'status' => 'error',
            ], Response::HTTP_OK);
        }
    }
}
