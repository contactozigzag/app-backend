<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ExpireStaleActiveRoutesMessage;
use App\Repository\ActiveRouteRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ExpireStaleActiveRoutesHandler
{
    public function __construct(
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExpireStaleActiveRoutesMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'batch_size' => $message->getBatchSize(),
        ]);

        $stale = $this->activeRouteRepository->findStaleNonTerminal($message->getBatchSize());
        $count = count($stale);

        if ($count === 0) {
            $this->logger->info('Handler completed — no stale active routes found', [
                'handler' => self::class,
            ]);

            return;
        }

        $now = new DateTimeImmutable();
        $cancelled = 0;

        foreach ($stale as $route) {
            $route->setStatus('cancelled');

            // Stamp completedAt so downstream archiving/reporting can distinguish
            // scheduler-cancelled zombies from manually cancelled trips via the
            // gap between startedAt (null or trip date) and completedAt (now).
            if ($route->getCompletedAt() === null) {
                $route->setCompletedAt($now);
            }

            ++$cancelled;

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
