<?php

declare(strict_types=1);

namespace App\State\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Chat\ChatMessageListOutput;
use App\Entity\ChatMessage;
use App\Entity\DriverAlert;
use App\Entity\User;
use App\Repository\ChatMessageRepository;
use App\Repository\DriverAlertRepository;
use App\Service\Payment\TokenEncryptor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles GET /api/driver-alerts/{alertId}/messages.
 *
 * Returns paginated and decrypted chat messages for the given alert.
 * Access is restricted to chat participants (distressed driver, responding
 * driver, or school admin).
 *
 * @implements ProviderInterface<ChatMessageListOutput>
 */
final readonly class ChatMessageListProvider implements ProviderInterface
{
    public function __construct(
        private DriverAlertRepository $driverAlertRepository,
        private ChatMessageRepository $chatMessageRepository,
        private TokenEncryptor $tokenEncryptor,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChatMessageListOutput
    {
        $alertId = is_string($uriVariables['alertId'] ?? null) ? $uriVariables['alertId'] : '';
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if (! $alert instanceof DriverAlert) {
            throw new NotFoundHttpException('Alert not found.');
        }

        if (! $this->isParticipant($alert)) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        $request = $context['request'] instanceof Request ? $context['request'] : null;
        $page = max(1, (int) $request?->query->get('page', 1));
        $limit = min(50, max(1, (int) $request?->query->get('limit', 20)));

        $messages = $this->chatMessageRepository->findByAlert($alert, $page, $limit);

        $result = array_values(array_map(function (ChatMessage $m): array {
            $sender = $m->getSender();

            return [
                'id' => $m->getId(),
                'sender' => [
                    'id' => $sender?->getId(),
                    'name' => $sender?->getfullName() ?? '',
                ],
                'content' => $this->tokenEncryptor->decrypt($m->getContent()),
                'sentAt' => $m->getSentAt()->format('c'),
                'readBy' => $m->getReadBy(),
            ];
        }, $messages));

        return new ChatMessageListOutput(
            alertId: $alertId,
            page: $page,
            limit: $limit,
            count: count($result),
            messages: $result,
        );
    }

    private function isParticipant(DriverAlert $alert): bool
    {
        if ($this->security->isGranted('ROLE_SCHOOL_ADMIN')) {
            return true;
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $driver = $user->getDriver();

        if ($driver === null) {
            return false;
        }

        if ($alert->getDistressedDriver()?->getId() === $driver->getId()) {
            return true;
        }

        return $alert->getRespondingDriver()?->getId() === $driver->getId();
    }
}
