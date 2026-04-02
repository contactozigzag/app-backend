<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\CheckPushReceipts;
use DateTimeImmutable;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('push_notifications')]
readonly class PushNotificationScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(
                RecurringMessage::every(
                    '15 minutes',
                    new CheckPushReceipts(olderThan: new DateTimeImmutable('-15 minutes')),
                )
            )
            ->stateful($this->cache);
    }
}
