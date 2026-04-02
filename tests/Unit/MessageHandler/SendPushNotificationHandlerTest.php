<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\PushDevice;
use App\Entity\PushTicket;
use App\Entity\User;
use App\Message\SendPushNotification;
use App\MessageHandler\SendPushNotificationHandler;
use App\Repository\PushDeviceRepository;
use App\Repository\PushTicketRepository;
use App\Service\Push\ExpoPushService;
use Dru1x\ExpoPush\PushError\PushError;
use Dru1x\ExpoPush\PushError\PushErrorCode;
use Dru1x\ExpoPush\PushError\PushErrorCollection;
use Dru1x\ExpoPush\PushTicket\FailedPushTicket;
use Dru1x\ExpoPush\PushTicket\PushTicketCollection;
use Dru1x\ExpoPush\PushTicket\PushTicketDetails;
use Dru1x\ExpoPush\PushTicket\PushTicketErrorCode;
use Dru1x\ExpoPush\PushTicket\SuccessfulPushTicket;
use Dru1x\ExpoPush\PushToken\PushToken;
use Dru1x\ExpoPush\Result\SendNotificationsResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class SendPushNotificationHandlerTest extends TestCase
{
    private const TOKEN_A = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';
    private const TOKEN_B = 'ExponentPushToken[bbbbbbbbbbbbbbbbbbbbbb]';

    private function makeDevice(string $token): PushDevice
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);

        return new PushDevice(
            user: $user,
            expoPushToken: $token,
            platform: 'android',
        );
    }

    private function makeResult(PushTicketCollection $tickets, PushErrorCollection $errors): SendNotificationsResult
    {
        return new SendNotificationsResult($tickets, $errors);
    }

    private function emptyErrors(): PushErrorCollection
    {
        return new PushErrorCollection();
    }

    // ── No active devices ─────────────────────────────────────────────────────

    public function testNoActiveDevicesIsNoOp(): void
    {
        $deviceRepo = $this->createStub(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([]);

        $ticketRepo = $this->createMock(PushTicketRepository::class);
        $ticketRepo->expects($this->never())->method('save');

        $expoPush = $this->createMock(ExpoPushService::class);
        $expoPush->expects($this->never())->method('send');

        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new SendPushNotification(
            recipientUserIds: [1],
            title: 'Test',
            body: 'Hello',
            notificationType: 'trip_started',
        ));
    }

    // ── Successful push ───────────────────────────────────────────────────────

    public function testSuccessfulTicketIsSaved(): void
    {
        $device = $this->makeDevice(self::TOKEN_A);

        $deviceRepo = $this->createStub(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([$device]);

        $savedTicket = null;
        $ticketRepo = $this->createMock(PushTicketRepository::class);
        $ticketRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PushTicket $t) use (&$savedTicket) {
                $savedTicket = $t;

                return true;
            }));

        $ticket = new SuccessfulPushTicket(new PushToken(self::TOKEN_A), 'receipt-id-123');
        $tickets = new PushTicketCollection($ticket);
        $result = $this->makeResult($tickets, $this->emptyErrors());

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('send')->willReturn($result);

        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new SendPushNotification(
            recipientUserIds: [1],
            title: 'Trip started',
            body: 'Bus is on its way',
            notificationType: 'trip_started',
        ));

        $this->assertInstanceOf(PushTicket::class, $savedTicket);
        $this->assertSame('receipt-id-123', $savedTicket->getTicketId());
        $this->assertSame('ok', $savedTicket->getStatus());
        $this->assertSame('trip_started', $savedTicket->getNotificationType());
    }

    // ── DeviceNotRegistered at ticket level ───────────────────────────────────

    public function testDeviceNotRegisteredTicketDeactivatesDevice(): void
    {
        $device = $this->makeDevice(self::TOKEN_A);

        $deviceRepo = $this->createMock(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([$device]);
        $deviceRepo->expects($this->once())->method('save')->with($device);

        $ticketRepo = $this->createStub(PushTicketRepository::class);

        $details = new PushTicketDetails(PushTicketErrorCode::DeviceNotRegistered, null);
        $failedTicket = new FailedPushTicket(new PushToken(self::TOKEN_A), 'Device not registered', $details);
        $tickets = new PushTicketCollection($failedTicket);
        $result = $this->makeResult($tickets, $this->emptyErrors());

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('send')->willReturn($result);

        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new SendPushNotification(
            recipientUserIds: [1],
            title: 'Test',
            body: 'Test',
            notificationType: 'trip_started',
        ));

        $this->assertFalse($device->isActive());
    }

    public function testOtherTicketErrorDoesNotDeactivateDevice(): void
    {
        $device = $this->makeDevice(self::TOKEN_A);

        $deviceRepo = $this->createMock(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([$device]);
        $deviceRepo->expects($this->never())->method('save');

        $ticketRepo = $this->createStub(PushTicketRepository::class);

        $details = new PushTicketDetails(PushTicketErrorCode::Unknown, null);
        $failedTicket = new FailedPushTicket(new PushToken(self::TOKEN_A), 'Unknown error', $details);
        $tickets = new PushTicketCollection($failedTicket);
        $result = $this->makeResult($tickets, $this->emptyErrors());

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('send')->willReturn($result);

        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new SendPushNotification(
            recipientUserIds: [1],
            title: 'Test',
            body: 'Test',
            notificationType: 'trip_started',
        ));

        $this->assertTrue($device->isActive());
    }

    // ── Multiple devices ──────────────────────────────────────────────────────

    public function testMultipleDevicesTicketsIndexedCorrectly(): void
    {
        $deviceA = $this->makeDevice(self::TOKEN_A);
        $deviceB = $this->makeDevice(self::TOKEN_B);

        $deviceRepo = $this->createStub(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([$deviceA, $deviceB]);

        $savedTickets = [];
        $ticketRepo = $this->createMock(PushTicketRepository::class);
        $ticketRepo->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (PushTicket $t) use (&$savedTickets): void {
                $savedTickets[] = $t;
            });

        $ticketA = new SuccessfulPushTicket(new PushToken(self::TOKEN_A), 'receipt-a');
        $ticketB = new SuccessfulPushTicket(new PushToken(self::TOKEN_B), 'receipt-b');
        $tickets = new PushTicketCollection($ticketA, $ticketB);
        $result = $this->makeResult($tickets, $this->emptyErrors());

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('send')->willReturn($result);

        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new SendPushNotification(
            recipientUserIds: [1, 2],
            title: 'Test',
            body: 'Test',
            notificationType: 'payment_confirmed',
        ));

        $this->assertCount(2, $savedTickets);
        $this->assertSame('receipt-a', $savedTickets[0]->getTicketId());
        $this->assertSame('receipt-b', $savedTickets[1]->getTicketId());
    }

    // ── Expo API failure ──────────────────────────────────────────────────────

    public function testExpoApiFailureRethrowsForMessengerRetry(): void
    {
        $device = $this->makeDevice(self::TOKEN_A);

        $deviceRepo = $this->createStub(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([$device]);

        $ticketRepo = $this->createStub(PushTicketRepository::class);

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('send')->willThrowException(new RuntimeException('Expo API unavailable'));

        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expo API unavailable');

        $handler(new SendPushNotification(
            recipientUserIds: [1],
            title: 'Test',
            body: 'Test',
            notificationType: 'trip_started',
        ));
    }

    // ── Request-level errors ──────────────────────────────────────────────────

    public function testRequestLevelErrorsAreLogged(): void
    {
        $device = $this->makeDevice(self::TOKEN_A);

        $deviceRepo = $this->createStub(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([$device]);

        $ticketRepo = $this->createStub(PushTicketRepository::class);

        $error = new PushError(PushErrorCode::Unknown, 'Batch error');
        $errors = new PushErrorCollection($error);
        $tickets = new PushTicketCollection();
        $result = $this->makeResult($tickets, $errors);

        $expoPush = $this->createStub(ExpoPushService::class);
        $expoPush->method('send')->willReturn($result);

        // NullLogger discards everything — just assert no exception is thrown
        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new SendPushNotification(
            recipientUserIds: [1],
            title: 'Test',
            body: 'Test',
            notificationType: 'trip_started',
        ));

        // Errors are logged; no tickets to save, no devices to deactivate
        $this->assertCount(0, $result->tickets);
        $this->assertCount(1, $result->errors);
    }

    // ── Channel resolution ────────────────────────────────────────────────────

    public function testChannelIsResolvedFromNotificationType(): void
    {
        $device = $this->makeDevice(self::TOKEN_A);

        $deviceRepo = $this->createStub(PushDeviceRepository::class);
        $deviceRepo->method('findActiveByUserIds')->willReturn([$device]);

        $ticketRepo = $this->createStub(PushTicketRepository::class);

        $capturedMessages = [];
        $expoPush = $this->createMock(ExpoPushService::class);
        $expoPush->expects($this->once())
            ->method('send')
            ->willReturnCallback(function ($collection) use (&$capturedMessages): SendNotificationsResult {
                foreach ($collection as $msg) {
                    $capturedMessages[] = $msg;
                }

                return $this->makeResult(new PushTicketCollection(), $this->emptyErrors());
            });

        $handler = new SendPushNotificationHandler($deviceRepo, $ticketRepo, $expoPush, new NullLogger());
        $handler(new SendPushNotification(
            recipientUserIds: [1],
            title: 'Payment done',
            body: 'Your payment was confirmed',
            notificationType: 'payment_confirmed',
        ));

        $this->assertCount(1, $capturedMessages);
        $this->assertSame('payments', $capturedMessages[0]->channelId);
    }
}
