<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;

final class AttendanceControllerTest extends AbstractApiTestCase
{
    // ── POST /api/attendance/pickup — authentication & authorisation ──────────

    public function testRecordPickupRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/attendance/pickup', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRecordPickupRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — no ROLE_DRIVER
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/attendance/pickup', [
            'stop_id' => 1,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST /api/attendance/pickup — validation ──────────────────────────────

    public function testRecordPickupMissingStopIdReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/attendance/pickup', []);

        self::assertResponseStatusCodeSame(400);
        $this->assertSame('stop_id is required', $data['error']);
    }

    public function testRecordPickupStopNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/attendance/pickup', [
            'stop_id' => 99999,
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertSame('Stop not found', $data['error']);
    }

    // ── POST /api/attendance/dropoff — authentication & validation ────────────

    public function testRecordDropoffRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/attendance/dropoff', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRecordDropoffMissingStopIdReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/attendance/dropoff', []);

        self::assertResponseStatusCodeSame(400);
        $this->assertSame('stop_id is required', $data['error']);
    }

    public function testRecordDropoffStopNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/attendance/dropoff', [
            'stop_id' => 99999,
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertSame('Stop not found', $data['error']);
    }

    // ── POST /api/attendance/no-show — authentication & validation ────────────

    public function testRecordNoShowRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/attendance/no-show', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRecordNoShowMissingStopIdReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/attendance/no-show', []);

        self::assertResponseStatusCodeSame(400);
        $this->assertSame('stop_id is required', $data['error']);
    }

    public function testRecordNoShowStopNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/attendance/no-show', [
            'stop_id' => 99999,
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertSame('Stop not found', $data['error']);
    }

    // ── GET /api/attendance/manifest/{routeId} — authentication ──────────────

    public function testGetManifestRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/attendance/manifest/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetManifestRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/attendance/manifest/99999');

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('error', $data);
    }

    // ── GET /api/attendance/student/{studentId} — authentication ──────────────

    public function testGetStudentHistoryRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/attendance/student/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetStudentHistoryStudentNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/attendance/student/99999');

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetStudentHistorySuccess(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $student = StudentFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, sprintf('/api/attendance/student/%d', $student->getId()));

        self::assertResponseIsSuccessful();
        $this->assertSame($student->getId(), $data['student_id']);
        $this->assertArrayHasKey('student_name', $data);
        $this->assertSame(0, $data['count']);
        $this->assertIsArray($data['history']);
    }

    // ── GET /api/attendance/stats — authentication & authorisation ────────────

    public function testGetStatsRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/attendance/stats');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetStatsRequiresSchoolAdminRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — not ROLE_SCHOOL_ADMIN
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/attendance/stats?start=2026-01-01&end=2026-02-01');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGetStatsMissingDatesReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/attendance/stats');

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetStatsSuccess(): void
    {
        $client = $this->createApiClient();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/attendance/stats?start=2026-01-01&end=2026-02-01');

        self::assertResponseIsSuccessful();
        $this->assertSame('2026-01-01', $data['start_date']);
        $this->assertSame('2026-02-01', $data['end_date']);
        $this->assertIsArray($data['statistics']);
    }
}
