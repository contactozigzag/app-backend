<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;

final class RouteStopControllerTest extends AbstractApiTestCase
{
    // ── POST /api/route-stops — authentication & validation ───────────────────

    public function testCreateRouteStopRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/route-stops', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateRouteStopMissingFieldsReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/route-stops', [
            // missing route, student, address
        ]);

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data);
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
        $this->assertArrayHasKey('unconfirmed_stops', $data);
        $this->assertSame(0, $data['total']);
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
        $this->assertArrayHasKey('error', $data);
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
        $this->assertArrayHasKey('error', $data);
    }
}
