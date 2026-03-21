<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\DriverRateFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;

final class DriverRateControllerTest extends AbstractApiTestCase
{
    // ── Authentication ───────────────────────────────────────────────────────

    public function testListRatesRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/driver-rates?driver=1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateRateRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/driver-rates', []);

        self::assertResponseStatusCodeSame(401);
    }

    // ── GetCollection ────────────────────────────────────────────────────────

    public function testListRatesByDriver(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::new()->with([
            'roles' => ['ROLE_USER'],
        ])->create();
        $driver = DriverFactory::createOne();
        DriverRateFactory::new()->flat('1500.00')->with([
            'driver' => $driver,
        ])->create();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/driver-rates?driver=' . $driver->getId());

        self::assertResponseIsSuccessful();
        $this->assertCount(1, $body);
        $this->assertSame('1500.00', $body[0]['amount']);
    }

    public function testListRatesWithoutDriverParamReturnsEmpty(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::new()->with([
            'roles' => ['ROLE_USER'],
        ])->create();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/driver-rates');

        self::assertResponseIsSuccessful();
        $this->assertSame([], $body);
    }

    // ── Create Rate ──────────────────────────────────────────────────────────

    public function testCreateFlatRate(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $body = $this->postJson($client, '/api/driver-rates', [
            'driver' => '/api/drivers/' . $driver->getId(),
            'pricingModel' => 'flat',
            'amount' => '1500.00',
            'currency' => 'ARS',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertSame('flat', $body['pricingModel']);
        $this->assertSame('1500.00', $body['amount']);
        $this->assertNull($body['perStudentAmount']);
    }

    public function testCreatePerRouteRate(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $driver = DriverFactory::createOne();
        $route = RouteFactory::new()->withDriver($driver)->withSchool($school)->create();
        $this->loginUser($client, $driver->getUser());

        $body = $this->postJson($client, '/api/driver-rates', [
            'driver' => '/api/drivers/' . $driver->getId(),
            'pricingModel' => 'per_route',
            'route' => '/api/routes/' . $route->getId(),
            'amount' => '2000.00',
            'currency' => 'ARS',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertSame('per_route', $body['pricingModel']);
        $this->assertSame('2000.00', $body['amount']);
    }

    public function testCreateRateRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::new()->with([
            'roles' => ['ROLE_USER'],
        ])->create();
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/driver-rates', [
            'pricingModel' => 'flat',
            'amount' => '1500.00',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateRateForAnotherDriverReturns403(): void
    {
        $client = $this->createApiClient();
        $otherDriver = DriverFactory::createOne();
        $myDriver = DriverFactory::createOne();
        $this->loginUser($client, $myDriver->getUser());

        $this->postJson($client, '/api/driver-rates', [
            'driver' => '/api/drivers/' . $otherDriver->getId(),
            'pricingModel' => 'flat',
            'amount' => '1500.00',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateRateWithInvalidPricingModelFieldsReturns422(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        // FLAT model should not have a route
        $school = SchoolFactory::createOne();
        $route = RouteFactory::new()->withDriver($driver)->withSchool($school)->create();

        $this->postJson($client, '/api/driver-rates', [
            'driver' => '/api/drivers/' . $driver->getId(),
            'pricingModel' => 'flat',
            'route' => '/api/routes/' . $route->getId(),
            'amount' => '1500.00',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function testDeleteRateOnlyByOwner(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $rate = DriverRateFactory::new()->flat('1500.00')->with([
            'driver' => $driver,
        ])->create();
        $otherDriver = DriverFactory::createOne();
        $this->loginUser($client, $otherDriver->getUser());

        $this->deleteJson($client, '/api/driver-rates/' . $rate->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteOwnRate(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $rate = DriverRateFactory::new()->flat('1500.00')->with([
            'driver' => $driver,
        ])->create();
        $this->loginUser($client, $driver->getUser());

        $this->deleteJson($client, '/api/driver-rates/' . $rate->getId());

        self::assertResponseStatusCodeSame(204);
    }

    // ── Bulk Set ─────────────────────────────────────────────────────────────

    public function testBulkSetDriverRatesFlat(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $body = $this->postJson($client, '/api/drivers/' . $driver->getId() . '/rates', [
            'pricingModel' => 'flat',
            'rates' => [
                [
                    'amount' => '2000.00',
                    'currency' => 'ARS',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertSame('flat', $body['pricingModel']);
    }

    public function testBulkSetDriverRatesPerRoute(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $driver = DriverFactory::createOne();
        $route1 = RouteFactory::new()->withDriver($driver)->withSchool($school)->create();
        $route2 = RouteFactory::new()->withDriver($driver)->withSchool($school)->create();
        $this->loginUser($client, $driver->getUser());

        $body = $this->postJson($client, '/api/drivers/' . $driver->getId() . '/rates', [
            'pricingModel' => 'per_route',
            'rates' => [
                [
                    'routeId' => $route1->getId(),
                    'amount' => '1500.00',
                ],
                [
                    'routeId' => $route2->getId(),
                    'amount' => '1800.00',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertSame('per_route', $body['pricingModel']);
    }

    public function testBulkSetRatesReplacesExisting(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        DriverRateFactory::new()->flat('1000.00')->with([
            'driver' => $driver,
        ])->create();
        $this->loginUser($client, $driver->getUser());

        $this->postJson($client, '/api/drivers/' . $driver->getId() . '/rates', [
            'pricingModel' => 'per_student',
            'rates' => [
                [
                    'perStudentAmount' => '500.00',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();

        // Verify old rate is gone and new one exists
        $ratesBody = $this->getJson($client, '/api/driver-rates?driver=' . $driver->getId());
        $this->assertCount(1, $ratesBody);
        $this->assertSame('per_student', $ratesBody[0]['pricingModel']);
        $this->assertSame('500.00', $ratesBody[0]['perStudentAmount']);
    }

    public function testBulkSetRatesForAnotherDriverReturns403(): void
    {
        $client = $this->createApiClient();
        $otherDriver = DriverFactory::createOne();
        $myDriver = DriverFactory::createOne();
        $this->loginUser($client, $myDriver->getUser());

        $this->postJson($client, '/api/drivers/' . $otherDriver->getId() . '/rates', [
            'pricingModel' => 'flat',
            'rates' => [
                [
                    'amount' => '1000.00',
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
