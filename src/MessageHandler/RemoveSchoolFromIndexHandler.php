<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RemoveSchoolFromIndexMessage;
use App\Service\OpenSearch\SchoolSearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveSchoolFromIndexHandler
{
    public function __construct(
        private SchoolSearchService $schoolSearchService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Exception propagates for Messenger retry policy.
     */
    public function __invoke(RemoveSchoolFromIndexMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'school_id' => $message->schoolId,
        ]);

        $this->schoolSearchService->delete($message->schoolId);

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Handler completed', [
            'handler' => self::class,
            'school_id' => $message->schoolId,
            'duration_ms' => $elapsed,
        ]);
    }
}
