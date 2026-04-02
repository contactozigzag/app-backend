<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\PushDevice;
use App\Message\CheckPushReceipts;
use App\Repository\PushDeviceRepository;
use App\Repository\PushTicketRepository;
use App\Service\Push\ExpoPushService;
use Dru1x\ExpoPush\PushReceipt\FailedPushReceipt;
use Dru1x\ExpoPush\PushReceipt\PushReceiptErrorCode;
use Dru1x\ExpoPush\PushReceipt\PushReceiptIdCollection;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\InvalidPoolItemException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckPushReceiptsHandler
{
    public function __construct(
        private PushDeviceRepository $deviceRepo,
        private PushTicketRepository $ticketRepo,
        private ExpoPushService $expoPush,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws InvalidPoolItemException
     */
    public function __invoke(CheckPushReceipts $message): void
    {
        $pendingTickets = $this->ticketRepo->findPendingOlderThan($message->olderThan);

        if ($pendingTickets === []) {
            return;
        }

        $receiptIds = array_map(
            static fn ($t): string => $t->getTicketId(),
            $pendingTickets
        );

        $result = $this->expoPush->getReceipts(new PushReceiptIdCollection(...$receiptIds));

        $ticketsByReceiptId = [];
        foreach ($pendingTickets as $ticket) {
            $ticketsByReceiptId[$ticket->getTicketId()] = $ticket;
        }

        foreach ($result->receipts as $receipt) {
            $ticket = $ticketsByReceiptId[$receipt->id] ?? null;

            if ($ticket === null) {
                continue;
            }

            if ($receipt instanceof FailedPushReceipt) {
                $ticket->markError($receipt->message, [
                    'error' => $receipt->details->error->value,
                    'expoPushToken' => $receipt->details->expoPushToken?->value,
                ]);

                if ($receipt->details->error === PushReceiptErrorCode::DeviceNotRegistered) {
                    $device = $this->deviceRepo->findByToken($ticket->getExpoPushToken());

                    if ($device instanceof PushDevice) {
                        $device->deactivate();
                        $this->deviceRepo->save($device);
                    }
                }
            } else {
                $ticket->markChecked();
            }

            $this->ticketRepo->save($ticket);
        }

        $this->logger->info('Push receipts checked', [
            'pending' => count($pendingTickets),
            'receipts' => count($result->receipts),
            'errors' => $result->errors->count(),
        ]);
    }
}
