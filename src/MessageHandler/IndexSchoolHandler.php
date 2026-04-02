<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\IndexSchoolMessage;
use App\Repository\SchoolRepository;
use App\Service\OpenSearch\SchoolSearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IndexSchoolHandler
{
    public function __construct(
        private SchoolSearchService $schoolSearchService,
        private SchoolRepository $schoolRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(IndexSchoolMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'school_id' => $message->schoolId,
        ]);

        $school = $this->schoolRepository->find($message->schoolId);

        if ($school === null) {
            $this->logger->warning('School not found for indexing, may have been deleted', [
                'school_id' => $message->schoolId,
            ]);

            return;
        }

        // Exception propagates for Messenger retry policy
        $this->schoolSearchService->index($school);

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Handler completed', [
            'handler' => self::class,
            'school_id' => $message->schoolId,
            'duration_ms' => $elapsed,
        ]);
    }
}
