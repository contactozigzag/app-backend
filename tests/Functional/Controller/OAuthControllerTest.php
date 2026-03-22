<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Service\Payment\MercadoPagoOAuthService;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * /oauth/mercadopago/connect is behind the "oauth_connect" firewall
 * (stateless, JWT via ?access_token= query parameter).
 * /oauth/mercadopago/callback is PUBLIC_ACCESS (no auth needed).
 * /oauth/mercadopago/status is behind the "main" (form-login) firewall.
 */
final class OAuthControllerTest extends AbstractApiTestCase
{
    // ── /connect ──────────────────────────────────────────────────────────────

    public function testConnectRequiresAuthentication(): void
    {
        $client = $this->createApiClient();
        $client->request(Request::METHOD_GET, '/oauth/mercadopago/connect');

        // oauth_connect firewall is stateless — returns 401 without a token
        self::assertResponseStatusCodeSame(401);
    }

    public function testConnectRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_PARENT'],
        ]);
        $client->loginUser($user);

        $client->request(Request::METHOD_GET, '/oauth/mercadopago/connect');

        self::assertResponseStatusCodeSame(403);
    }

    public function testConnectRedirectsToMercadoPago(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();

        $oauthMock = $this->createMock(MercadoPagoOAuthService::class);
        $oauthMock
            ->expects($this->once())
            ->method('buildAuthorizationUrl')
            ->willReturn('https://auth.mercadopago.com/authorization?client_id=123&state=abc');
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->loginUser($driver->getUser());
        $client->request(Request::METHOD_GET, '/oauth/mercadopago/connect');

        self::assertResponseRedirects('https://auth.mercadopago.com/authorization?client_id=123&state=abc');
    }

    /**
     * End-to-end: mobile app opens /oauth/mercadopago/connect?access_token=<jwt>
     * in the system browser. The oauth_connect firewall authenticates via
     * the JWT in the query string and redirects to Mercado Pago.
     */
    public function testConnectWithJwtQueryParamRedirectsToMercadoPago(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $jwt = $jwtManager->createFromPayload($driver->getUser(), []);

        $oauthMock = $this->createMock(MercadoPagoOAuthService::class);
        $oauthMock
            ->expects($this->once())
            ->method('buildAuthorizationUrl')
            ->willReturn('https://auth.mercadopago.com/authorization?client_id=123&state=abc');
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->request(Request::METHOD_GET, '/oauth/mercadopago/connect?access_token=' . $jwt);

        self::assertResponseRedirects('https://auth.mercadopago.com/authorization?client_id=123&state=abc');
    }

    public function testConnectWithInvalidJwtReturns401(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/oauth/mercadopago/connect?access_token=invalid-jwt-token');

        self::assertResponseStatusCodeSame(401);
    }

    public function testConnectWithParentJwtReturns403(): void
    {
        $client = $this->createApiClient();
        $parent = UserFactory::createOne([
            'roles' => ['ROLE_PARENT'],
        ]);

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $jwt = $jwtManager->createFromPayload($parent, []);

        $client->request(Request::METHOD_GET, '/oauth/mercadopago/connect?access_token=' . $jwt);

        self::assertResponseStatusCodeSame(403);
    }

    // ── /callback ─────────────────────────────────────────────────────────────

    public function testCallbackMissingCodeAndStateReturns400(): void
    {
        $client = $this->createApiClient();
        $client->request(Request::METHOD_GET, '/oauth/mercadopago/callback');

        self::assertResponseStatusCodeSame(400);
    }

    public function testCallbackWithErrorParamReturns400(): void
    {
        $client = $this->createApiClient();
        $client->request(Request::METHOD_GET, '/oauth/mercadopago/callback?error=access_denied&error_description=User+denied+access');

        self::assertResponseStatusCodeSame(400);
        $html = $client->getResponse()->getContent();
        self::assertStringContainsString('access_denied', $html);
    }

    public function testCallbackInvalidStateReturns400(): void
    {
        $client = $this->createApiClient();
        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock
            ->method('handleCallback')
            ->willThrowException(new RuntimeException('Invalid or expired OAuth state parameter.'));
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->request(Request::METHOD_GET, '/oauth/mercadopago/callback?code=auth-code&state=invalid-state');

        self::assertResponseStatusCodeSame(400);
    }

    public function testCallbackSuccessReturnsDriverData(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::new()->withMpAuthorized('enc-token', 'enc-refresh', '123456789')->create();

        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock->method('handleCallback')->willReturn($driver);
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->request(Request::METHOD_GET, '/oauth/mercadopago/callback?code=valid-code&state=valid-state');

        self::assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        self::assertStringContainsString('123456789', $html);
        self::assertStringContainsString('vinculada', $html);
    }

    // ── /status ───────────────────────────────────────────────────────────────

    public function testStatusRequiresAuthentication(): void
    {
        $client = $this->createApiClient();
        $client->request(Request::METHOD_GET, '/oauth/mercadopago/status');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testStatusRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne([
            'roles' => ['ROLE_PARENT'],
        ]);
        $client->loginUser($user);

        $client->request(Request::METHOD_GET, '/oauth/mercadopago/status');

        self::assertResponseStatusCodeSame(403);
    }

    public function testStatusReturnsConnectedFalseForNewDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne(); // not MP-authorized

        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock->method('needsRefresh')->willReturn(false);
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->loginUser($driver->getUser());
        $body = $this->getJson($client, '/oauth/mercadopago/status');

        self::assertResponseIsSuccessful();
        $this->assertFalse($body['connected']);
        $this->assertNull($body['mp_account_id']);
    }

    public function testStatusReturnsConnectedTrueForAuthorizedDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::new()->withMpAuthorized()->create();

        $oauthMock = $this->createStub(MercadoPagoOAuthService::class);
        $oauthMock->method('needsRefresh')->willReturn(false);
        self::getContainer()->set(MercadoPagoOAuthService::class, $oauthMock);

        $client->loginUser($driver->getUser());
        $body = $this->getJson($client, '/oauth/mercadopago/status');

        self::assertResponseIsSuccessful();
        $this->assertTrue($body['connected']);
        $this->assertSame('987654321', $body['mp_account_id']);
    }
}
