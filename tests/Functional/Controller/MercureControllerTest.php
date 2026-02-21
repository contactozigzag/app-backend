<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\PaymentFactory;
use App\Tests\Factory\UserFactory;

final class MercureControllerTest extends AbstractApiTestCase
{
    // ── authentication guard ──────────────────────────────────────────────────

    public function testTokenEndpointRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/mercure/token?payment_id=1');

        self::assertResponseStatusCodeSame(401);
    }

    // ── validation ────────────────────────────────────────────────────────────

    public function testTokenEndpointMissingPaymentIdReturns400(): void
    {
        $client = $this->createApiClient();
        $user   = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token');

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('payment_id', $body['error']);
    }

    public function testTokenEndpointNonNumericPaymentIdReturns400(): void
    {
        $client = $this->createApiClient();
        $user   = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token?payment_id=abc');

        self::assertResponseStatusCodeSame(400);
    }

    // ── not found ─────────────────────────────────────────────────────────────

    public function testTokenEndpointPaymentNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user   = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token?payment_id=999999');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('not found', $body['error']);
    }

    // ── access control ────────────────────────────────────────────────────────

    public function testTokenEndpointForbiddenForNonOwnerReturns403(): void
    {
        $client  = $this->createApiClient();
        $owner   = UserFactory::createOne();
        $other   = UserFactory::createOne();
        $payment = PaymentFactory::createOne(['user' => $owner]);
        $this->loginUser($client, $other);

        $body = $this->getJson($client, "/api/mercure/token?payment_id={$payment->getId()}");

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('denied', $body['error']);
    }

    // ── success ───────────────────────────────────────────────────────────────

    public function testTokenEndpointReturnsJwtForPaymentOwner(): void
    {
        $client  = $this->createApiClient();
        $user    = UserFactory::createOne();
        $payment = PaymentFactory::createOne(['user' => $user]);
        $this->loginUser($client, $user);

        $body = $this->getJson($client, "/api/mercure/token?payment_id={$payment->getId()}");

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $body);
        self::assertArrayHasKey('hub_url', $body);
        self::assertArrayHasKey('topics', $body);

        // The topic must match the payment's private update URL
        self::assertContains("/payments/{$payment->getId()}", $body['topics']);

        // The Mercure JWT must be a valid 3-part JWT
        self::assertCount(3, explode('.', $body['token']));
    }
}
