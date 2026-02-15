<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\ProcessSubscriptionsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('subscription_processing')]
class SubscriptionScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Process subscriptions every 5 minutes
                RecurringMessage::every(
                    '5 minutes',
                    new ProcessSubscriptionsMessage(
                        limit: 100,
                        processRetries: true
                    )
                )
            )
            ->stateful(
                // Persist schedule state to avoid duplicate execution
                store: 'cache.app'
            );
    }
}
