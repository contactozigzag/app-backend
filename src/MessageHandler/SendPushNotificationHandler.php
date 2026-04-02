<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\PushTicket;
use App\Message\SendPushNotification;
use App\Repository\PushDeviceRepository;
use App\Repository\PushTicketRepository;
use App\Service\Push\ExpoPushService;
use Dru1x\ExpoPush\PushMessage\Priority;
use Dru1x\ExpoPush\PushMessage\PushMessage;
use Dru1x\ExpoPush\PushMessage\PushMessageCollection;
use Dru1x\ExpoPush\PushTicket\FailedPushTicket;
use Dru1x\ExpoPush\PushTicket\PushTicketErrorCode;
use Dru1x\ExpoPush\PushTicket\SuccessfulPushTicket;
use Dru1x\ExpoPush\PushToken\PushToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\UuidV7;
use Throwable;

#[AsMessageHandler]
final readonly class SendPushNotificationHandler
{
    public function __construct(
        private PushDeviceRepository $deviceRepo,
        private PushTicketRepository $ticketRepo,
        private ExpoPushService $expoPush,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(SendPushNotification $message): void
    {
        $startTime = microtime(true);

        $devices = array_values(
            $this->deviceRepo->findActiveByUserIds($message->recipientUserIds)
        );

        if ($devices === []) {
            $this->logger->info('No active devices for notification', [
                'type' => $message->notificationType,
                'recipientCount' => count($message->recipientUserIds),
            ]);

            return;
        }

        $eventId = $message->eventId !== '' ? $message->eventId : new UuidV7()->toRfc4122();

        $pushMessages = [];
        foreach ($devices as $device) {
            $data = array_filter([
                'eventId' => $eventId,
                'deepLink' => $message->deepLink,
                'type' => $message->notificationType,
                ...$message->extraData,
            ]);

            $pushMessages[] = new PushMessage(
                to: new PushToken($device->getExpoPushToken()),
                title: $message->title,
                body: $message->body,
                data: $data !== [] ? $data : null,
                priority: Priority::from($message->priority),
                channelId: $message->channelId ?? $this->resolveChannel($message->notificationType),
            );
        }

        try {
            $result = $this->expoPush->send(new PushMessageCollection(...$pushMessages));
        } catch (Throwable $throwable) {
            $elapsed = (int) ((microtime(true) - $startTime) * 1000);
            $this->logger->error('Expo Push API request failed', [
                'type' => $message->notificationType,
                'error' => $throwable->getMessage(),
                'tokenCount' => count($pushMessages),
                'duration_ms' => $elapsed,
            ]);
            throw $throwable;
        }

        foreach ($result->errors as $error) {
            $this->logger->error('Expo Push request error', [
                'code' => $error->code->value,
                'error' => $error->message,
            ]);
        }

        foreach ($result->tickets as $index => $ticket) {
            $device = $devices[$index];

            if ($ticket instanceof SuccessfulPushTicket) {
                $this->ticketRepo->save(new PushTicket(
                    ticketId: $ticket->receiptId,
                    expoPushToken: $device->getExpoPushToken(),
                    notificationType: $message->notificationType,
                    status: 'ok',
                ));
            } elseif ($ticket instanceof FailedPushTicket) {
                $this->logger->warning('Push ticket error', [
                    'token' => $device->getExpoPushToken(),
                    'error' => $ticket->message,
                    'details' => $ticket->details->error->value,
                ]);

                if ($ticket->details->error === PushTicketErrorCode::DeviceNotRegistered) {
                    $device->deactivate();
                    $this->deviceRepo->save($device);
                }
            }
        }

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Push notifications sent', [
            'type' => $message->notificationType,
            'sent' => count($result->tickets),
            'errors' => $result->errors->count(),
            'duration_ms' => $elapsed,
        ]);
    }

    private function resolveChannel(string $notificationType): string
    {
        return match (true) {
            str_starts_with($notificationType, 'trip_') => 'trips',
            str_starts_with($notificationType, 'payment_') => 'payments',
            str_starts_with($notificationType, 'message_') => 'messages',
            default => 'reminders',
        };
    }
}
