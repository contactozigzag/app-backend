<?php

declare(strict_types=1);

namespace App\Dto\Payment;

use Symfony\Component\Validator\Constraints as Assert;

final class CreatePaymentPreferenceInput
{
    /**
     * ID of the driver who will receive funds (must have a connected Mercado Pago account).
     */
    #[Assert\NotNull]
    #[Assert\Positive]
    public int $driverId;

    /**
     * List of student IDs included in this payment.
     *
     * @var int[]
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Assert\All([
        new Assert\Positive(),
    ])]
    public array $studentIds = [];

    /**
     * Optional route ID — required when the driver uses per-route or per-route-student pricing.
     */
    #[Assert\Positive]
    public ?int $routeId = null;

    /**
     * Human-readable description shown on the Mercado Pago checkout page.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $description;

    /**
     * Client-generated UUID v4 for idempotent payment creation.
     * Duplicate keys within 24 h return the cached result without calling Mercado Pago again.
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $idempotencyKey;
}
