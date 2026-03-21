<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Driver;
use App\Entity\Route;
use App\Service\OpenSearch\DriverSearchService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:opensearch:index-drivers',
    description: 'Hydrate the OpenSearch drivers index from the database',
)]
final class OpenSearchIndexDriversCommand extends Command
{
    public function __construct(
        private readonly DriverSearchService $driverSearchService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Client $opensearchClient,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Delete and recreate the index before indexing')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of documents per bulk request', '100')
            ->addOption('school', null, InputOption::VALUE_REQUIRED, 'Limit indexing to a specific school ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $batchSizeRaw = $input->getOption('batch-size');
        $batchSize = is_numeric($batchSizeRaw) ? (int) $batchSizeRaw : 100;
        $schoolRaw = $input->getOption('school');
        $schoolId = is_numeric($schoolRaw) ? (int) $schoolRaw : null;
        $indexName = $this->driverSearchService->getIndexName();

        $io->title('OpenSearch Driver Indexing');
        $io->listing([
            'Index: ' . $indexName,
            'Force recreate: ' . ($force ? 'Yes' : 'No'),
            'Batch size: ' . $batchSize,
            'School filter: ' . ($schoolId !== null ? (string) $schoolId : 'All'),
        ]);

        // Create or recreate index
        if ($force) {
            $io->write('Recreating index... ');
            $this->driverSearchService->createIndex();
            $io->writeln('done.');
        } elseif (! $this->opensearchClient->indices()->exists([
            'index' => $indexName,
        ])) {
            $io->write('Creating index... ');
            $this->driverSearchService->createIndex();
            $io->writeln('done.');
        }

        // Disable SchoolFilter so all schools' drivers are indexed
        $filters = $this->entityManager->getFilters();

        if ($filters->isEnabled('school_filter')) {
            $filters->disable('school_filter');
        }

        // Build query
        $qb = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Driver::class, 'd')
            ->join('d.user', 'u');

        if ($schoolId !== null) {
            $qb->join(Route::class, 'r', 'WITH', 'r.driver = d AND r.school = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        // Count for progress bar
        $countQb = clone $qb;
        $totalCount = (int) $countQb->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();

        if ($totalCount === 0) {
            $io->success('No drivers found to index.');

            return Command::SUCCESS;
        }

        $io->writeln('Indexing drivers...');
        $io->newLine();

        $progressBar = new ProgressBar($output, $totalCount);
        $progressBar->start();

        $startTime = microtime(true);
        $indexed = 0;
        $errors = 0;
        $batch = [];

        // Memory-safe iteration using toIterable() with periodic clear()
        $query = $qb->getQuery();

        /** @var Driver $driver */
        foreach ($query->toIterable() as $driver) {
            $user = $driver->getUser();

            if ($user === null) {
                $progressBar->advance();

                continue;
            }

            $schoolIds = $this->driverSearchService->getSchoolIdsForDriver($driver);

            $batch[] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => (string) $driver->getId(),
                ],
            ];
            $batch[] = [
                'driver_id' => $driver->getId(),
                'user_id' => $user->getId(),
                'school_id' => $schoolIds,
                'nickname' => $driver->getNickname() ?? '',
                'first_name' => $user->getFirstName() ?? '',
                'last_name' => $user->getLastName() ?? '',
                'identification_number' => $user->getIdentificationNumber() ?? '',
                'full_name' => trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? '')),
                'is_active' => true,
                'updated_at' => new DateTimeImmutable()->format('c'),
            ];

            if (\count($batch) >= $batchSize * 2) {
                $errors += $this->executeBulk($batch);
                $indexed += \count($batch) / 2;
                $batch = [];
                $this->entityManager->clear();
            }

            $progressBar->advance();
        }

        // Flush remaining batch
        if ($batch !== []) {
            $errors += $this->executeBulk($batch);
            $indexed += \count($batch) / 2;
        }

        $progressBar->finish();
        $io->newLine(2);

        $elapsed = round(microtime(true) - $startTime, 1);
        $io->success(sprintf('Indexed %d drivers in %ss (%d errors)', $indexed, $elapsed, $errors));

        return Command::SUCCESS;
    }

    /**
     * Execute a bulk request and return the number of individual document errors.
     *
     * @param array<int, array<string, mixed>> $batch
     */
    private function executeBulk(array $batch): int
    {
        try {
            /** @var array{errors: bool, items: array<int, array{index: array{error?: array<string, mixed>, _id: string}}>} $response */
            $response = $this->opensearchClient->bulk([
                'body' => $batch,
            ]);

            if (! $response['errors']) {
                return 0;
            }

            $errorCount = 0;

            foreach ($response['items'] as $item) {
                if (isset($item['index']['error'])) {
                    ++$errorCount;
                    $this->logger->error('Bulk index error for document', [
                        'id' => $item['index']['_id'],
                        'error' => $item['index']['error'],
                    ]);
                }
            }

            return $errorCount;
        } catch (Exception $exception) {
            $this->logger->error('Bulk request failed', [
                'exception' => $exception->getMessage(),
            ]);

            return (int) (\count($batch) / 2);
        }
    }
}
