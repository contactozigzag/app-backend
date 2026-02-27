<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;

final class AbsenceControllerTest extends AbstractApiTestCase
{
    // ── POST /api/absences — authentication & authorisation ───────────────────

    public function testReportAbsenceRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/absences', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testReportAbsenceRequiresParentRole(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne(); // ROLE_DRIVER — no ROLE_PARENT
        $this->loginUser($client, $driver->getUser());

        $this->postJson($client, '/api/absences', [
            'student_id' => 999,
            'date' => '2026-03-15',
            'type' => 'full_day',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST /api/absences — validation ───────────────────────────────────────

    public function testReportAbsenceMissingFieldsReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/absences', [
            'date' => '2026-03-15',
            // missing student_id and type
        ]);

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testReportAbsenceStudentNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/absences', [
            'student_id' => 99999,
            'date' => '2026-03-15',
            'type' => 'full_day',
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('error', $data);
    }

    // ── POST /api/absences — success ──────────────────────────────────────────

    public function testReportAbsenceSuccess(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $student = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $data = $this->postJson($client, '/api/absences', [
            'student_id' => $student->getId(),
            'date' => '2026-03-15',
            'type' => 'full_day',
            'reason' => 'sick',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('absence_id', $data);
    }

    public function testReportAbsenceDuplicateReturnsConflict(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $student = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $payload = [
            'student_id' => $student->getId(),
            'date' => '2026-03-20',
            'type' => 'full_day',
            'reason' => 'sick',
        ];

        $this->postJson($client, '/api/absences', $payload);
        self::assertResponseStatusCodeSame(201);

        $data = $this->postJson($client, '/api/absences', $payload);
        self::assertResponseStatusCodeSame(409);
        $this->assertArrayHasKey('error', $data);
    }

    // ── GET /api/absences/student/{studentId} — authentication & results ──────

    public function testGetStudentAbsencesRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/absences/student/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetStudentAbsencesStudentNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/absences/student/99999');

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetStudentAbsencesSuccess(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $student = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, sprintf('/api/absences/student/%d', $student->getId()));

        self::assertResponseIsSuccessful();
        $this->assertSame($student->getId(), $data['student_id']);
        $this->assertSame(0, $data['count']);
        $this->assertIsArray($data['absences']);
    }

    // ── DELETE /api/absences/{id} — authentication & authorisation ────────────

    public function testCancelAbsenceRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_DELETE, '/api/absences/1', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCancelAbsenceRequiresParentRole(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $client->request(Request::METHOD_DELETE, '/api/absences/1', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $client->getServerParameter('HTTP_AUTHORIZATION'),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCancelAbsenceNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $client->request(Request::METHOD_DELETE, '/api/absences/99999', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $client->getServerParameter('HTTP_AUTHORIZATION'),
        ]);

        $data = json_decode((string) $client->getResponse()->getContent(), true) ?? [];

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testCancelAbsenceSuccess(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $student = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $created = $this->postJson($client, '/api/absences', [
            'student_id' => $student->getId(),
            'date' => '2030-06-01', // future date
            'type' => 'morning',
            'reason' => 'vacation',
        ]);

        self::assertResponseStatusCodeSame(201);

        $client->request(Request::METHOD_DELETE, sprintf('/api/absences/%d', $created['absence_id']), [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $client->getServerParameter('HTTP_AUTHORIZATION'),
        ]);

        $data = json_decode((string) $client->getResponse()->getContent(), true) ?? [];

        self::assertResponseIsSuccessful();
        $this->assertTrue($data['success']);
    }
}
