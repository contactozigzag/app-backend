<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Payment>
 */
final class PaymentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Payment::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'user'           => UserFactory::new(),
            'driver'         => DriverFactory::new()->withMpAuthorized(),
            'amount'         => (string) self::faker()->randomFloat(2, 10, 500),
            'currency'       => 'ARS',
            'status'         => PaymentStatus::PENDING,
            'idempotencyKey' => self::faker()->uuid(),
            'description'    => self::faker()->sentence(),
        ];
    }

    public function withStatus(PaymentStatus $status): static
    {
        return $this->with(['status' => $status]);
    }

    public function withProviderData(string $preferenceId = 'pref-123', string $providerId = 'pay-456'): static
    {
        return $this->with([
            'preferenceId'      => $preferenceId,
            'paymentProviderId' => $providerId,
        ]);
    }
}
