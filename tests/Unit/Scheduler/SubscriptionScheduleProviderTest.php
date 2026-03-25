<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Scheduler\SubscriptionScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class SubscriptionScheduleProviderTest extends TestCase
{
    public function testScheduleContainsOneRecurringMessage(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $provider = new SubscriptionScheduleProvider($cache);

        $schedule = $provider->getSchedule();

        self::assertCount(1, $schedule->getRecurringMessages());
    }
}
