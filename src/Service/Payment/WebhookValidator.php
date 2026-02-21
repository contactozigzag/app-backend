<?php

declare(strict_types=1);

namespace App\Service\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class WebhookValidator
{
    private const int TIMESTAMP_TOLERANCE = 300; // 5 minutes

    public function __construct(
        #[Autowire(env: 'MERCADOPAGO_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Validate Mercado Pago webhook signature
     */
    public function isValid(Request $request): bool
    {
        $xSignature = $request->headers->get('x-signature');
        $xRequestId = $request->headers->get('x-request-id');

        if (! $xSignature || ! $xRequestId) {
            $this->logger->warning('Webhook missing required headers', [
                'has_signature' => $xSignature !== null,
                'has_request_id' => $xRequestId !== null,
            ]);

            return false;
        }

        // Parse x-signature header
        // Format: ts=1234567890,v1=abcdef123456...
        $signatureParts = $this->parseSignatureHeader($xSignature);

        if (! isset($signatureParts['ts'], $signatureParts['v1'])) {
            $this->logger->warning('Invalid signature format', [
                'x_signature' => $xSignature,
            ]);

            return false;
        }

        $timestamp = (int) $signatureParts['ts'];
        $signature = $signatureParts['v1'];

        // Check timestamp to prevent replay attacks
        if (! $this->isTimestampValid($timestamp)) {
            $this->logger->warning('Webhook timestamp outside tolerance window', [
                'timestamp' => $timestamp,
                'current' => time(),
                'diff' => time() - $timestamp,
            ]);

            return false;
        }

        // Get request body
        $request->getContent();

        // Construct signed data: id + request_id + timestamp + body
        $dataId = $request->query->get('id') ?? $request->request->get('id') ?? '';
        $signedData = sprintf('id:%s;request-id:%s;ts:%d;', $dataId, $xRequestId, $timestamp);

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $signedData, $this->webhookSecret);

        // Compare signatures
        if (! hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('Webhook signature mismatch', [
                'expected' => $expectedSignature,
                'received' => $signature,
                'data_id' => $dataId,
                'request_id' => $xRequestId,
            ]);

            return false;
        }

        $this->logger->info('Webhook signature validated successfully', [
            'request_id' => $xRequestId,
            'data_id' => $dataId,
        ]);

        return true;
    }

    /**
     * Parse x-signature header into components
     */
    private function parseSignatureHeader(string $header): array
    {
        $parts = [];
        $segments = explode(',', $header);

        foreach ($segments as $segment) {
            $keyValue = explode('=', $segment, 2);
            if (count($keyValue) === 2) {
                $parts[trim($keyValue[0])] = trim($keyValue[1]);
            }
        }

        return $parts;
    }

    /**
     * Check if timestamp is within acceptable range
     */
    private function isTimestampValid(int $timestamp): bool
    {
        $currentTime = time();
        $timeDiff = abs($currentTime - $timestamp);

        return $timeDiff <= self::TIMESTAMP_TOLERANCE;
    }

    /**
     * Extract payment ID from webhook data
     */
    public function extractPaymentId(array $data): ?string
    {
        // Mercado Pago webhook structure
        // data.id contains the payment ID
        return $data['data']['id'] ?? null;
    }

    /**
     * Validate webhook event type
     */
    public function isPaymentEvent(array $data): bool
    {
        $type = $data['type'] ?? '';
        $action = $data['action'] ?? '';

        // Check for payment-related events
        return $type === 'payment' && in_array($action, ['payment.created', 'payment.updated'], true);
    }
}
