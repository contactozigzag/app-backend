<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\CheckPushReceipts;
use App\Message\DetectGpsAnomalyMessage;
use App\Message\ExpireStaleActiveRoutesMessage;
use App\Message\ExpireStalePaymentsMessage;
use App\Message\ProcessSubscriptionsMessage;
use DateTimeImmutable;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Single aggregated Symfony Scheduler provider for the application.
 *
 * ## Why one provider instead of one per concern
 *
 * Every `#[AsSchedule('name')]` creates its own `scheduler_name` transport.
 * The worker must consume every such transport for the schedules to fire —
 * and a missing transport in the worker command fails silently (the schedule
 * is registered but no one dispatches the tick). Keeping all recurring
 * messages under the single `default` name means the worker only ever has
 * to consume `scheduler_default`, so adding a new recurring job is a one-
 * line change here and never requires touching compose files.
 *
 * ## Conventions
 *
 * - Use `every('1 hour')` for interval jobs, `cron('15 3 * * *')` for
 *   time-of-day jobs. `#midnight` (hashed) avoids thundering-herd on
 *   multi-host setups even though we run a single worker today.
 * - Every recurring message must route to an async transport in
 *   `config/packages/messenger.yaml` (usually `async`). The handler runs
 *   in the `async` consumer, not inside the scheduler worker loop.
 * - Do NOT introduce another `ScheduleProvider`. If you need per-feature
 *   cadence tuning, add another `RecurringMessage` below.
 * - Keep the worker a single replica (see `compose.yaml` warning). Symfony
 *   Scheduler has no built-in distributed lock; two replicas double-fire.
 */
#[AsSchedule('default')]
readonly class AppScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            // Scan recent GPS updates for anomalies (speed/stationary/etc.).
            ->add(
                RecurringMessage::every(
                    '60 seconds',
                    new DetectGpsAnomalyMessage()
                )
            )
            // Cancel pending payments whose MP preference has expired.
            ->add(
                RecurringMessage::every(
                    '1 hour',
                    new ExpireStalePaymentsMessage(batchSize: 500)
                )
            )
            // Process due subscription charges (and retries).
            ->add(
                RecurringMessage::every(
                    '5 minutes',
                    new ProcessSubscriptionsMessage(limit: 100, processRetries: true)
                )
            )
            // Poll Expo push receipts for delivery confirmation.
            ->add(
                RecurringMessage::every(
                    '15 minutes',
                    new CheckPushReceipts(olderThan: new DateTimeImmutable('-15 minutes'))
                )
            )
            // Cancel zombie ActiveRoutes (non-terminal rows from past dates)
            // that a driver never completed. Runs early morning UTC so the
            // day's scheduled trips appear clean on dashboards at school start.
            ->add(
                RecurringMessage::cron(
                    '15 3 * * *',
                    new ExpireStaleActiveRoutesMessage(batchSize: 500)
                )
            )
            // Messenger monitor maintenance: prune message history and stale
            // schedule runs. Hashed midnight avoids thundering herd.
            ->add(
                RecurringMessage::cron(
                    '#midnight',
                    new RunCommandMessage('messenger:monitor:purge --older-than=7days')
                )
            )
            ->add(
                RecurringMessage::cron(
                    '#midnight',
                    new RunCommandMessage('messenger:monitor:schedule:purge')
                )
            )
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
