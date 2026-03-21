<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RemoveDriverFromIndexMessage;
use App\Service\OpenSearch\DriverSearchService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveDriverFromIndexHandler
{
    public function __construct(
        private DriverSearchService $driverSearchService,
    ) {
    }

    /**
     * Exception propagates for Messenger retry policy.
     */
    public function __invoke(RemoveDriverFromIndexMessage $message): void
    {
        $this->driverSearchService->delete($message->driverId);
    }
}
