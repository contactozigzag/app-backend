<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\RouteStopFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;

final class RouteStopControllerTest extends AbstractApiTestCase
{
    // ── GET /api/route-stops — driver sees only stops on own routes ─────────

    public function testGetCollectionDriverSeesOnlyOwnRouteStops(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $otherDriver = DriverFactory::createOne();
        $route = RouteFactory::new()->withDriver($driver)->create();
        $otherRoute = RouteFactory::new()->withDriver($otherDriver)->create();
        $student1 = StudentFactory::createOne();
        $student2 = StudentFactory::createOne();
        RouteStopFactory::new()->withRoute($route)->withStudent($student1)->create();
        RouteStopFactory::new()->withRoute($route)->withStudent($student1)->create();
        RouteStopFactory::new()->withRoute($otherRoute)->withStudent($student2)->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/route-stops');

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data);
    }

    public function testGetCollectionDriverDoesNotSeeOtherDriverStops(): void
    {
        $client = $this->createApiClient();
        $driver1 = DriverFactory::createOne();
        $driver2 = DriverFactory::createOne();
        $route1 = RouteFactory::new()->withDriver($driver1)->create();
        $route2 = RouteFactory::new()->withDriver($driver2)->create();
        RouteStopFactory::new()->withRoute($route1)->create();
        RouteStopFactory::new()->withRoute($route2)->create();
        $this->loginUser($client, $driver1->getUser());

        $data = $this->getJson($client, '/api/route-stops');

        self::assertResponseIsSuccessful();
        $this->assertCount(1, $data);
    }

    // ── POST /api/route-stops — authentication & validation ───────────────────

    public function testCreateRouteStopRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/route-stops', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateRouteStopMissingFieldsReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/route-stops', [
            // missing route, student, address
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testCreateRouteStopSucceeds(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $route = RouteFactory::createOne();
        $student = StudentFactory::createOne();
        $address = AddressFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/route-stops', [
            'route' => '/api/routes/' . $route->getId(),
            'student' => '/api/students/' . $student->getId(),
            'address' => '/api/addresses/' . $address->getId(),
            'stopOrder' => 0,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateRouteStopDuplicateActiveReturns409(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $route = RouteFactory::createOne();
        $student = StudentFactory::createOne();
        $address = AddressFactory::createOne();
        RouteStopFactory::new()->withRoute($route)->withStudent($student)->create();
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/route-stops', [
            'route' => '/api/routes/' . $route->getId(),
            'student' => '/api/students/' . $student->getId(),
            'address' => '/api/addresses/' . $address->getId(),
            'stopOrder' => 0,
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testCreateRouteStopAllowsDifferentStudentOnSameRoute(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $route = RouteFactory::createOne();
        $student1 = StudentFactory::createOne();
        $student2 = StudentFactory::createOne();
        $address = AddressFactory::createOne();
        RouteStopFactory::new()->withRoute($route)->withStudent($student1)->create();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/route-stops', [
            'route' => '/api/routes/' . $route->getId(),
            'student' => '/api/students/' . $student2->getId(),
            'address' => '/api/addresses/' . $address->getId(),
            'stopOrder' => 0,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateRouteStopDuplicateConfirmedAlsoReturns409(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $route = RouteFactory::createOne();
        $student = StudentFactory::createOne();
        $address = AddressFactory::createOne();
        RouteStopFactory::new()->withRoute($route)->withStudent($student)->with([
            'isConfirmed' => true,
        ])->create();
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/route-stops', [
            'route' => '/api/routes/' . $route->getId(),
            'student' => '/api/students/' . $student->getId(),
            'address' => '/api/addresses/' . $address->getId(),
            'stopOrder' => 0,
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    // ── GET /api/route-stops/unconfirmed — authentication & authorisation ─────

    public function testListUnconfirmedRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/route-stops/unconfirmed');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListUnconfirmedRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/route-stops/unconfirmed');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListUnconfirmedSuccessReturnsEmptyList(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/route-stops/unconfirmed');

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('unconfirmedStops', $data);
        $this->assertSame(0, $data['total']);
    }

    public function testListUnconfirmedIncludesStudentAndParentNames(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $parent = UserFactory::createOne();
        $student = StudentFactory::new()->withParent($parent)->create();
        $route = RouteFactory::new()->withDriver($driver)->create();
        RouteStopFactory::new()->withRoute($route)->withStudent($student)->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/route-stops/unconfirmed');

        self::assertResponseIsSuccessful();
        $this->assertSame(1, $data['total']);
        $stop = $data['unconfirmedStops'][0];
        $this->assertSame($student->getFirstName() . ' ' . $student->getLastName(), $stop['studentName']);
        $this->assertSame(trim(($parent->getFirstName() ?? '') . ' ' . ($parent->getLastName() ?? '')), $stop['parentName']);
        $this->assertArrayHasKey('parentNames', $stop);
        $this->assertCount(1, $stop['parentNames']);
    }

    public function testListUnconfirmedExcludesConfirmedStops(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $route = RouteFactory::new()->withDriver($driver)->create();
        RouteStopFactory::new()->withRoute($route)->with([
            'isConfirmed' => true,
        ])->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/route-stops/unconfirmed');

        self::assertResponseIsSuccessful();
        $this->assertSame(0, $data['total']);
    }

    public function testListUnconfirmedExcludesInactiveStops(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $route = RouteFactory::new()->withDriver($driver)->create();
        RouteStopFactory::new()->withRoute($route)->with([
            'isActive' => false,
        ])->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/route-stops/unconfirmed');

        self::assertResponseIsSuccessful();
        $this->assertSame(0, $data['total']);
    }

    public function testListUnconfirmedOnlyShowsOwnDriverStops(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $otherDriver = DriverFactory::createOne();
        $ownRoute = RouteFactory::new()->withDriver($driver)->create();
        $otherRoute = RouteFactory::new()->withDriver($otherDriver)->create();
        RouteStopFactory::new()->withRoute($ownRoute)->create();
        RouteStopFactory::new()->withRoute($otherRoute)->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/route-stops/unconfirmed');

        self::assertResponseIsSuccessful();
        $this->assertSame(1, $data['total']);
    }

    // ── PATCH /api/route-stops/{id}/confirm — authentication & validation ─────

    public function testConfirmRouteStopRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_PATCH, '/api/route-stops/1/confirm', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testConfirmRouteStopRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT
        $this->loginUser($client, $user);

        $client->request(Request::METHOD_PATCH, '/api/route-stops/1/confirm', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $client->getServerParameter('HTTP_AUTHORIZATION'),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testConfirmRouteStopNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $client->request(Request::METHOD_PATCH, '/api/route-stops/99999/confirm', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $client->getServerParameter('HTTP_AUTHORIZATION'),
        ]);

        $data = json_decode((string) $client->getResponse()->getContent(), true) ?? [];

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }

    // ── PATCH /api/route-stops/{id}/reject — authentication & validation ──────

    public function testRejectRouteStopRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_PATCH, '/api/route-stops/1/reject', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectRouteStopNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $client->request(Request::METHOD_PATCH, '/api/route-stops/99999/reject', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $client->getServerParameter('HTTP_AUTHORIZATION'),
        ]);

        $data = json_decode((string) $client->getResponse()->getContent(), true) ?? [];

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }
}
