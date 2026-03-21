<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Enum\PricingModel;
use App\Service\Payment\MercadoPagoOAuthService;
use App\Service\Payment\MercadoPagoService;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\DriverRateFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;

final class PaymentWithDriverRateTest extends AbstractApiTestCase
{
    public function testCreatePreferenceCalculatesAmountFromFlatRate(): void
    {
        $client = $this->createApiClient();

        // Mock MP services
        $mpService = $this->createStub(MercadoPagoService::class);
        $mpService->method('createPreference')->willReturn([
            'preference_id' => 'pref_123',
            'init_point' => 'https://mp.test/init',
            'sandbox_init_point' => 'https://mp.test/sandbox',
        ]);

        $oauthService = $this->createStub(MercadoPagoOAuthService::class);
        $oauthService->method('getAccessToken')->willReturn('test-access-token');

        self::getContainer()->set(MercadoPagoService::class, $mpService);
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthService);

        $user = UserFactory::createOne();
        $driver = DriverFactory::new()->withMpAuthorized()->with([
            'pricingModel' => PricingModel::FLAT,
        ])->create();
        DriverRateFactory::new()->flat('1500.00')->with([
            'driver' => $driver,
        ])->create();
        $student = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/payments/preference', [
            'driverId' => $driver->getId(),
            'studentIds' => [$student->getId()],
            'description' => 'Monthly payment',
            'idempotencyKey' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertSame('1500.00', $body['amount']);
        $this->assertSame('ARS', $body['currency']);
    }

    public function testCreatePreferenceCalculatesPerStudentAmount(): void
    {
        $client = $this->createApiClient();

        $mpService = $this->createStub(MercadoPagoService::class);
        $mpService->method('createPreference')->willReturn([
            'preference_id' => 'pref_456',
            'init_point' => 'https://mp.test/init',
            'sandbox_init_point' => 'https://mp.test/sandbox',
        ]);

        $oauthService = $this->createStub(MercadoPagoOAuthService::class);
        $oauthService->method('getAccessToken')->willReturn('test-access-token');

        self::getContainer()->set(MercadoPagoService::class, $mpService);
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthService);

        $user = UserFactory::createOne();
        $driver = DriverFactory::new()->withMpAuthorized()->with([
            'pricingModel' => PricingModel::PER_STUDENT,
        ])->create();
        DriverRateFactory::new()->perStudent('500.00')->with([
            'driver' => $driver,
        ])->create();
        $student1 = StudentFactory::new()->withParent($user)->create();
        $student2 = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/payments/preference', [
            'driverId' => $driver->getId(),
            'studentIds' => [$student1->getId(), $student2->getId()],
            'description' => 'Monthly payment - 2 students',
            'idempotencyKey' => 'a47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertSame('1000.00', $body['amount']);
    }

    public function testCreatePreferenceFailsWithoutDriverRate(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $driver = DriverFactory::new()->withMpAuthorized()->with([
            'pricingModel' => PricingModel::FLAT,
        ])->create();
        // No rate created for this driver
        $student = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/payments/preference', [
            'driverId' => $driver->getId(),
            'studentIds' => [$student->getId()],
            'description' => 'Monthly payment',
            'idempotencyKey' => 'b47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatePreferenceFailsWithoutPricingModel(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        // Driver with no pricing model set
        $driver = DriverFactory::new()->withMpAuthorized()->create();
        $student = StudentFactory::new()->withParent($user)->create();
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/payments/preference', [
            'driverId' => $driver->getId(),
            'studentIds' => [$student->getId()],
            'description' => 'Monthly payment',
            'idempotencyKey' => 'c47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

        self::assertResponseStatusCodeSame(422);
    }
}
