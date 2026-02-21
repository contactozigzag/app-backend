<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Service\Payment\MercadoPagoOAuthService;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;

/**
 * The /oauth/mercadopago/* routes are under the "main" (form-login) firewall,
 * not the stateless JWT "api" firewall.  Authentication is done via
 * $client->loginUser() which stores a TestBrowserToken in the session.
 *
 * Unauthenticated requests redirect to /login (302), not 401.
 */
final class OAuthControllerTest extends AbstractApiTestCase
{
    // ── /connect ──────────────────────────────────────────────────────────────

    public function testConnectRequiresAuthentication(): void
    {
        $client = $this->createApiClient();
        $client->request('GET', '/oauth/mercadopago/connect');

        // main firewall redirects unauthenticated users to /login
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testConnectRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user   = UserFactory::createOne(['roles' => ['ROLE_PARENT']]);
        $client->loginUser($user);

        $client->request('GET', '/oauth/mercadopago/connect');

        self::assertResponseStatusCodeSame(403);
    }

    public function testConnectRedirectsToMercadoPago(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();

        $oauthMock = $this->createMock(MercadoPagoOAuthService::class);
        $oauthMock
            ->expects(self::once())
            ->method('buildAuthorizationUrl')
            ->willReturn('https://auth.mercadopago.com/authorization?client_id=123&state=abc');
        static::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->loginUser($driver->getUser());
        $client->request('GET', '/oauth/mercadopago/connect');

        self::assertResponseRedirects('https://auth.mercadopago.com/authorization?client_id=123&state=abc');
    }

    // ── /callback ─────────────────────────────────────────────────────────────

    public function testCallbackMissingCodeAndStateReturns400(): void
    {
        $client = $this->createApiClient();
        $this->getJson($client, '/oauth/mercadopago/callback');

        self::assertResponseStatusCodeSame(400);
    }

    public function testCallbackWithErrorParamReturns400(): void
    {
        $client = $this->createApiClient();
        $this->getJson($client, '/oauth/mercadopago/callback?error=access_denied&error_description=User+denied+access');

        self::assertResponseStatusCodeSame(400);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('access_denied', $body['error']);
    }

    public function testCallbackInvalidStateReturns400(): void
    {
        $client    = $this->createApiClient();
        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock
            ->method('handleCallback')
            ->willThrowException(new \RuntimeException('Invalid or expired OAuth state parameter.'));
        static::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $this->getJson($client, '/oauth/mercadopago/callback?code=auth-code&state=invalid-state');

        self::assertResponseStatusCodeSame(400);
    }

    public function testCallbackSuccessReturnsDriverData(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::new()->withMpAuthorized('enc-token', 'enc-refresh', '123456789')->create();

        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock->method('handleCallback')->willReturn($driver);
        static::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $body = $this->getJson($client, '/oauth/mercadopago/callback?code=valid-code&state=valid-state');

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('driver_id', $body);
        self::assertSame('123456789', $body['mp_account_id']);
    }

    // ── /status ───────────────────────────────────────────────────────────────

    public function testStatusRequiresAuthentication(): void
    {
        $client = $this->createApiClient();
        $client->request('GET', '/oauth/mercadopago/status');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testStatusRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user   = UserFactory::createOne(['roles' => ['ROLE_PARENT']]);
        $client->loginUser($user);

        $client->request('GET', '/oauth/mercadopago/status');

        self::assertResponseStatusCodeSame(403);
    }

    public function testStatusReturnsConnectedFalseForNewDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne(); // not MP-authorized

        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock->method('needsRefresh')->willReturn(false);
        static::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->loginUser($driver->getUser());
        $body = $this->getJson($client, '/oauth/mercadopago/status');

        self::assertResponseIsSuccessful();
        self::assertFalse($body['connected']);
        self::assertNull($body['mp_account_id']);
    }

    public function testStatusReturnsConnectedTrueForAuthorizedDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::new()->withMpAuthorized()->create();

        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock->method('needsRefresh')->willReturn(false);
        static::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->loginUser($driver->getUser());
        $body = $this->getJson($client, '/oauth/mercadopago/status');

        self::assertResponseIsSuccessful();
        self::assertTrue($body['connected']);
        self::assertSame('987654321', $body['mp_account_id']);
    }
}
