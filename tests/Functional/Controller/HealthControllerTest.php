<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Request;

final class HealthControllerTest extends AbstractApiTestCase
{
    // ── GET /health ───────────────────────────────────────────────────────────

    public function testHealthCheckReturns200(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/health');

        self::assertResponseIsSuccessful();
    }

    public function testHealthCheckResponseShape(): void
    {
        $client = $this->createApiClient();

        $data = $this->getJson($client, '/health');

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('timestamp', $data);
        self::assertArrayHasKey('checks', $data);
        self::assertArrayHasKey('database', $data['checks']);
        self::assertArrayHasKey('disk', $data['checks']);
        self::assertArrayHasKey('memory', $data['checks']);
        self::assertArrayHasKey('application', $data['checks']);
    }

    public function testHealthCheckDatabaseIsHealthy(): void
    {
        $client = $this->createApiClient();

        $data = $this->getJson($client, '/health');

        self::assertSame('healthy', $data['checks']['database']['status']);
    }

    // ── GET /health/ready ─────────────────────────────────────────────────────

    public function testHealthReadyReturns200(): void
    {
        $client = $this->createApiClient();

        $data = $this->getJson($client, '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertSame('ready', $data['status']);
        self::assertArrayHasKey('timestamp', $data);
    }

    // ── GET /health/live ──────────────────────────────────────────────────────

    public function testHealthLiveReturns200(): void
    {
        $client = $this->createApiClient();

        $data = $this->getJson($client, '/health/live');

        self::assertResponseIsSuccessful();
        self::assertSame('alive', $data['status']);
        self::assertArrayHasKey('timestamp', $data);
    }
}
