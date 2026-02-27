<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;

final class SafetyAuditControllerTest extends AbstractApiTestCase
{
    // ── GET /api/safety/audit — authentication & authorisation ───────────────

    public function testSafetyAuditRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/safety/audit');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSafetyAuditRequiresSchoolAdminRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — not ROLE_SCHOOL_ADMIN
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/safety/audit');

        self::assertResponseStatusCodeSame(403);
    }

    public function testSafetyAuditWithoutSchoolReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]); // no school set
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/safety/audit');

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testSafetyAuditSuccess(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/safety/audit?start_date=2026-01-01&end_date=2026-02-01');

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('school', $data);
        $this->assertArrayHasKey('safety_score', $data);
        $this->assertArrayHasKey('check_in_out_verification', $data);
    }

    public function testSafetyAuditDefaultsToLast30Days(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $admin);

        // No date parameters — defaults to last 30 days
        $data = $this->getJson($client, '/api/safety/audit');

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('safety_score', $data);
    }
}
