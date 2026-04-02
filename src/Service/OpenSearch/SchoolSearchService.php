<?php

declare(strict_types=1);

namespace App\Service\OpenSearch;

use App\Entity\School;
use App\Service\Tracing\TracingService;
use DateTimeImmutable;
use OpenSearch\Client;
use Throwable;

/**
 * Manages the OpenSearch `schools` index: CRUD operations and search queries.
 *
 * Index design:
 * - `name` field uses a custom analyzer with asciifolding (García → garcia),
 *   Spanish stop words (removes "de", "del", "la", "las", "los", etc.) and
 *   edge_ngram (min 2) for instant prefix matching at index time.
 *   At search time a matching analyzer without ngram keeps precision high.
 * - `name.keyword` sub-field with a lowercase+asciifolding normalizer supports
 *   exact-match boosts via `term` query.
 *
 * Search scoring (four-layer bool/should):
 *   1. Exact keyword   (boost 10) — term on name.keyword
 *   2. Phrase match    (boost  5) — match_phrase on name
 *   3. Edge-ngram      (boost  2) — standard match on name (ngram index)
 *   4. Fuzzy tolerance (boost  1) — match with fuzziness AUTO
 *
 * Scalability notes:
 * - Schools are a small dataset (hundreds globally). Single shard is sufficient.
 * - If multi-tenancy ever requires school isolation within a sub-index, add a
 *   routing key on `school_id` and set `number_of_shards` accordingly.
 */
readonly class SchoolSearchService
{
    private const int MAX_QUERY_LENGTH = 100;

    public function __construct(
        private Client $opensearchClient,
        private string $indexPrefix,
        private TracingService $tracingService,
    ) {
    }

    public function getIndexName(): string
    {
        return $this->indexPrefix . 'schools';
    }

    /**
     * Search schools by name using a four-layer relevance scoring query.
     *
     * Returns an empty result for queries shorter than 2 characters so the
     * caller (autocomplete UX) never sees an error while the user is still
     * typing the first character.
     *
     * @throws Throwable
     */
    public function search(string $query, int $page = 1, int $limit = 10): SchoolSearchResult
    {
        $query = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH);

        if ($query === '' || mb_strlen($query) < 2) {
            return new SchoolSearchResult([], 0, $page, $limit);
        }

        $from = ($page - 1) * $limit;

        // Four-layer bool/should scoring strategy:
        // Layer 1 — exact keyword match (highest signal, boost 10)
        // Layer 2 — phrase match preserves word order (boost 5)
        // Layer 3 — edge-ngram prefix via standard match (boost 2)
        // Layer 4 — fuzzy/typo tolerance for resilience (boost 1)
        $body = [
            '_source' => ['school_id', 'name', 'city', 'street_address'],
            'track_total_hits' => true,
            'from' => $from,
            'size' => $limit,
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'term' => [
                                'name.keyword' => [
                                    'value' => $query,
                                    'boost' => 10.0,
                                ],
                            ],
                        ],
                        [
                            'match_phrase' => [
                                'name' => [
                                    'query' => $query,
                                    'boost' => 5.0,
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'name' => [
                                    'query' => $query,
                                    'boost' => 2.0,
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'name' => [
                                    'query' => $query,
                                    'fuzziness' => 'AUTO',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                    'filter' => [
                        [
                            'term' => [
                                'is_active' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        /** @var array{hits: array{total: array{value: int}, hits: array<int, array{_source: array<string, mixed>, _score: float}>}} $response */
        $response = $this->tracingService->trace(
            'SchoolSearch.search',
            fn (): array => $this->opensearchClient->search([
                'index' => $this->getIndexName(),
                'body' => $body,
            ]),
            [
                'search.query_length' => mb_strlen($query),
            ],
        );

        $hits = [];

        foreach ($response['hits']['hits'] as $hit) {
            /** @var array{school_id: int, name: string, city: string, street_address: string} $source */
            $source = $hit['_source'];
            $hits[] = new SchoolSearchHit(
                schoolId: $source['school_id'],
                name: $source['name'],
                city: $source['city'],
                address: $source['street_address'],
                score: (float) $hit['_score'],
            );
        }

        return new SchoolSearchResult($hits, $response['hits']['total']['value'], $page, $limit);
    }

    /**
     * Index or update a single school document.
     */
    public function index(School $school): void
    {
        $this->opensearchClient->index([
            'index' => $this->getIndexName(),
            'id' => (string) $school->getId(),
            'body' => $this->buildDocument($school),
        ]);
    }

    /**
     * Remove a school document from the index.
     *
     * Uses `client.ignore: [404]` so a missing document is treated as a
     * successful no-op without throwing the deprecated Missing404Exception.
     */
    public function delete(int $schoolId): void
    {
        $this->opensearchClient->delete([
            'index' => $this->getIndexName(),
            'id' => (string) $schoolId,
            'client' => [
                'ignore' => [404],
            ],
        ]);
    }

    /**
     * Create the index with Spanish-aware autocomplete analyzers and mappings.
     *
     * Analyzer breakdown:
     * - `school_name_index`: standard tokenizer → lowercase → asciifolding →
     *   spanish_stop → edge_ngram. Indexed tokens are short prefixes.
     * - `school_name_search`: same pipeline without edge_ngram so the search
     *   term is not expanded — keeps precision high at query time.
     * - `lowercase_ascii` normalizer: applied to `name.keyword` so term queries
     *   are case- and accent-insensitive ("Güemes" == "guemes").
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
                            'spanish_stop' => [
                                'type' => 'stop',
                                'stopwords' => '_spanish_',
                            ],
                            'edge_ngram_filter' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 2,
                                'max_gram' => 20,
                            ],
                        ],
                        'normalizer' => [
                            'lowercase_ascii' => [
                                'type' => 'custom',
                                'filter' => ['lowercase', 'asciifolding'],
                            ],
                        ],
                        'analyzer' => [
                            'school_name_index' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => [
                                    'lowercase',
                                    'asciifolding',
                                    'spanish_stop',
                                    'edge_ngram_filter',
                                ],
                            ],
                            'school_name_search' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding', 'spanish_stop'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'school_id' => [
                            'type' => 'integer',
                        ],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'school_name_index',
                            'search_analyzer' => 'school_name_search',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'normalizer' => 'lowercase_ascii',
                                ],
                            ],
                        ],
                        'city' => [
                            'type' => 'keyword',
                        ],
                        'street_address' => [
                            'type' => 'keyword',
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

    /**
     * @return array<string, mixed>
     */
    public function buildDocument(School $school): array
    {
        return [
            'school_id' => $school->getId(),
            'name' => $school->getName() ?? '',
            'city' => $school->getAddress()?->getCity() ?? '',
            'street_address' => $school->getAddress()?->getStreetAddress() ?? '',
            'is_active' => true,
            'updated_at' => new DateTimeImmutable()->format('c'),
        ];
    }
}
