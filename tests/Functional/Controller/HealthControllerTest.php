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
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('disk', $data['checks']);
        $this->assertArrayHasKey('memory', $data['checks']);
        $this->assertArrayHasKey('application', $data['checks']);
    }

    public function testHealthCheckDatabaseIsHealthy(): void
    {
        $client = $this->createApiClient();

        $data = $this->getJson($client, '/health');

        $this->assertSame('healthy', $data['checks']['database']['status']);
    }

    // ── GET /health/ready ─────────────────────────────────────────────────────

    public function testHealthReadyReturns200(): void
    {
        $client = $this->createApiClient();

        $data = $this->getJson($client, '/health/ready');

        self::assertResponseIsSuccessful();
        $this->assertSame('ready', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    // ── GET /health/live ──────────────────────────────────────────────────────

    public function testHealthLiveReturns200(): void
    {
        $client = $this->createApiClient();

        $data = $this->getJson($client, '/health/live');

        self::assertResponseIsSuccessful();
        $this->assertSame('alive', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
    }
}
