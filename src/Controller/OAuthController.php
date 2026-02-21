<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Payment\MercadoPagoOAuthService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/oauth/mercadopago')]
class OAuthController extends AbstractController
{
    public function __construct(
        private readonly MercadoPagoOAuthService $oauthService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Step 1 — driver initiates the OAuth flow.
     *
     * Requires ROLE_DRIVER. Generates a CSRF state, stores it in Redis,
     * then redirects the browser to Mercado Pago's authorization page.
     */
    #[Route('/connect', name: 'oauth_mp_connect', methods: ['GET'])]
    #[IsGranted('ROLE_DRIVER')]
    public function connect(): RedirectResponse
    {
        /** @var User $user */
        $user   = $this->getUser();
        $driver = $user->getDriver();

        if ($driver === null) {
            throw $this->createNotFoundException('No driver profile found for this user.');
        }

        $authUrl = $this->oauthService->buildAuthorizationUrl($driver);

        $this->logger->info('Driver initiating MP OAuth flow', ['driver_id' => $driver->getId()]);

        return new RedirectResponse($authUrl);
    }

    /**
     * Step 2 — Mercado Pago posts back with an authorization code.
     *
     * This is a PUBLIC route: the browser is redirected here by MP without
     * sending the driver's JWT. The driver is identified solely via the
     * cryptographic `state` parameter validated against Redis.
     *
     * On success the driver's tokens are stored encrypted in the DB and a
     * JSON response is returned. In a production front-end you would redirect
     * to the app instead (e.g. deep-link or SPA route).
     */
    #[Route('/callback', name: 'oauth_mp_callback', methods: ['GET'])]
    public function callback(Request $request): JsonResponse
    {
        // MP sends `error` when the driver denies access
        $error = $request->query->get('error');
        if ($error !== null) {
            $this->logger->warning('MP OAuth denied by driver', [
                'error'             => $error,
                'error_description' => $request->query->get('error_description'),
            ]);

            return new JsonResponse(
                ['error' => 'Authorization denied by Mercado Pago: ' . $error],
                Response::HTTP_BAD_REQUEST
            );
        }

        $code  = $request->query->get('code');
        $state = $request->query->get('state');

        if ($code === null || $state === null) {
            return new JsonResponse(
                ['error' => 'Missing required parameters: code, state'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $driver = $this->oauthService->handleCallback($code, $state);

            return new JsonResponse([
                'message'        => 'Mercado Pago account connected successfully.',
                'driver_id'      => $driver->getId(),
                'mp_account_id'  => $driver->getMpAccountId(),
            ]);
        } catch (\RuntimeException $e) {
            $this->logger->error('MP OAuth callback failed', ['error' => $e->getMessage()]);

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Returns the OAuth connection status for the authenticated driver.
     * Useful for the front-end to decide whether to show the "Connect MP" button.
     */
    #[Route('/status', name: 'oauth_mp_status', methods: ['GET'])]
    #[IsGranted('ROLE_DRIVER')]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user   = $this->getUser();
        $driver = $user->getDriver();

        if ($driver === null) {
            throw $this->createNotFoundException('No driver profile found for this user.');
        }

        return new JsonResponse([
            'connected'        => $driver->hasMpAuthorized(),
            'mp_account_id'    => $driver->getMpAccountId(),
            'token_expires_at' => $driver->getMpTokenExpiresAt()?->format('c'),
            'needs_refresh'    => $this->oauthService->needsRefresh($driver),
        ]);
    }
}
