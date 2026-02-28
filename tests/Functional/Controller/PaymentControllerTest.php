<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Enum\PaymentStatus;
use App\Service\Payment\MercadoPagoOAuthService;
use App\Service\Payment\MercadoPagoService;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\PaymentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;

final class PaymentControllerTest extends AbstractApiTestCase
{
    // ── Authentication guard ──────────────────────────────────────────────────

    public function testCreatePreferenceRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/payments/preference', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testListPaymentsRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/payments');

        self::assertResponseStatusCodeSame(401);
    }

    // ── createPreference: validation ──────────────────────────────────────────

    public function testCreatePreferenceMissingDriverIdReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/payments/preference', [
            'studentIds' => [1],
            'amount' => '100',
            'description' => 'Test',
            'idempotencyKey' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $body);
        $violationPaths = array_column($body['violations'], 'propertyPath');
        $this->assertContains('driverId', $violationPaths);
    }

    public function testCreatePreferenceInvalidIdempotencyKeyReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/payments/preference', [
            'driverId' => 1,
            'studentIds' => [1],
            'amount' => '100',
            'description' => 'Test',
            'idempotencyKey' => 'not-a-uuid',
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $body);
        $violationPaths = array_column($body['violations'], 'propertyPath');
        $this->assertContains('idempotencyKey', $violationPaths);
    }

    public function testCreatePreferenceDriverNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/payments/preference', [
            'driverId' => 999999,
            'studentIds' => [1],
            'amount' => '100',
            'description' => 'Test',
            'idempotencyKey' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('not found', (string) $body['detail']);
    }

    public function testCreatePreferenceDriverNotConnectedReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        // Driver without MP OAuth (hasMpAuthorized() = false)
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/payments/preference', [
            'driverId' => $driver->getId(),
            'studentIds' => [1],
            'amount' => '100',
            'description' => 'Test',
            'idempotencyKey' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Mercado Pago', (string) $body['detail']);
    }

    // ── createPreference: success (mocked MP) ─────────────────────────────────

    public function testCreatePreferenceReturns201OnSuccess(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $driver = DriverFactory::new()->withMpAuthorized()->create();
        $student = StudentFactory::new()->withParent($user)->create();

        // Mock MercadoPagoService so we don't need real MP credentials
        $mpMock = $this->createStub(MercadoPagoService::class);
        $mpMock->method('createPreference')->willReturn([
            'preference_id' => 'pref-abc123',
            'init_point' => 'https://mp.example.com/checkout',
            'sandbox_init_point' => 'https://sandbox.mp.example.com/checkout',
        ]);
        self::getContainer()->set(MercadoPagoService::class, $mpMock);

        // Mock OAuth service so token decryption is skipped
        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock->method('getAccessToken')->willReturn('fake-access-token');
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/payments/preference', [
            'driverId' => $driver->getId(),
            'studentIds' => [$student->getId()],
            'amount' => '150.00',
            'description' => 'School transport fee',
            'idempotencyKey' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'currency' => 'ARS',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('paymentId', $body);
        $this->assertArrayHasKey('preferenceId', $body);
        $this->assertArrayHasKey('initPoint', $body);
        $this->assertSame('pending', $body['status']);
    }

    // ── getStatus ─────────────────────────────────────────────────────────────

    public function testGetStatusReturnsPaymentData(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $payment = PaymentFactory::createOne([
            'user' => $user,
        ]);
        $this->loginUser($client, $user);

        $body = $this->getJson($client, sprintf('/api/payments/%s/status', $payment->getId()));

        self::assertResponseIsSuccessful();
        $this->assertSame($payment->getId(), $body['paymentId']);
        $this->assertSame(PaymentStatus::PENDING->value, $body['status']);
    }

    public function testGetStatusNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/payments/999999/status');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetStatusForbiddenForOtherUserReturns403(): void
    {
        $client = $this->createApiClient();
        $owner = UserFactory::createOne();
        $other = UserFactory::createOne();
        $payment = PaymentFactory::createOne([
            'user' => $owner,
        ]);
        $this->loginUser($client, $other);

        $this->getJson($client, sprintf('/api/payments/%s/status', $payment->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    // ── list ──────────────────────────────────────────────────────────────────

    public function testListPaymentsReturnsOnlyOwnPayments(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $other = UserFactory::createOne();
        PaymentFactory::createMany(3, [
            'user' => $user,
        ]);
        PaymentFactory::createMany(2, [
            'user' => $other,
        ]);
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/payments');

        self::assertResponseIsSuccessful();
        $this->assertCount(3, $body);
    }

    public function testListPaymentsInvalidStatusIsIgnored(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        PaymentFactory::createMany(2, [
            'user' => $user,
        ]);
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/payments?status=invalid_status');

        // Invalid status is silently ignored — all payments are returned
        self::assertResponseIsSuccessful();
        $this->assertCount(2, $body);
    }

    public function testListPaymentsFiltersByStatus(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        PaymentFactory::createMany(2, [
            'user' => $user,
            'status' => PaymentStatus::APPROVED,
        ]);
        PaymentFactory::createOne([
            'user' => $user,
            'status' => PaymentStatus::PENDING,
        ]);
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/payments?status=approved');

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $body);
    }

    // ── detail ────────────────────────────────────────────────────────────────

    public function testDetailReturnsFullPaymentData(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $payment = PaymentFactory::createOne([
            'user' => $user,
        ]);
        $this->loginUser($client, $user);

        $body = $this->getJson($client, '/api/payments/' . $payment->getId());

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('transactions', $body);
        $this->assertArrayHasKey('refundedAmount', $body);
    }

    public function testDetailForbiddenForOtherUser(): void
    {
        $client = $this->createApiClient();
        $owner = UserFactory::createOne();
        $other = UserFactory::createOne();
        $payment = PaymentFactory::createOne([
            'user' => $owner,
        ]);
        $this->loginUser($client, $other);

        $this->getJson($client, '/api/payments/' . $payment->getId());

        self::assertResponseStatusCodeSame(403);
    }
}
