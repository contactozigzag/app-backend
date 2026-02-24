<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Enum\AlertStatus;
use App\Message\ChatMessageCreatedMessage;
use App\Repository\ChatMessageRepository;
use App\Repository\DriverAlertRepository;
use App\Service\Payment\TokenEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(name: 'api_chat_')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly DriverAlertRepository $driverAlertRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly TokenEncryptor $tokenEncryptor,
    ) {
    }

    /**
     * Post a message to the emergency chat thread for an alert.
     */
    #[Route('/api/driver-alerts/{alertId}/messages', name: 'post_message', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function postMessage(string $alertId, Request $request): JsonResponse
    {
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if ($alert === null) {
            return $this->json([
                'error' => 'Alert not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if (! $this->isParticipant($alert)) {
            return $this->json([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($alert->getStatus() === AlertStatus::RESOLVED) {
            return $this->json([
                'error' => 'Chat is read-only for resolved alerts',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (! is_string($content) || $content === '') {
            return $this->json([
                'error' => 'content is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $chatMessage = new ChatMessage();
        $chatMessage->setAlert($alert);
        $chatMessage->setSender($user);
        $chatMessage->setContent($this->tokenEncryptor->encrypt($content));

        $this->entityManager->persist($chatMessage);
        $this->entityManager->flush();

        $this->bus->dispatch(new ChatMessageCreatedMessage((int) $chatMessage->getId(), $alert->getAlertId()));

        return $this->json([
            'id' => $chatMessage->getId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Get paginated messages for an alert's chat thread.
     */
    #[Route('/api/driver-alerts/{alertId}/messages', name: 'get_messages', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMessages(string $alertId, Request $request): JsonResponse
    {
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if ($alert === null) {
            return $this->json([
                'error' => 'Alert not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if (! $this->isParticipant($alert)) {
            return $this->json([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $messages = $this->chatMessageRepository->findByAlert($alert, $page, $limit);

        $result = array_map(function (ChatMessage $m): array {
            $sender = $m->getSender();

            return [
                'id' => $m->getId(),
                'sender' => [
                    'id' => $sender?->getId(),
                    'name' => $sender?->getfullName(),
                ],
                'content' => $this->tokenEncryptor->decrypt($m->getContent()),
                'sentAt' => $m->getSentAt()->format('c'),
                'readBy' => $m->getReadBy(),
            ];
        }, $messages);

        return $this->json([
            'alertId' => $alertId,
            'page' => $page,
            'limit' => $limit,
            'count' => count($result),
            'messages' => $result,
        ]);
    }

    /**
     * Determine if the current user is a chat participant for the given alert.
     */
    private function isParticipant(\App\Entity\DriverAlert $alert): bool
    {
        if ($this->isGranted('ROLE_SCHOOL_ADMIN')) {
            return true;
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();

        if ($driver === null) {
            return false;
        }

        if ($alert->getDistressedDriver()?->getId() === $driver->getId()) {
            return true;
        }

        if ($alert->getRespondingDriver()?->getId() === $driver->getId()) {
            return true;
        }

        return false;
    }
}
