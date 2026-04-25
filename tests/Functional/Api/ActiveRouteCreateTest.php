<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\ActiveRoute;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\ActiveRouteFactory;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\RouteStopFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verifies POST /api/active_routes materializes ActiveRouteStop rows from
 * the route template — without this, parents see "no children to deliver"
 * and the proximity / push pipeline never fires.
 */
final class ActiveRouteCreateTest extends AbstractApiTestCase
{
    public function testPostActiveRouteMaterializesStopsFromTemplate(): void
    {
        $client = $this->createApiClient();

        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $route = RouteFactory::createOne();
        $driver = DriverFactory::createOne();

        $studentA = StudentFactory::createOne();
        $studentB = StudentFactory::createOne();
        $addressA = AddressFactory::createOne();
        $addressB = AddressFactory::createOne();

        RouteStopFactory::new()->with([
            'route' => $route,
            'student' => $studentA,
            'address' => $addressA,
            'stopOrder' => 1,
            'isActive' => true,
        ])->create();

        RouteStopFactory::new()->with([
            'route' => $route,
            'student' => $studentB,
            'address' => $addressB,
            'stopOrder' => 2,
            'isActive' => true,
        ])->create();

        // Inactive template stop must not be materialized.
        RouteStopFactory::new()->with([
            'route' => $route,
            'student' => StudentFactory::createOne(),
            'address' => AddressFactory::createOne(),
            'stopOrder' => 3,
            'isActive' => false,
        ])->create();

        $this->loginUser($client, $admin);

        $data = $this->postJson($client, '/api/active_routes', [
            'routeTemplate' => '/api/routes/' . $route->getId(),
            'driver' => '/api/drivers/' . $driver->getId(),
            'date' => '2026-04-12',
            'status' => 'scheduled',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $data);

        // Pull the persisted ActiveRoute back out of the EM and verify the
        // children were materialized with the right ordering and student refs.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $activeRoute = $em->getRepository(ActiveRoute::class)->find($data['id']);

        $this->assertNotNull($activeRoute);
        $this->assertCount(2, $activeRoute->getStops(), 'inactive template stop should be skipped');

        $stopOrders = [];
        $studentIds = [];
        foreach ($activeRoute->getStops() as $stop) {
            $stopOrders[] = $stop->getStopOrder();
            $studentIds[] = $stop->getStudent()?->getId();
            $this->assertSame('pending', $stop->getStatus());
            $this->assertSame($activeRoute->getId(), $stop->getActiveRoute()?->getId());
        }

        sort($stopOrders);
        $this->assertSame([1, 2], $stopOrders);

        $this->assertContains($studentA->getId(), $studentIds);
        $this->assertContains($studentB->getId(), $studentIds);
    }

    public function testPostActiveRouteDiscardsClientProvidedCurrentPosition(): void
    {
        $client = $this->createApiClient();

        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $route = RouteFactory::createOne();
        $driver = DriverFactory::createOne();

        $this->loginUser($client, $admin);

        $data = $this->postJson($client, '/api/active_routes', [
            'routeTemplate' => '/api/routes/' . $route->getId(),
            'driver' => '/api/drivers/' . $driver->getId(),
            'date' => '2026-04-12',
            'status' => 'scheduled',
            'currentLatitude' => '-34.603722',
            'currentLongitude' => '-58.381592',
        ]);

        self::assertResponseStatusCodeSame(201);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $activeRoute = $em->getRepository(ActiveRoute::class)->find($data['id']);

        $this->assertNotNull($activeRoute);
        $this->assertNull($activeRoute->getCurrentLatitude());
        $this->assertNull($activeRoute->getCurrentLongitude());
    }

    public function testPostActiveRouteCancelsPriorSameDayDuplicate(): void
    {
        $client = $this->createApiClient();

        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $route = RouteFactory::createOne();
        $driver = DriverFactory::createOne();
        $date = new DateTimeImmutable('2026-04-12');

        $zombieScheduled = ActiveRouteFactory::new()->with([
            'routeTemplate' => $route,
            'driver' => $driver,
            'date' => $date,
            'status' => 'scheduled',
        ])->create();

        $zombieInProgress = ActiveRouteFactory::new()->with([
            'routeTemplate' => $route,
            'driver' => $driver,
            'date' => $date,
            'status' => 'in_progress',
            'startedAt' => new DateTimeImmutable('2026-04-12 07:00:00'),
        ])->create();

        $this->loginUser($client, $admin);

        $data = $this->postJson($client, '/api/active_routes', [
            'routeTemplate' => '/api/routes/' . $route->getId(),
            'driver' => '/api/drivers/' . $driver->getId(),
            'date' => '2026-04-12',
            'status' => 'scheduled',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $data);
        $newId = $data['id'];

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $repo = $em->getRepository(ActiveRoute::class);

        $cancelledA = $repo->find($zombieScheduled->getId());
        $this->assertNotNull($cancelledA);
        $this->assertSame('cancelled', $cancelledA->getStatus());
        $this->assertNotNull($cancelledA->getCompletedAt());

        $cancelledB = $repo->find($zombieInProgress->getId());
        $this->assertNotNull($cancelledB);
        $this->assertSame('cancelled', $cancelledB->getStatus());
        $this->assertNotNull($cancelledB->getCompletedAt());

        $fresh = $repo->find($newId);
        $this->assertNotNull($fresh);
        $this->assertSame('scheduled', $fresh->getStatus());
    }

    public function testPostActiveRouteLeavesDifferentDayUntouched(): void
    {
        $client = $this->createApiClient();

        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $route = RouteFactory::createOne();
        $driver = DriverFactory::createOne();

        $otherDay = ActiveRouteFactory::new()->with([
            'routeTemplate' => $route,
            'driver' => $driver,
            'date' => new DateTimeImmutable('2026-04-11'),
            'status' => 'scheduled',
        ])->create();

        $this->loginUser($client, $admin);

        $this->postJson($client, '/api/active_routes', [
            'routeTemplate' => '/api/routes/' . $route->getId(),
            'driver' => '/api/drivers/' . $driver->getId(),
            'date' => '2026-04-12',
            'status' => 'scheduled',
        ]);

        self::assertResponseStatusCodeSame(201);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $stillScheduled = $em->getRepository(ActiveRoute::class)->find($otherDay->getId());
        $this->assertNotNull($stillScheduled);
        $this->assertSame('scheduled', $stillScheduled->getStatus());
    }

    public function testPostActiveRouteLeavesDifferentDriverUntouched(): void
    {
        $client = $this->createApiClient();

        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $route = RouteFactory::createOne();
        $driverA = DriverFactory::createOne();
        $driverB = DriverFactory::createOne();
        $date = new DateTimeImmutable('2026-04-12');

        $otherDriver = ActiveRouteFactory::new()->with([
            'routeTemplate' => $route,
            'driver' => $driverA,
            'date' => $date,
            'status' => 'scheduled',
        ])->create();

        $this->loginUser($client, $admin);

        $this->postJson($client, '/api/active_routes', [
            'routeTemplate' => '/api/routes/' . $route->getId(),
            'driver' => '/api/drivers/' . $driverB->getId(),
            'date' => '2026-04-12',
            'status' => 'scheduled',
        ]);

        self::assertResponseStatusCodeSame(201);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $stillScheduled = $em->getRepository(ActiveRoute::class)->find($otherDriver->getId());
        $this->assertNotNull($stillScheduled);
        $this->assertSame('scheduled', $stillScheduled->getStatus());
    }

    public function testPostActiveRouteLeavesDifferentTemplateUntouched(): void
    {
        $client = $this->createApiClient();

        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $morning = RouteFactory::createOne();
        $afternoon = RouteFactory::createOne();
        $driver = DriverFactory::createOne();
        $date = new DateTimeImmutable('2026-04-12');

        $morningTrip = ActiveRouteFactory::new()->with([
            'routeTemplate' => $morning,
            'driver' => $driver,
            'date' => $date,
            'status' => 'scheduled',
        ])->create();

        $this->loginUser($client, $admin);

        $this->postJson($client, '/api/active_routes', [
            'routeTemplate' => '/api/routes/' . $afternoon->getId(),
            'driver' => '/api/drivers/' . $driver->getId(),
            'date' => '2026-04-12',
            'status' => 'scheduled',
        ]);

        self::assertResponseStatusCodeSame(201);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $stillScheduled = $em->getRepository(ActiveRoute::class)->find($morningTrip->getId());
        $this->assertNotNull($stillScheduled);
        $this->assertSame('scheduled', $stillScheduled->getStatus());
    }

    public function testPostActiveRouteLeavesTerminalRowsUntouched(): void
    {
        $client = $this->createApiClient();

        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $route = RouteFactory::createOne();
        $driver = DriverFactory::createOne();
        $date = new DateTimeImmutable('2026-04-12');

        $completed = ActiveRouteFactory::new()->with([
            'routeTemplate' => $route,
            'driver' => $driver,
            'date' => $date,
            'status' => 'completed',
            'startedAt' => new DateTimeImmutable('2026-04-12 06:00:00'),
            'completedAt' => new DateTimeImmutable('2026-04-12 07:00:00'),
        ])->create();

        $cancelled = ActiveRouteFactory::new()->with([
            'routeTemplate' => $route,
            'driver' => $driver,
            'date' => $date,
            'status' => 'cancelled',
            'completedAt' => new DateTimeImmutable('2026-04-12 06:30:00'),
        ])->create();

        $cancelledCompletedAt = $cancelled->getCompletedAt();

        $this->loginUser($client, $admin);

        $this->postJson($client, '/api/active_routes', [
            'routeTemplate' => '/api/routes/' . $route->getId(),
            'driver' => '/api/drivers/' . $driver->getId(),
            'date' => '2026-04-12',
            'status' => 'scheduled',
        ]);

        self::assertResponseStatusCodeSame(201);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $repo = $em->getRepository(ActiveRoute::class);

        $stillCompleted = $repo->find($completed->getId());
        $this->assertNotNull($stillCompleted);
        $this->assertSame('completed', $stillCompleted->getStatus());

        $stillCancelled = $repo->find($cancelled->getId());
        $this->assertNotNull($stillCancelled);
        $this->assertSame('cancelled', $stillCancelled->getStatus());
        // completedAt was already stamped — must not be overwritten by the
        // duplicate-cancel path (which only stamps when null).
        $this->assertEquals($cancelledCompletedAt, $stillCancelled->getCompletedAt());
    }
}
