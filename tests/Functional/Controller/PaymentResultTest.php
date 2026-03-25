<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Request;

final class PaymentResultTest extends AbstractApiTestCase
{
    public function testSuccessStatusRendsSuccessPage(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/payment/result?status=success');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.result-title', 'Pago realizado');
    }

    public function testPendingStatusRendersPendingPage(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/payment/result?status=pending');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.result-title', 'Pago pendiente');
    }

    public function testFailureStatusRendersFailurePage(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/payment/result?status=failure');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.result-title', 'Pago no realizado');
    }

    public function testMissingStatusDefaultsToFailure(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/payment/result');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.result-title', 'Pago no realizado');
    }

    public function testInvalidStatusDefaultsToFailure(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/payment/result?status=bogus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.result-title', 'Pago no realizado');
    }

    public function testPageIsPubliclyAccessible(): void
    {
        $client = $this->createApiClient();

        // No loginUser() — request is unauthenticated
        $client->request(Request::METHOD_GET, '/payment/result?status=success');

        self::assertResponseIsSuccessful();
    }
}
