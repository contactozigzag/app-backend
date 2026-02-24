<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Throwable;
use App\Message\ChatMessageCreatedMessage;
use App\Repository\ChatMessageRepository;
use App\Service\Payment\TokenEncryptor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ChatMessagePublishHandler
{
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly TokenEncryptor $tokenEncryptor,
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ChatMessageCreatedMessage $message): void
    {
        $chatMessage = $this->chatMessageRepository->find($message->chatMessageId);

        if ($chatMessage === null) {
            $this->logger->warning('ChatMessagePublishHandler: message not found', [
                'chatMessageId' => $message->chatMessageId,
            ]);

            return;
        }

        $decryptedContent = $this->tokenEncryptor->decrypt($chatMessage->getContent());

        $sender = $chatMessage->getSender();
        $senderName = $sender !== null ? $sender->getfullName() : 'Unknown';

        $payload = json_encode([
            'messageId' => $chatMessage->getId(),
            'senderName' => $senderName,
            'content' => $decryptedContent,
            'sentAt' => $chatMessage->getSentAt()->format('c'),
        ], JSON_THROW_ON_ERROR);

        $topic = sprintf('/chat/alert/%s', $message->alertId);

        try {
            $this->hub->publish(new Update($topic, $payload, true));
        } catch (Throwable $throwable) {
            $this->logger->error('ChatMessagePublishHandler: failed to publish', [
                'topic' => $topic,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
