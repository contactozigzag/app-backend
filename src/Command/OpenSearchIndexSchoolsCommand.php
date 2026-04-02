<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\School;
use App\Service\OpenSearch\SchoolSearchService;
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
    name: 'app:opensearch:index-schools',
    description: 'Hydrate the OpenSearch schools index from the database',
)]
final class OpenSearchIndexSchoolsCommand extends Command
{
    public function __construct(
        private readonly SchoolSearchService $schoolSearchService,
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
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of documents per bulk request', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $batchSizeRaw = $input->getOption('batch-size');
        $batchSize = is_numeric($batchSizeRaw) ? (int) $batchSizeRaw : 100;
        $indexName = $this->schoolSearchService->getIndexName();

        $io->title('OpenSearch School Indexing');
        $io->listing([
            'Index: ' . $indexName,
            'Force recreate: ' . ($force ? 'Yes' : 'No'),
            'Batch size: ' . $batchSize,
        ]);

        // Create or recreate index
        if ($force) {
            $io->write('Recreating index... ');
            $this->schoolSearchService->createIndex();
            $io->writeln('done.');
        } elseif (! $this->opensearchClient->indices()->exists([
            'index' => $indexName,
        ])) {
            $io->write('Creating index... ');
            $this->schoolSearchService->createIndex();
            $io->writeln('done.');
        }

        // Disable SchoolFilter so the command can read all schools regardless of context
        $filters = $this->entityManager->getFilters();

        if ($filters->isEnabled('school_filter')) {
            $filters->disable('school_filter');
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(School::class, 's')
            ->leftJoin('s.address', 'a');

        $countQb = clone $qb;
        $totalCount = (int) $countQb->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        if ($totalCount === 0) {
            $io->success('No schools found to index.');

            return Command::SUCCESS;
        }

        $io->writeln('Indexing schools...');
        $io->newLine();

        $progressBar = new ProgressBar($output, $totalCount);
        $progressBar->start();

        $startTime = microtime(true);
        $indexed = 0;
        $errors = 0;
        $batch = [];

        /** @var School $school */
        foreach ($qb->getQuery()->toIterable() as $school) {
            $batch[] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => (string) $school->getId(),
                ],
            ];
            $batch[] = $this->schoolSearchService->buildDocument($school);

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
        $io->success(sprintf('Indexed %d schools in %ss (%d errors)', $indexed, $elapsed, $errors));

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
