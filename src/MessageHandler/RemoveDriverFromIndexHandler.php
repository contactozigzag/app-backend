<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RemoveDriverFromIndexMessage;
use App\Service\OpenSearch\DriverSearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveDriverFromIndexHandler
{
    public function __construct(
        private DriverSearchService $driverSearchService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Exception propagates for Messenger retry policy.
     */
    public function __invoke(RemoveDriverFromIndexMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'driver_id' => $message->driverId,
        ]);

        $this->driverSearchService->delete($message->driverId);

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Handler completed', [
            'handler' => self::class,
            'driver_id' => $message->driverId,
            'duration_ms' => $elapsed,
        ]);
    }
}
