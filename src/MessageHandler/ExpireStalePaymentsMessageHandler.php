<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\PaymentStatus;
use App\Message\ExpireStalePaymentsMessage;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ExpireStalePaymentsMessageHandler
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExpireStalePaymentsMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'batch_size' => $message->getBatchSize(),
        ]);

        $expiredPayments = $this->paymentRepository->findExpiredPendingPayments($message->getBatchSize());
        $count = count($expiredPayments);

        if ($count === 0) {
            $this->logger->info('Handler completed — no expired payments found', [
                'handler' => self::class,
            ]);

            return;
        }

        $cancelled = 0;

        foreach ($expiredPayments as $payment) {
            $payment->setStatus(PaymentStatus::CANCELLED);
            $cancelled++;

            if ($cancelled % 50 === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Handler completed', [
            'handler' => self::class,
            'cancelled' => $cancelled,
            'duration_ms' => $elapsed,
        ]);
    }
}
