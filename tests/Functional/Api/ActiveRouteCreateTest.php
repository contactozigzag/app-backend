<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\ActiveRoute;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\RouteStopFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
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
}
