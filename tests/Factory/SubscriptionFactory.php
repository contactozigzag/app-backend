<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Subscription;
use App\Enum\BillingCycle;
use App\Enum\SubscriptionStatus;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Subscription>
 */
final class SubscriptionFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Subscription::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'driver' => DriverFactory::new(),
            'planType' => 'Basic',
            'status' => SubscriptionStatus::ACTIVE,
            'amount' => '1500.00',
            'currency' => 'ARS',
            'billingCycle' => BillingCycle::MONTHLY,
            'nextBillingDate' => new DateTimeImmutable('+1 month'),
        ];
    }

    public function active(): static
    {
        return $this->with([
            'status' => SubscriptionStatus::ACTIVE,
        ]);
    }

    public function cancelled(): static
    {
        return $this->with([
            'status' => SubscriptionStatus::CANCELLED,
        ]);
    }
}
