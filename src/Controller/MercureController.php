<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\PaymentRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Issues short-lived Mercure subscriber JWTs so authenticated clients can
 * receive real-time updates from the Mercure hub.
 *
 * Supported query parameters (mutually exclusive):
 *   - payment_id → subscribes to /payments/{id}
 *   - user_id    → subscribes to /api/users/{id}/notifications
 *
 * ── Two completely separate JWTs ─────────────────────────────────────────────
 *
 *  1. API authentication JWT  (issued by lexik/jwt-authentication-bundle)
 *     • Obtained via POST /api/login_check with user credentials.
 *     • Signed with an RSA key-pair (JWT_SECRET_KEY / JWT_PUBLIC_KEY env vars).
 *     • Sent by the client as "Authorization: Bearer <token>" on every API call.
 *     • Tells **Symfony** who the caller is; never sent to the Mercure hub.
 *
 *  2. Mercure subscriber JWT  (issued by this controller)
 *     • Obtained via GET /api/mercure/token (requires a valid API auth JWT above).
 *     • Signed with a symmetric HMAC-SHA256 key (MERCURE_JWT_SECRET env var).
 *     • Contains a "mercure.subscribe" claim listing the topics the client may
 *       listen to.  Has nothing to do with user identity in Symfony.
 *     • Sent by the client to the **Mercure hub** only, either as the
 *       "Authorization: Bearer <token>" header on the EventSource request or
 *       via the "mercureAuthorization" cookie set by the hub's own endpoint.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */
#[IsGranted('ROLE_USER')]
class MercureController extends AbstractController
{
    /**
     * How long (seconds) the subscriber JWT remains valid.
     */
    private const int TOKEN_TTL = 3600; // 1 hour

    public function __construct(
        private readonly HubInterface $hub,
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    #[Route('/api/mercure/token', name: 'api_mercure_token', methods: ['GET'])]
    public function token(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->query->has('user_id')) {
            return $this->handleUserToken($request, $user);
        }

        if ($request->query->has('payment_id')) {
            return $this->handlePaymentToken($request, $user);
        }

        return new JsonResponse(
            [
                'error' => 'Missing query parameter. Provide either payment_id or user_id.',
            ],
            Response::HTTP_BAD_REQUEST,
        );
    }

    private function handleUserToken(Request $request, User $user): JsonResponse
    {
        $userId = $request->query->get('user_id');

        if (! ctype_digit((string) $userId)) {
            return new JsonResponse(
                [
                    'error' => 'Invalid user_id query parameter.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Users can only subscribe to their own notification topic.
        if ((int) $userId !== $user->getId()) {
            return new JsonResponse(
                [
                    'error' => 'Access denied.',
                ],
                Response::HTTP_FORBIDDEN,
            );
        }

        $topic = sprintf('/api/users/%d/notifications', $user->getId());

        return $this->createTokenResponse([$topic]);
    }

    private function handlePaymentToken(Request $request, User $user): JsonResponse
    {
        $paymentId = $request->query->get('payment_id');

        if (! ctype_digit((string) $paymentId)) {
            return new JsonResponse(
                [
                    'error' => 'Invalid payment_id query parameter.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $payment = $this->paymentRepository->find((int) $paymentId);

        if ($payment === null) {
            return new JsonResponse(
                [
                    'error' => 'Payment not found.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        // Only the payment owner may subscribe to its topic.
        if ($payment->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(
                [
                    'error' => 'Access denied.',
                ],
                Response::HTTP_FORBIDDEN,
            );
        }

        $topic = '/payments/' . $payment->getId();

        return $this->createTokenResponse([$topic]);
    }

    /**
     * @param list<string> $topics
     */
    private function createTokenResponse(array $topics): JsonResponse
    {
        $factory = $this->hub->getFactory();

        if (! $factory instanceof TokenFactoryInterface) {
            return new JsonResponse(
                [
                    'error' => 'Mercure hub is not configured with a token factory.',
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $token = $factory->create(
            subscribe: $topics,
            publish: [],
            additionalClaims: [
                'exp' => new DateTimeImmutable('+' . self::TOKEN_TTL . ' seconds'),
            ],
        );

        return new JsonResponse([
            'token' => $token,
            'hub_url' => $this->hub->getPublicUrl(),
            'topics' => $topics,
        ]);
    }
}
