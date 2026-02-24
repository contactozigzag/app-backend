<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use DateTimeImmutable;
use App\Entity\User;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Issues short-lived Mercure subscriber JWTs to authenticated parents so they
 * can receive real-time payment status updates pushed by PaymentEventSubscriber.
 *
 * The hub publishes private updates on the topic "/payments/{id}", so the
 * browser/app needs a signed subscribe JWT that matches that topic before the
 * Mercure hub will forward the event.
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

    /**
     * Returns a Mercure subscriber JWT scoped to a single payment topic.
     *
     * Query parameters:
     *   payment_id  (int, required) — the payment the client wants to subscribe to.
     *
     * Response:
     *   {
     *     "token":   "<JWT>",
     *     "hub_url": "<Mercure hub public URL>",
     *     "topics":  ["/payments/{id}"]
     *   }
     */
    #[Route('/api/mercure/token', name: 'api_mercure_token', methods: ['GET'])]
    public function token(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $paymentId = $request->query->get('payment_id');

        if ($paymentId === null || ! ctype_digit((string) $paymentId)) {
            return new JsonResponse(
                [
                    'error' => 'Missing or invalid payment_id query parameter.',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $payment = $this->paymentRepository->find((int) $paymentId);

        if ($payment === null) {
            return new JsonResponse(
                [
                    'error' => 'Payment not found.',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        // Only the payment owner may subscribe to its topic.
        if ($payment->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(
                [
                    'error' => 'Access denied.',
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        $factory = $this->hub->getFactory();

        if (! $factory instanceof TokenFactoryInterface) {
            return new JsonResponse(
                [
                    'error' => 'Mercure hub is not configured with a token factory.',
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $topic = '/payments/' . $payment->getId();

        $token = $factory->create(
            subscribe: [$topic],
            publish: [],
            additionalClaims: [
                'exp' => new DateTimeImmutable('+' . self::TOKEN_TTL . ' seconds'),
            ],
        );

        return new JsonResponse([
            'token' => $token,
            'hub_url' => $this->hub->getPublicUrl(),
            'topics' => [$topic],
        ]);
    }
}
