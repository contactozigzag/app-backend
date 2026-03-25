<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Message\ExpireStalePaymentsMessage;
use App\MessageHandler\ExpireStalePaymentsMessageHandler;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ExpireStalePaymentsMessageHandlerTest extends TestCase
{
    public function testNoExpiredPaymentsIsNoOp(): void
    {
        $repo = $this->createStub(PaymentRepository::class);
        $repo->method('findExpiredPendingPayments')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $handler = new ExpireStalePaymentsMessageHandler($repo, $em, new NullLogger());
        $handler(new ExpireStalePaymentsMessage());
    }

    public function testExpiredPaymentsAreCancelled(): void
    {
        $payment1 = $this->createMock(Payment::class);
        $payment1->expects($this->once())->method('setStatus')->with(PaymentStatus::CANCELLED);

        $payment2 = $this->createMock(Payment::class);
        $payment2->expects($this->once())->method('setStatus')->with(PaymentStatus::CANCELLED);

        $repo = $this->createStub(PaymentRepository::class);
        $repo->method('findExpiredPendingPayments')->willReturn([$payment1, $payment2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $handler = new ExpireStalePaymentsMessageHandler($repo, $em, new NullLogger());
        $handler(new ExpireStalePaymentsMessage());
    }

    public function testBatchSizeIsPassedToRepository(): void
    {
        $repo = $this->createMock(PaymentRepository::class);
        $repo->expects($this->once())
            ->method('findExpiredPendingPayments')
            ->with(200)
            ->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new ExpireStalePaymentsMessageHandler($repo, $em, new NullLogger());
        $handler(new ExpireStalePaymentsMessage(batchSize: 200));
    }
}
