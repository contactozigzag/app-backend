<?php

declare(strict_types=1);

namespace App\State\Payment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use ValueError;

/**
 * State provider for GET /api/payments (collection).
 *
 * Always scopes results to the authenticated user's own payments.
 * Supports optional ?status= filter using the PaymentStatus enum.
 *
 * @implements ProviderInterface<Payment>
 */
final readonly class PaymentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private Security $security,
    ) {
    }

    /**
     * @return Payment[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $request = $context['request'] instanceof Request ? $context['request'] : null;

        $status = $request?->query->get('status');
        $limit = min((int) ($request?->query->get('itemsPerPage', 30) ?? 30), 100);
        $page = max(1, (int) ($request?->query->get('page', 1) ?? 1));
        $offset = ($page - 1) * $limit;

        $paymentStatus = null;
        if ($status !== null) {
            try {
                $paymentStatus = PaymentStatus::from($status);
            } catch (ValueError) {
                // Invalid status value — ignore filter, return all
            }
        }

        return $this->paymentRepository->findByUser($user, $paymentStatus, $limit, $offset);
    }
}
