<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Payment;

use App\Entity\Driver;
use App\Repository\DriverRepository;
use App\Service\Payment\MercadoPagoOAuthService;
use App\Service\Payment\TokenEncryptor;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;
use RuntimeException;

final class MercadoPagoOAuthServiceTest extends TestCase
{
    private CacheItemPoolInterface $cache;

    private EntityManagerInterface $em;

    private DriverRepository $driverRepo;

    private TokenEncryptor $encryptor;

    private MercadoPagoOAuthService $service;

    protected function setUp(): void
    {
        // Use stubs for collaborators that don't need expectation tracking in setUp.
        // Tests that need expectations re-configure them individually.
        $this->cache = $this->createStub(CacheItemPoolInterface::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->driverRepo = $this->createStub(DriverRepository::class);
        $this->encryptor = new TokenEncryptor(
            base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))
        );

        $this->service = $this->buildService();
    }

    private function buildService(
        ?CacheItemPoolInterface $cache = null,
        ?EntityManagerInterface $em = null,
        ?DriverRepository $repo = null,
    ): MercadoPagoOAuthService {
        return new MercadoPagoOAuthService(
            appId: '12345678',
            appSecret: 'test-secret',
            redirectUri: 'http://localhost/callback',
            statePool: $cache ?? $this->cache,
            entityManager: $em ?? $this->em,
            driverRepository: $repo ?? $this->driverRepo,
            tokenEncryptor: $this->encryptor,
            logger: new NullLogger(),
        );
    }

    // ── buildAuthorizationUrl ─────────────────────────────────────────────────

    public function testBuildAuthorizationUrlStoresStateInCache(): void
    {
        $driver = $this->makeDriver(42);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set')->with(42);
        $cacheItem->expects($this->once())->method('expiresAfter')->with(600);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('getItem')
            ->with(self::stringStartsWith('mp_oauth_state.'))
            ->willReturn($cacheItem);
        $cache->expects($this->once())->method('save')->with($cacheItem);

        $service = $this->buildService(cache: $cache);
        $url = $service->buildAuthorizationUrl($driver);

        $this->assertStringContainsString('12345678', $url);
        $this->assertStringContainsString('http', $url);
    }

    // ── handleCallback ────────────────────────────────────────────────────────

    public function testHandleCallbackThrowsOnInvalidState(): void
    {
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        $service = $this->buildService(cache: $cache);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired OAuth state');

        $service->handleCallback('auth-code', 'invalid-state-xyz');
    }

    // ── getAccessToken ────────────────────────────────────────────────────────

    public function testGetAccessTokenThrowsWhenDriverNotAuthorized(): void
    {
        $driver = $this->makeDriver(5);
        // hasMpAuthorized() returns false because mpAccessToken is null

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has not connected');

        $this->service->getAccessToken($driver);
    }

    public function testGetAccessTokenDecryptsToken(): void
    {
        $plaintext = 'APP-my-real-token';
        $encrypted = $this->encryptor->encrypt($plaintext);

        $driver = $this->makeDriver(7);
        $driver->setMpAccessToken($encrypted);
        $driver->setMpAccountId('987');
        $driver->setMpRefreshToken($this->encryptor->encrypt('some-refresh'));
        $driver->setMpTokenExpiresAt(new DateTimeImmutable('+90 days'));

        $result = $this->service->getAccessToken($driver);

        $this->assertSame($plaintext, $result);
    }

    // ── needsRefresh ──────────────────────────────────────────────────────────

    public function testNeedsRefreshReturnsFalseWithNoRefreshToken(): void
    {
        $driver = $this->makeDriver(1);
        // mpRefreshToken is null by default in our makeDriver helper

        $this->assertFalse($this->service->needsRefresh($driver));
    }

    public function testNeedsRefreshReturnsTrueWhenTokenExpiresSoon(): void
    {
        $driver = $this->makeDriver(2);
        $driver->setMpRefreshToken('enc-refresh');
        $driver->setMpTokenExpiresAt(new DateTimeImmutable('+1 hour')); // within 24h buffer

        $this->assertTrue($this->service->needsRefresh($driver));
    }

    public function testNeedsRefreshReturnsFalseWhenTokenIsFresh(): void
    {
        $driver = $this->makeDriver(3);
        $driver->setMpRefreshToken('enc-refresh');
        $driver->setMpTokenExpiresAt(new DateTimeImmutable('+30 days')); // well beyond buffer

        $this->assertFalse($this->service->needsRefresh($driver));
    }

    public function testNeedsRefreshReturnsTrueWhenNoExpiryOnRecord(): void
    {
        $driver = $this->makeDriver(4);
        $driver->setMpRefreshToken('enc-refresh');
        // mpTokenExpiresAt is null → treat as expired

        $this->assertTrue($this->service->needsRefresh($driver));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeDriver(int $id): Driver
    {
        $driver = new Driver();
        // Reflection needed because $id is generated by the DB; we just need a
        // dummy value for log messages and assertions in unit tests.
        $ref = new ReflectionProperty($driver, 'id');
        $ref->setValue($driver, $id);

        return $driver;
    }
}
