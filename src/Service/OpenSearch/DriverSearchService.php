<?php

declare(strict_types=1);

namespace App\Service\OpenSearch;

use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\User;
use App\Service\Logging\PerformanceLogger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;

/**
 * Manages the OpenSearch `drivers` index: CRUD operations and search queries.
 *
 * Scalability notes:
 * - The `drivers` index is small (hundreds to low thousands per school). If it grows,
 *   consider using `routing` on `school_id` for shard-level tenancy.
 * - Single-node OpenSearch is sufficient for dev and small prod. For larger deployments,
 *   switch to a 3-node cluster and increase `number_of_replicas` to 1.
 * - The edge_ngram analyzer trades index size for query speed. If the index grows very
 *   large, consider switching to `search_as_you_type` field type.
 */
readonly class DriverSearchService
{
    private const int MAX_QUERY_LENGTH = 100;

    private const int SHORT_QUERY_THRESHOLD = 4;

    public function __construct(
        private Client $opensearchClient,
        private string $indexPrefix,
        private EntityManagerInterface $entityManager,
        private PerformanceLogger $performanceLogger,
    ) {
    }

    public function getIndexName(): string
    {
        return $this->indexPrefix . 'drivers';
    }

    /**
     * Search for drivers by name, nickname, or identification number.
     *
     * Short queries (2-3 chars) use match_phrase_prefix for fast prefix autocomplete.
     * Longer queries (4+) use multi_match with fuzziness for typo tolerance.
     */
    public function search(string $query, int $schoolId, int $page = 1, int $limit = 10): DriverSearchResult
    {
        $query = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH);

        if ($query === '' || mb_strlen($query) < 2) {
            return new DriverSearchResult([], 0, $page, $limit);
        }

        $from = ($page - 1) * $limit;

        $textFields = ['nickname', 'first_name', 'last_name', 'full_name'];
        $sourceFields = ['driver_id', 'nickname', 'first_name', 'last_name', 'identification_number'];

        if (mb_strlen($query) < self::SHORT_QUERY_THRESHOLD) {
            // Short query: match_phrase_prefix is faster and more predictable for autocomplete
            $shouldClauses = array_map(
                static fn (string $field): array => [
                    'match_phrase_prefix' => [
                        $field => [
                            'query' => $query,
                        ],
                    ],
                ],
                $textFields,
            );
        } else {
            // Longer query: multi_match with fuzziness for typo tolerance
            $shouldClauses = [
                [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => $textFields,
                        'type' => 'most_fields',
                        'fuzziness' => 'AUTO',
                        'minimum_should_match' => '75%',
                    ],
                ],
            ];
        }

        // Boost identification_number prefix matches
        $shouldClauses[] = [
            'prefix' => [
                'identification_number' => [
                    'value' => $query,
                    'boost' => 2.0,
                ],
            ],
        ];

        $body = [
            '_source' => $sourceFields,
            'track_total_hits' => true,
            'from' => $from,
            'size' => $limit,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'should' => $shouldClauses,
                                'minimum_should_match' => 1,
                            ],
                        ],
                    ],
                    // school_id as filter (cached by OpenSearch, enforces multi-tenancy)
                    'filter' => [
                        [
                            'term' => [
                                'school_id' => $schoolId,
                            ],
                        ],
                        [
                            'term' => [
                                'is_active' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Let exceptions propagate — the provider catches them to trigger Doctrine fallback
        /** @var array{hits: array{total: array{value: int}, hits: array<int, array{_source: array<string, mixed>, _score: float}>}} $response */
        $response = $this->performanceLogger->measure(
            'opensearch.search',
            fn (): array => $this->opensearchClient->search([
                'index' => $this->getIndexName(),
                'body' => $body,
            ]),
            [
                'school_id' => $schoolId,
                'query_length' => mb_strlen($query),
            ],
        );

        $hits = [];
        foreach ($response['hits']['hits'] as $hit) {
            /** @var array{driver_id: int, nickname: string, first_name: string, last_name: string, identification_number: string} $source */
            $source = $hit['_source'];
            $hits[] = new DriverSearchHit(
                driverId: $source['driver_id'],
                nickname: $source['nickname'],
                firstName: $source['first_name'],
                lastName: $source['last_name'],
                identificationNumber: $source['identification_number'],
                score: (float) $hit['_score'],
            );
        }

        $total = $response['hits']['total']['value'];

        return new DriverSearchResult($hits, $total, $page, $limit);
    }

    /**
     * Index or update a single driver document.
     *
     * school_id is stored as an array derived from the driver's route assignments,
     * since a driver can serve multiple schools. OpenSearch's term filter matches
     * any value in the array natively.
     */
    public function index(Driver $driver): void
    {
        $user = $driver->getUser();

        if (! $user instanceof User) {
            return;
        }

        $schoolIds = $this->getSchoolIdsForDriver($driver);

        $this->opensearchClient->index([
            'index' => $this->getIndexName(),
            'id' => (string) $driver->getId(),
            'body' => [
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
            ],
        ]);
    }

    /**
     * Get all school IDs this driver is assigned to via routes.
     *
     * @return list<int>
     */
    public function getSchoolIdsForDriver(Driver $driver): array
    {
        /** @var list<array{school_id: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT IDENTITY(r.school) AS school_id')
            ->from(Route::class, 'r')
            ->where('r.driver = :driver')
            ->andWhere('r.school IS NOT NULL')
            ->setParameter('driver', $driver)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => $row['school_id'], $rows);
    }

    /**
     * Remove a driver document from the index.
     */
    public function delete(int $driverId): void
    {
        try {
            $this->opensearchClient->delete([
                'index' => $this->getIndexName(),
                'id' => (string) $driverId,
            ]);
        } catch (Missing404Exception) {
            // Document already gone — idempotent delete
        }
    }

    /**
     * Create the index with autocomplete analyzers and field mappings.
     *
     * The asciifolding filter is critical for Spanish names — García must match garcia,
     * Pérez must match perez. Edge_ngram on index with standard on search enables
     * prefix/partial matching while keeping search precise.
     */
    public function createIndex(): void
    {
        $indexName = $this->getIndexName();

        if ($this->opensearchClient->indices()->exists([
            'index' => $indexName,
        ])) {
            $this->opensearchClient->indices()->delete([
                'index' => $indexName,
            ]);
        }

        $this->opensearchClient->indices()->create([
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'filter' => [
                            'autocomplete_filter' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 2,
                                'max_gram' => 15,
                            ],
                        ],
                        'analyzer' => [
                            'autocomplete' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding', 'autocomplete_filter'],
                            ],
                            'autocomplete_search' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'driver_id' => [
                            'type' => 'integer',
                        ],
                        'user_id' => [
                            'type' => 'integer',
                        ],
                        'school_id' => [
                            'type' => 'integer',
                        ],
                        'nickname' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete',
                            'search_analyzer' => 'autocomplete_search',
                        ],
                        'first_name' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete',
                            'search_analyzer' => 'autocomplete_search',
                        ],
                        'last_name' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete',
                            'search_analyzer' => 'autocomplete_search',
                        ],
                        'identification_number' => [
                            'type' => 'keyword',
                        ],
                        'full_name' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete',
                            'search_analyzer' => 'autocomplete_search',
                        ],
                        'is_active' => [
                            'type' => 'boolean',
                        ],
                        'updated_at' => [
                            'type' => 'date',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
