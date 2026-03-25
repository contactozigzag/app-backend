<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\ProcessSubscriptionsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('subscription_processing')]
class SubscriptionScheduleProvider implements ScheduleProviderInterface
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
                    '5 minutes',
                    new ProcessSubscriptionsMessage(
                        limit: 100,
                        processRetries: true
                    )
                )
            )
            ->stateful($this->cache);
    }
}
