<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Scheduler\PaymentScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class PaymentScheduleProviderTest extends TestCase
{
    public function testScheduleContainsOneRecurringMessage(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $provider = new PaymentScheduleProvider($cache);

        $schedule = $provider->getSchedule();

        $this->assertCount(1, $schedule->getRecurringMessages());
    }
}
