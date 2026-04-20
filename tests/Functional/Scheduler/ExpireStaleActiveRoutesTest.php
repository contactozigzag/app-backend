<?php

declare(strict_types=1);

namespace App\Tests\Functional\Scheduler;

use App\Entity\ActiveRoute;
use App\Repository\ActiveRouteRepository;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\ActiveRouteFactory;
use DateTimeImmutable;

/**
 * Guards the repo-side filters that protect live views (dashboards, anomaly
 * detection, geofencing) from zombie rows — trips a driver never marked
 * completed. Handler cancellation logic is covered by the unit test at
 * tests/Unit/MessageHandler/ExpireStaleActiveRoutesHandlerTest.php.
 */
final class ExpireStaleActiveRoutesTest extends AbstractApiTestCase
{
    public function testFindInProgressExcludesZombies(): void
    {
        $this->createApiClient();

        $aWeekAgo = new DateTimeImmutable('-7 days');
        $today = new DateTimeImmutable('today');

        $zombie = ActiveRouteFactory::new()->with([
            'status' => 'in_progress',
            'date' => $aWeekAgo,
        ])->create();

        $live = ActiveRouteFactory::new()->with([
            'status' => 'in_progress',
            'date' => $today,
        ])->create();

        /** @var ActiveRouteRepository $repo */
        $repo = self::getContainer()->get(ActiveRouteRepository::class);

        $ids = array_map(fn (ActiveRoute $r): ?int => $r->getId(), $repo->findInProgress());

        $this->assertContains($live->getId(), $ids);
        $this->assertNotContains($zombie->getId(), $ids);
    }

    public function testFindStaleNonTerminalReturnsOnlyPastNonTerminalRows(): void
    {
        $this->createApiClient();

        $yesterday = new DateTimeImmutable('yesterday');
        $aWeekAgo = new DateTimeImmutable('-7 days');
        $today = new DateTimeImmutable('today');

        $zombieInProgress = ActiveRouteFactory::new()->with([
            'status' => 'in_progress',
            'date' => $aWeekAgo,
        ])->create();

        $zombieScheduled = ActiveRouteFactory::new()->with([
            'status' => 'scheduled',
            'date' => $yesterday,
        ])->create();

        $liveToday = ActiveRouteFactory::new()->with([
            'status' => 'in_progress',
            'date' => $today,
        ])->create();

        $completedLastWeek = ActiveRouteFactory::new()->with([
            'status' => 'completed',
            'date' => $aWeekAgo,
            'startedAt' => $aWeekAgo,
            'completedAt' => $aWeekAgo,
        ])->create();

        /** @var ActiveRouteRepository $repo */
        $repo = self::getContainer()->get(ActiveRouteRepository::class);

        $ids = array_map(fn (ActiveRoute $r): ?int => $r->getId(), $repo->findStaleNonTerminal());

        $this->assertContains($zombieInProgress->getId(), $ids);
        $this->assertContains($zombieScheduled->getId(), $ids);
        $this->assertNotContains($liveToday->getId(), $ids);
        $this->assertNotContains($completedLastWeek->getId(), $ids);
    }
}
