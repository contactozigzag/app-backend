<?php

declare(strict_types=1);

namespace App\Dto\Payment;

/**
 * Represents the Mercado Pago webhook notification payload.
 *
 * MP sends the following structure for payment events:
 * {
 *   "action": "payment.updated",
 *   "api_version": "v1",
 *   "data": { "id": "12345678" },
 *   "date_created": "2025-01-01T00:00:00.000-04:00",
 *   "id": 12345678,
 *   "live_mode": true,
 *   "type": "payment",
 *   "user_id": "12345678"
 * }
 *
 * Signature validation is done inside the processor before any field access.
 * This DTO is intentionally kept permissive (`mixed $data`) because the raw
 * body is validated cryptographically, not structurally.
 */
final class MercadoPagoWebhookInput
{
    public ?string $action = null;

    public ?string $apiVersion = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = null;

    public ?string $dateCreated = null;

    public int|string|null $id = null;

    public ?bool $liveMode = null;

    public ?string $type = null;

    public int|string|null $userId = null;
}
