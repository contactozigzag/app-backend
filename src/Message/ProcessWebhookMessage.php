<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched by WebhookController immediately after validating an MP webhook.
 * Carried over RabbitMQ so the HTTP response is returned in < 1 s regardless
 * of how long the status fetch + DB write takes.
 *
 * All fields are primitive so the message serialises cleanly to JSON for AMQP.
 */
final readonly class ProcessWebhookMessage
{
    public function __construct(
        /** Our internal payment.id — used by the handler to reload the entity. */
        public int $paymentId,

        /** Mercado Pago's payment identifier — used to fetch the authoritative status. */
        public string $paymentProviderId,

        /** Raw webhook payload stored in the PaymentTransaction for audit. */
        public array $webhookData,

        /** MP's x-request-id header, threaded through for log correlation. */
        public string $requestId,
    ) {
    }
}
