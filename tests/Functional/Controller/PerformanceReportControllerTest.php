<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;

final class PerformanceReportControllerTest extends AbstractApiTestCase
{
    // ── GET /api/reports/performance — authentication & authorisation ─────────

    public function testPerformanceReportRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/reports/performance');

        self::assertResponseStatusCodeSame(401);
    }

    public function testPerformanceReportRequiresSchoolAdminRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/reports/performance');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPerformanceReportWithoutSchoolReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $admin = UserFactory::createOne(['roles' => ['ROLE_SCHOOL_ADMIN']]); // no school
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/reports/performance');

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $data);
    }

    public function testPerformanceReportSuccess(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/reports/performance?start_date=2026-01-01&end_date=2026-02-01');

        self::assertResponseIsSuccessful();
    }

    // ── GET /api/reports/efficiency — authentication & authorisation ──────────

    public function testEfficiencyMetricsRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/reports/efficiency');

        self::assertResponseStatusCodeSame(401);
    }

    public function testEfficiencyMetricsRequiresSchoolAdminRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/reports/efficiency');

        self::assertResponseStatusCodeSame(403);
    }

    public function testEfficiencyMetricsSuccess(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/reports/efficiency');

        self::assertResponseIsSuccessful();
    }

    // ── GET /api/reports/top-performing — authentication ─────────────────────

    public function testTopPerformingRoutesRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/reports/top-performing');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTopPerformingRoutesSuccess(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/reports/top-performing');

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('top_routes', $data);
    }

    // ── GET /api/reports/comparative — authentication ─────────────────────────

    public function testComparativeMetricsRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/reports/comparative');

        self::assertResponseStatusCodeSame(401);
    }

    public function testComparativeMetricsSuccess(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/reports/comparative');

        self::assertResponseIsSuccessful();
    }
}
