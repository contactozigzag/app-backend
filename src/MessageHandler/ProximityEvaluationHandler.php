<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DriverLocationUpdatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Placeholder handler — proximity alert logic will be implemented in Step 4.
 */
#[AsMessageHandler]
class ProximityEvaluationHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(DriverLocationUpdatedMessage $message): void
    {
        $this->logger->debug('ProximityEvaluationHandler: received (noop)', [
            'driverId' => $message->driverId,
            'correlationId' => $message->correlationId,
        ]);
    }
}
