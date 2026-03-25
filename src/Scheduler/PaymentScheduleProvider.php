<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\ExpireStalePaymentsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('payment_maintenance')]
class PaymentScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(
                RecurringMessage::every(
                    '1 hour',
                    new ExpireStalePaymentsMessage(batchSize: 500)
                )
            )
            ->stateful($this->cache);
    }
}
