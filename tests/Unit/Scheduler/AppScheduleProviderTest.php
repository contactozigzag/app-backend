<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Scheduler\AppScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class AppScheduleProviderTest extends TestCase
{
    public function testScheduleAggregatesAllRecurringMessages(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $provider = new AppScheduleProvider($cache);

        $schedule = $provider->getSchedule();

        // DetectGpsAnomaly, ExpireStalePayments, ProcessSubscriptions,
        // CheckPushReceipts, ExpireStaleActiveRoutes, plus two
        // messenger:monitor purge jobs.
        $this->assertCount(7, $schedule->getRecurringMessages());
    }
}
