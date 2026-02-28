<?php

declare(strict_types=1);

namespace App\State\Payment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Message\ProcessWebhookMessage;
use App\Repository\PaymentRepository;
use App\Service\Payment\WebhookValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles POST /api/webhooks/mercadopago.
 *
 * This processor replicates the exact logic of the former WebhookController:
 *  1. Validate HMAC signature — 401 on failure
 *  2. Check event type — 200 "ignored" for non-payment events
 *  3. Extract provider payment ID
 *  4. Resolve internal Payment
 *  5. Dispatch ProcessWebhookMessage to RabbitMQ
 *
 * The endpoint is PUBLIC (no JWT required) — Mercado Pago servers cannot
 * authenticate. Security is enforced entirely by the HMAC signature.
 *
 * Returns null (204-equivalent) — AP4 will map this to HTTP 202 via the
 * operation's status configuration.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class WebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private WebhookValidator $webhookValidator,
        private PaymentRepository $paymentRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $request = $context['request'] instanceof Request ? $context['request'] : null;
        $requestId = $request?->headers->get('x-request-id', 'unknown') ?? 'unknown';

        $this->logger->info('MP webhook received', [
            'request_id' => $requestId,
            'ip' => $request?->getClientIp(),
        ]);

        // ── 1. Signature validation ─────────────────────────────────────────
        if (! $request instanceof Request || ! $this->webhookValidator->isValid($request)) {
            $this->logger->warning('MP webhook signature validation failed', [
                'request_id' => $requestId,
                'ip' => $request?->getClientIp(),
            ]);

            throw new UnauthorizedHttpException('Bearer realm="MP-Webhook"', 'Invalid signature.');
        }

        // ── 2. Parse payload ────────────────────────────────────────────────
        $webhookData = json_decode((string) $request->getContent(), true);

        if (! is_array($webhookData)) {
            $this->logger->warning('MP webhook: empty or invalid JSON payload', [
                'request_id' => $requestId,
            ]);
            // Return silently — malformed payloads should not cause retries
            return null;
        }

        // ── 3. Event type filter ────────────────────────────────────────────
        if (! $this->webhookValidator->isPaymentEvent($webhookData)) {
            $this->logger->info('MP webhook: non-payment event, ignoring', [
                'request_id' => $requestId,
                'type' => $webhookData['type'] ?? 'unknown',
            ]);

            return null;
        }

        // ── 4. Extract provider payment ID ──────────────────────────────────
        $paymentProviderId = $this->webhookValidator->extractPaymentId($webhookData);

        if ($paymentProviderId === null) {
            $this->logger->warning('MP webhook: could not extract payment ID', [
                'request_id' => $requestId,
                'data' => $webhookData,
            ]);

            return null;
        }

        // ── 5. Resolve internal payment ─────────────────────────────────────
        $payment = $this->paymentRepository->findByPaymentProviderId($paymentProviderId);

        $externalRef = $webhookData['data']['external_reference'] ?? null;
        if (! $payment instanceof Payment && is_numeric($externalRef)) {
            $payment = $this->paymentRepository->find((int) $externalRef);
        }

        if ($payment === null) {
            $this->logger->warning('MP webhook: payment not found, acknowledging without processing', [
                'request_id' => $requestId,
                'payment_provider_id' => $paymentProviderId,
            ]);

            return null;
        }

        // ── 6. Enqueue async processing ─────────────────────────────────────
        $this->messageBus->dispatch(new ProcessWebhookMessage(
            paymentId: intval($payment->getId()),
            paymentProviderId: $paymentProviderId,
            webhookData: $webhookData,
            requestId: $requestId,
        ));

        $this->logger->info('MP webhook enqueued for async processing', [
            'request_id' => $requestId,
            'payment_id' => $payment->getId(),
        ]);

        return null;
    }
}
