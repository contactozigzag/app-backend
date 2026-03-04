<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\SpecialEventRouteFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SpecialEventRouteControllerTest extends AbstractApiTestCase
{
    // ── GET /api/special-event-routes — unauthenticated ───────────────────────

    public function testListRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/special-event-routes');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListRequiresRouteManage(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — no ROUTE_MANAGE
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/special-event-routes');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListReturnsEmptyCollectionForAdmin(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/special-event-routes');

        self::assertResponseIsSuccessful();
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    // ── GET /api/special-event-routes/{id} — authentication & authorisation ───

    public function testGetRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/special-event-routes/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetRequiresRouteManage(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $route = SpecialEventRouteFactory::createOne();
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/special-event-routes/' . $route->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testGetNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/special-event-routes/99999');

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testGetReturnsRoute(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/special-event-routes/' . $route->getId());

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame($route->getId(), $data['id']);
    }

    // ── POST /api/special-event-routes — authentication & validation ──────────

    public function testCreateRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/special-event-routes', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateRequiresRouteManage(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/special-event-routes', [
            'name' => 'Test Event',
            'routeMode' => 'ONE_WAY',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateSucceeds(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes', [
            'name' => 'Field Trip',
            'routeMode' => 'ONE_WAY',
            'eventType' => 'OTHER',
            'eventDate' => '2026-06-15',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('DRAFT', $data['status']);
    }

    // ── PATCH /api/special-event-routes/{id} ─────────────────────────────────

    public function testUpdateRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_PATCH, '/api/special-event-routes/1', [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'name' => 'Updated',
        ]));

        self::assertResponseStatusCodeSame(401);
    }

    public function testUpdateNonDraftReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->published()->create();
        $this->loginUser($client, $user);

        $client->request(Request::METHOD_PATCH, '/api/special-event-routes/' . $route->getId(), [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'name' => 'Updated',
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    // ── DELETE /api/special-event-routes/{id} ────────────────────────────────

    public function testDeleteRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_DELETE, '/api/special-event-routes/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteInProgressReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->inProgress()->create();
        $this->loginUser($client, $user);

        $client->request(Request::METHOD_DELETE, '/api/special-event-routes/' . $route->getId());

        self::assertResponseStatusCodeSame(422);
    }

    public function testDeleteDraftSucceeds(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::createOne();
        $this->loginUser($client, $user);

        $client->request(Request::METHOD_DELETE, '/api/special-event-routes/' . $route->getId());

        self::assertResponseStatusCodeSame(204);
    }

    // ── POST /api/special-event-routes/{id}/publish ───────────────────────────

    public function testPublishRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/special-event-routes/1/publish', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testPublishRequiresRouteManage(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/special-event-routes/1/publish', []);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPublishNonDraftReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->published()->create();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/publish', []);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPublishNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/99999/publish', []);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPublishDraftSucceeds(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::createOne([
            'status' => SpecialEventRouteStatus::DRAFT,
        ]);
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/publish', []);

        self::assertResponseStatusCodeSame(200);
        $this->assertSame('PUBLISHED', $data['status']);
    }

    // ── POST /api/special-event-routes/{id}/start-outbound ────────────────────

    public function testStartOutboundRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/special-event-routes/1/start-outbound', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testStartOutboundNotPublishedReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::createOne([
            'status' => SpecialEventRouteStatus::DRAFT,
        ]);
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/start-outbound', []);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testStartOutboundSucceeds(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->published()->create();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/start-outbound', []);

        self::assertResponseStatusCodeSame(200);
        $this->assertSame('IN_PROGRESS', $data['status']);
    }

    // ── POST /api/special-event-routes/{id}/complete ─────────────────────────

    public function testCompleteRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/special-event-routes/1/complete', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCompleteNotInProgressReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::createOne([
            'status' => SpecialEventRouteStatus::DRAFT,
        ]);
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/complete', []);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testCompleteSucceeds(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->inProgress()->create();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/complete', []);

        self::assertResponseStatusCodeSame(200);
        $this->assertSame('COMPLETED', $data['status']);
    }

    // ── Lifecycle happy path: DRAFT → publish → start-outbound → complete ────

    public function testFullLifecycleHappyPath(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::createOne([
            'status' => SpecialEventRouteStatus::DRAFT,
            'routeMode' => RouteMode::ONE_WAY,
        ]);
        $this->loginUser($client, $user);

        $routeId = $route->getId();

        // Publish
        $data = $this->postJson($client, '/api/special-event-routes/' . $routeId . '/publish', []);
        self::assertResponseStatusCodeSame(200);
        $this->assertSame('PUBLISHED', $data['status']);

        // Start outbound
        $data = $this->postJson($client, '/api/special-event-routes/' . $routeId . '/start-outbound', []);
        self::assertResponseStatusCodeSame(200);
        $this->assertSame('IN_PROGRESS', $data['status']);

        // Complete
        $data = $this->postJson($client, '/api/special-event-routes/' . $routeId . '/complete', []);
        self::assertResponseStatusCodeSame(200);
        $this->assertSame('COMPLETED', $data['status']);
    }

    // ── POST /api/special-event-routes/{id}/arrive-at-event ──────────────────

    public function testArriveAtEventRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/special-event-routes/1/arrive-at-event', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testArriveAtEventOneWayAutoCompletes(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->inProgress()->create([
            'routeMode' => RouteMode::ONE_WAY,
        ]);
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/arrive-at-event', []);

        self::assertResponseStatusCodeSame(200);
        $this->assertSame('COMPLETED', $data['status']);
    }

    // ── POST /api/special-event-routes/{id}/start-return ─────────────────────

    public function testStartReturnRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/special-event-routes/1/start-return', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testStartReturnOneWayReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->inProgress()->create([
            'routeMode' => RouteMode::ONE_WAY,
        ]);
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/special-event-routes/' . $route->getId() . '/start-return', []);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('detail', $data);
    }

    // ── POST /api/special-event-routes/{id}/students/{studentId}/ready ────────

    public function testStudentReadyRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/special-event-routes/1/students/1/ready', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testStudentReadyRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/special-event-routes/1/students/1/ready', []);

        self::assertResponseStatusCodeSame(403);
    }

    public function testStudentReadyRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/special-event-routes/99999/students/1/ready', []);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }
}
