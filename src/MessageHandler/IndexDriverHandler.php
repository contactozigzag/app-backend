<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\IndexDriverMessage;
use App\Repository\DriverRepository;
use App\Service\OpenSearch\DriverSearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IndexDriverHandler
{
    public function __construct(
        private DriverSearchService $driverSearchService,
        private DriverRepository $driverRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(IndexDriverMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'driver_id' => $message->driverId,
        ]);

        $driver = $this->driverRepository->find($message->driverId);

        if ($driver === null) {
            $this->logger->warning('Driver not found for indexing, may have been deleted', [
                'driver_id' => $message->driverId,
            ]);

            return;
        }

        // Exception propagates for Messenger retry policy
        $this->driverSearchService->index($driver);

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Handler completed', [
            'handler' => self::class,
            'driver_id' => $message->driverId,
            'duration_ms' => $elapsed,
        ]);
    }
}
