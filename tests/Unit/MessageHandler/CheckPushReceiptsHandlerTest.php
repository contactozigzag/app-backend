<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\PushDevice;
use App\Entity\PushTicket;
use App\Entity\User;
use App\Message\CheckPushReceipts;
use App\MessageHandler\CheckPushReceiptsHandler;
use App\Repository\PushDeviceRepository;
use App\Repository\PushTicketRepository;
use App\Service\Push\ExpoPushService;
use DateTimeImmutable;
use Dru1x\ExpoPush\PushError\PushErrorCollection;
use Dru1x\ExpoPush\PushReceipt\FailedPushReceipt;
use Dru1x\ExpoPush\PushReceipt\PushReceiptCollection;
use Dru1x\ExpoPush\PushReceipt\PushReceiptDetails;
use Dru1x\ExpoPush\PushReceipt\PushReceiptErrorCode;
use Dru1x\ExpoPush\PushReceipt\SuccessfulPushReceipt;
use Dru1x\ExpoPush\Result\GetReceiptsResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CheckPushReceiptsHandlerTest extends TestCase
{
    private const string TOKEN = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';

    private function makeTicket(string $ticketId, string $token = self::TOKEN): PushTicket
    {
        return new PushTicket($ticketId, $token, 'trip_started', 'pending');
    }

    private function makeDevice(string $token = self::TOKEN): PushDevice
    {
        $user = $this->createStub(User::class);

        return new PushDevice($user, $token, 'android');
    }

    private function makeReceiptsResult(PushReceiptCollection $receipts): GetReceiptsResult
    {
        return new GetReceiptsResult($receipts, new PushErrorCollection());
    }

    // ── No pending tickets ────────────────────────────────────────────────────

    public function testNoPendingTicketsIsNoOp(): void
    {
        $ticketRepo = $this->createStub(PushTicketRepository::class);
        $ticketRepo->method('findPendingOlderThan')->willReturn([]);

        $expoPush = $this->createMock(ExpoPushService::class);
        $expoPush->expects($this->never())->method('getReceipts');

        $handler = new CheckPushReceiptsHandler(
            $this->createStub(PushDeviceRepository::class),
            $ticketRepo,
            $expoPush,
            new NullLogger(),
        );
        $handler(new CheckPushReceipts(new DateTimeImmutable('-15 minutes')));
    }

    // ── Successful receipt ────────────────────────────────────────────────────

    public function testSuccessfulReceiptMarksTicketChecked(): void
    {
        $ticket = $this->makeTicket('receipt-abc');

        $ticketRepo = $this->createMock(PushTicketRepository::class);
        $ticketRepo->method('findPendingOlderThan')->willReturn([$ticket]);
        $ticketRepo->expects($this->once())->method('save')->with($ticket);

        $receipt = new SuccessfulPushReceipt('receipt-abc');
        $receipts = new PushReceiptCollection($receipt);
        $result = $this->makeReceiptsResult($receipts);

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('getReceipts')->willReturn($result);

        $handler = new CheckPushReceiptsHandler(
            $this->createStub(PushDeviceRepository::class),
            $ticketRepo,
            $expoPush,
            new NullLogger(),
        );
        $handler(new CheckPushReceipts(new DateTimeImmutable('-15 minutes')));

        $this->assertSame('ok', $ticket->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $ticket->getCheckedAt());
    }

    // ── Failed receipt — DeviceNotRegistered ──────────────────────────────────

    public function testDeviceNotRegisteredReceiptDeactivatesDevice(): void
    {
        $ticket = $this->makeTicket('receipt-abc');
        $device = $this->makeDevice();

        $ticketRepo = $this->createMock(PushTicketRepository::class);
        $ticketRepo->method('findPendingOlderThan')->willReturn([$ticket]);
        $ticketRepo->expects($this->once())->method('save')->with($ticket);

        $deviceRepo = $this->createMock(PushDeviceRepository::class);
        $deviceRepo->method('findByToken')->with(self::TOKEN)->willReturn($device);
        $deviceRepo->expects($this->once())->method('save')->with($device);

        $details = new PushReceiptDetails(PushReceiptErrorCode::DeviceNotRegistered, null);
        $receipt = new FailedPushReceipt('receipt-abc', 'Device not registered', $details);
        $receipts = new PushReceiptCollection($receipt);
        $result = $this->makeReceiptsResult($receipts);

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('getReceipts')->willReturn($result);

        $handler = new CheckPushReceiptsHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new CheckPushReceipts(new DateTimeImmutable('-15 minutes')));

        $this->assertSame('error', $ticket->getStatus());
        $this->assertFalse($device->isActive());
    }

    // ── Failed receipt — other error code ────────────────────────────────────

    public function testOtherReceiptErrorMarksTicketErrorWithoutDeactivating(): void
    {
        $token2 = 'ExponentPushToken[bbbbbbbbbbbbbbbbbbbbbb]';
        $ticket = $this->makeTicket('receipt-xyz', $token2);

        $ticketRepo = $this->createMock(PushTicketRepository::class);
        $ticketRepo->method('findPendingOlderThan')->willReturn([$ticket]);
        $ticketRepo->expects($this->once())->method('save');

        $deviceRepo = $this->createMock(PushDeviceRepository::class);
        $deviceRepo->expects($this->never())->method('save');

        $details = new PushReceiptDetails(PushReceiptErrorCode::MessageRateExceeded, null);
        $receipt = new FailedPushReceipt('receipt-xyz', 'Rate limit exceeded', $details);
        $receipts = new PushReceiptCollection($receipt);
        $result = $this->makeReceiptsResult($receipts);

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('getReceipts')->willReturn($result);

        $handler = new CheckPushReceiptsHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new CheckPushReceipts(new DateTimeImmutable('-15 minutes')));

        $this->assertSame('error', $ticket->getStatus());
        $this->assertNotNull($ticket->getErrorDetails());
    }

    // ── Receipt for unknown ticket ID ─────────────────────────────────────────

    public function testReceiptForUnknownTicketIdIsSkipped(): void
    {
        $ticket = $this->makeTicket('known-id');

        $ticketRepo = $this->createMock(PushTicketRepository::class);
        $ticketRepo->method('findPendingOlderThan')->willReturn([$ticket]);
        $ticketRepo->expects($this->never())->method('save');

        // Receipt ID doesn't match any stored ticket
        $receipt = new SuccessfulPushReceipt('unknown-id');
        $receipts = new PushReceiptCollection($receipt);
        $result = $this->makeReceiptsResult($receipts);

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('getReceipts')->willReturn($result);

        $handler = new CheckPushReceiptsHandler(
            $this->createStub(PushDeviceRepository::class),
            $ticketRepo,
            $expoPush,
            new NullLogger(),
        );
        $handler(new CheckPushReceipts(new DateTimeImmutable('-15 minutes')));

        // Original ticket untouched
        $this->assertSame('pending', $ticket->getStatus());
    }
}
