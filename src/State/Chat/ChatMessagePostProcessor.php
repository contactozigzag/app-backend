<?php

declare(strict_types=1);

namespace App\State\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Chat\ChatMessageIdOutput;
use App\Dto\Chat\ChatMessageInput;
use App\Entity\ChatMessage;
use App\Entity\DriverAlert;
use App\Entity\User;
use App\Enum\AlertStatus;
use App\Message\ChatMessageCreatedMessage;
use App\Repository\DriverAlertRepository;
use App\Service\Payment\TokenEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles POST /api/driver-alerts/{alertId}/messages.
 *
 * Validates participant access, encrypts and stores the message, and
 * dispatches ChatMessageCreatedMessage for async Mercure notification.
 *
 * @implements ProcessorInterface<ChatMessageInput, ChatMessageIdOutput>
 */
final readonly class ChatMessagePostProcessor implements ProcessorInterface
{
    public function __construct(
        private DriverAlertRepository $driverAlertRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private TokenEncryptor $tokenEncryptor,
        private Security $security,
    ) {
    }

    /**
     * @param ChatMessageInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChatMessageIdOutput
    {
        $alertId = is_string($uriVariables['alertId'] ?? null) ? $uriVariables['alertId'] : '';
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if (! $alert instanceof DriverAlert) {
            throw new NotFoundHttpException('Alert not found.');
        }

        if (! $this->isParticipant($alert)) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        if ($alert->getStatus() === AlertStatus::RESOLVED) {
            throw new UnprocessableEntityHttpException('Chat is read-only for resolved alerts.');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $chatMessage = new ChatMessage();
        $chatMessage->setAlert($alert);
        $chatMessage->setSender($user);
        $chatMessage->setContent($this->tokenEncryptor->encrypt($data->content));

        $this->entityManager->persist($chatMessage);
        $this->entityManager->flush();

        $this->bus->dispatch(new ChatMessageCreatedMessage((int) $chatMessage->getId(), $alert->getAlertId()));

        return new ChatMessageIdOutput(id: (int) $chatMessage->getId());
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
