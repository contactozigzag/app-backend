<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('messenger_monitor')]
readonly class MessengerMonitorScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(
                // Purge processed message history older than 7 days — hashed midnight to avoid thundering herd
                RecurringMessage::cron('#midnight', new RunCommandMessage('messenger:monitor:purge --older-than=7days')),
            )
            ->add(
                // Purge stale schedule run history, keeping last 10 runs per task
                RecurringMessage::cron('#midnight', new RunCommandMessage('messenger:monitor:schedule:purge')),
            )
            ->stateful($this->cache);
    }
}
