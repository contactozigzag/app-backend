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

    public function testTokenEndpointMissingQueryParamReturns400(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token');

        self::assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('payment_id', (string) $body['error']);
    }

    public function testTokenEndpointNonNumericPaymentIdReturns400(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/mercure/token?payment_id=abc');

        self::assertResponseStatusCodeSame(400);
    }

    // ── not found ─────────────────────────────────────────────────────────────

    public function testTokenEndpointPaymentNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token?payment_id=999999');

        self::assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('not found', (string) $body['error']);
    }

    // ── access control ────────────────────────────────────────────────────────

    public function testTokenEndpointForbiddenForNonOwnerReturns403(): void
    {
        $client = $this->createApiClient();
        $owner = UserFactory::createOne();
        $other = UserFactory::createOne();
        $payment = PaymentFactory::createOne([
            'user' => $owner,
        ]);
        $this->loginUser($client, $other);

        $body = $this->getJson($client, '/api/mercure/token?payment_id=' . $payment->getId());

        self::assertResponseStatusCodeSame(403);
        $this->assertStringContainsString('denied', (string) $body['error']);
    }

    // ── success ───────────────────────────────────────────────────────────────

    public function testTokenEndpointReturnsJwtForPaymentOwner(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $payment = PaymentFactory::createOne([
            'user' => $user,
        ]);
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token?payment_id=' . $payment->getId());

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $body);
        $this->assertArrayHasKey('hub_url', $body);
        $this->assertArrayHasKey('topics', $body);

        // The topic must match the payment's private update URL
        $this->assertContains('/payments/' . $payment->getId(), $body['topics']);

        // The Mercure JWT must be a valid 3-part JWT
        $this->assertCount(3, explode('.', (string) $body['token']));
    }

    // ── user_id: validation ────────────────────────────────────────────────

    public function testTokenEndpointNonNumericUserIdReturns400(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/mercure/token?user_id=abc');

        self::assertResponseStatusCodeSame(400);
    }

    // ── user_id: access control ────────────────────────────────────────────

    public function testTokenEndpointForbiddenForOtherUserReturns403(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $other = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token?user_id=' . $other->getId());

        self::assertResponseStatusCodeSame(403);
        $this->assertStringContainsString('denied', (string) $body['error']);
    }

    // ── user_id: success ───────────────────────────────────────────────────

    public function testTokenEndpointReturnsJwtForUserNotifications(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/mercure/token?user_id=' . $user->getId());

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $body);
        $this->assertArrayHasKey('hub_url', $body);
        $this->assertArrayHasKey('topics', $body);

        $expectedTopic = '/api/users/' . $user->getId() . '/notifications';
        $this->assertContains($expectedTopic, $body['topics']);

        $this->assertCount(3, explode('.', (string) $body['token']));
    }
}
