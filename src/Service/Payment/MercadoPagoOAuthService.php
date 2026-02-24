<?php

declare(strict_types=1);

namespace App\Service\Payment;

use RuntimeException;
use DateTimeImmutable;
use App\Entity\Driver;
use App\Repository\DriverRepository;
use Doctrine\ORM\EntityManagerInterface;
use MercadoPago\Client\OAuth\OAuthClient;
use MercadoPago\Client\OAuth\OAuthCreateRequest;
use MercadoPago\Client\OAuth\OAuthRefreshRequest;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Resources\OAuth;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages the Mercado Pago Marketplace OAuth flow.
 *
 * Flow:
 *   1. buildAuthorizationUrl()  — generate MP authorization URL + store CSRF state
 *   2. handleCallback()         — validate state, exchange code → tokens, persist encrypted
 *   3. getAccessToken()         — decrypt + refresh-if-needed before each payment
 */
class MercadoPagoOAuthService
{
    /**
     * CSRF state TTL: how long the driver has to complete the MP authorization page.
     * 10 minutes is generous; MP redirects back almost immediately.
     */
    private const int STATE_TTL = 600;

    /**
     * Refresh threshold: refresh the token when fewer than 1 day remain.
     * MP tokens expire after 180 days (15 552 000 s), so this leaves plenty of room.
     */
    private const int REFRESH_BUFFER_SECONDS = 86400;

    private readonly OAuthClient $oauthClient;

    public function __construct(
        #[Autowire(env: 'MERCADOPAGO_APP_ID')]
        private readonly string $appId,
        #[Autowire(env: 'MERCADOPAGO_APP_SECRET')]
        private readonly string $appSecret,
        #[Autowire(env: 'MERCADOPAGO_OAUTH_REDIRECT_URI')]
        private readonly string $redirectUri,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $statePool,
        private readonly EntityManagerInterface $entityManager,
        private readonly DriverRepository $driverRepository,
        private readonly TokenEncryptor $tokenEncryptor,
        private readonly LoggerInterface $logger,
    ) {
        // OAuthClient does not need a global access token — credentials travel
        // in the request body (client_id / client_secret), not as a Bearer header.
        $this->oauthClient = new OAuthClient();
    }

    // ── Step 1: redirect ─────────────────────────────────────────────────────

    /**
     * Generate the Mercado Pago authorization URL.
     * The random `state` is stored in Redis keyed by its value so it can be
     * validated — and consumed — when MP redirects back.
     */
    public function buildAuthorizationUrl(Driver $driver): string
    {
        $state = bin2hex(random_bytes(16)); // 32-char hex — CSRF token

        $item = $this->statePool->getItem($this->stateKey($state));
        $item->set($driver->getId());
        $item->expiresAfter(self::STATE_TTL);

        $this->statePool->save($item);

        $this->logger->info('MP OAuth: generated state for driver', [
            'driver_id' => $driver->getId(),
        ]);

        return $this->oauthClient->getAuthorizationURL($this->appId, $this->redirectUri, $state);
    }

    // ── Step 2: callback ─────────────────────────────────────────────────────
    /**
     * Validate the CSRF state, exchange the authorization code for tokens,
     * encrypt and persist them on the Driver entity.
     *
     * Returns the Driver so the controller can read its updated public fields.
     *
     * @throws RuntimeException on invalid state or MP API error
     */
    public function handleCallback(string $code, string $state): Driver
    {
        $driverId = $this->consumeState($state);

        $driver = $this->driverRepository->find($driverId);
        if ($driver === null) {
            throw new RuntimeException(sprintf('Driver %d not found after OAuth callback.', $driverId));
        }

        $request = new OAuthCreateRequest();
        $request->client_id = $this->appId;
        $request->client_secret = $this->appSecret;
        $request->code = $code;
        $request->redirect_uri = $this->redirectUri;

        try {
            $oauth = $this->oauthClient->create($request);
        } catch (MPApiException $mpApiException) {
            $this->logger->error('MP OAuth token exchange failed', [
                'driver_id' => $driverId,
                'error' => $mpApiException->getMessage(),
            ]);
            throw new RuntimeException(
                'Mercado Pago token exchange failed: ' . $mpApiException->getMessage(),
                0,
                $mpApiException
            );
        }

        $this->persistTokens($driver, $oauth);

        $this->logger->info('MP OAuth completed — driver account connected', [
            'driver_id' => $driver->getId(),
            'mp_account_id' => $driver->getMpAccountId(),
        ]);

        return $driver;
    }

    // ── Step 3: token access ─────────────────────────────────────────────────
    /**
     * Return the driver's plaintext MP access token, refreshing it first if
     * it expires within the next 24 hours.
     *
     * This is the single call-site that payment creation should use.
     *
     * @throws RuntimeException if the driver has not authorized yet
     */
    public function getAccessToken(Driver $driver): string
    {
        if (! $driver->hasMpAuthorized()) {
            throw new RuntimeException(
                sprintf('Driver %s has not connected their Mercado Pago account.', $driver->getId())
            );
        }

        $this->refreshIfNeeded($driver);

        return $this->tokenEncryptor->decrypt($driver->getMpAccessToken());
    }

    // ── Token refresh ─────────────────────────────────────────────────────────

    /**
     * Refresh the access token if it is within REFRESH_BUFFER_SECONDS of expiry.
     * No-op when the token is still fresh — safe to call unconditionally.
     */
    public function refreshIfNeeded(Driver $driver): void
    {
        if (! $this->needsRefresh($driver)) {
            return;
        }

        $this->logger->info('MP OAuth: refreshing access token for driver', [
            'driver_id' => $driver->getId(),
            'token_expires_at' => $driver->getMpTokenExpiresAt()?->format('c'),
        ]);

        $refreshToken = $this->tokenEncryptor->decrypt($driver->getMpRefreshToken());

        $request = new OAuthRefreshRequest();
        $request->client_id = $this->appId;
        $request->client_secret = $this->appSecret;
        $request->refresh_token = $refreshToken;

        try {
            $oauth = $this->oauthClient->refresh($request);
        } catch (MPApiException $mpApiException) {
            $this->logger->error('MP OAuth token refresh failed', [
                'driver_id' => $driver->getId(),
                'error' => $mpApiException->getMessage(),
            ]);
            throw new RuntimeException(
                'Mercado Pago token refresh failed: ' . $mpApiException->getMessage(),
                0,
                $mpApiException
            );
        }

        $this->persistTokens($driver, $oauth);

        $this->logger->info('MP OAuth token refreshed', [
            'driver_id' => $driver->getId(),
        ]);
    }

    public function needsRefresh(Driver $driver): bool
    {
        if ($driver->getMpRefreshToken() === null) {
            return false;
        }

        $expiresAt = $driver->getMpTokenExpiresAt();
        if (! $expiresAt instanceof DateTimeImmutable) {
            return true; // no expiry on record — treat as expired
        }

        $threshold = new DateTimeImmutable('+' . self::REFRESH_BUFFER_SECONDS . ' seconds');

        return $expiresAt <= $threshold;
    }

    // ── Private helpers ───────────────────────────────────────────────────────
    /**
     * Validate the CSRF state, consume it (single-use), and return the driver ID.
     *
     * @throws RuntimeException on invalid or expired state
     */
    private function consumeState(string $state): int
    {
        $item = $this->statePool->getItem($this->stateKey($state));

        if (! $item->isHit()) {
            $this->logger->warning('MP OAuth callback: invalid or expired state', [
                'state' => $state,
            ]);
            throw new RuntimeException('Invalid or expired OAuth state parameter.');
        }

        $driverId = (int) $item->get();

        // Delete immediately — state tokens are single-use
        $this->statePool->deleteItem($this->stateKey($state));

        return $driverId;
    }

    private function persistTokens(Driver $driver, OAuth $oauth): void
    {
        $driver->setMpAccountId((string) $oauth->user_id);
        $driver->setMpAccessToken($this->tokenEncryptor->encrypt($oauth->access_token));
        $driver->setMpRefreshToken($this->tokenEncryptor->encrypt($oauth->refresh_token));
        $driver->setMpTokenExpiresAt(
            new DateTimeImmutable('+' . $oauth->expires_in . ' seconds')
        );

        $this->entityManager->flush();
    }

    private function stateKey(string $state): string
    {
        return 'mp_oauth_state.' . $state;
    }
}
