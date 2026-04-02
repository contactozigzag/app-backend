<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OpenSearch;

use App\Entity\Address;
use App\Entity\School;
use App\Service\OpenSearch\SchoolSearchService;
use App\Service\Tracing\TracingService;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;

final class SchoolSearchServiceTest extends TestCase
{
    private const string INDEX_PREFIX = 'test_';

    // ── short-circuit guards ─────────────────────────────────────────────────

    public function testEmptyQueryReturnsEmptyWithoutCallingOpenSearch(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('search');

        $service = $this->buildService($client);
        $result = $service->search('');

        $this->assertSame([], $result->results);
        $this->assertSame(0, $result->total);
    }

    public function testSingleCharQueryReturnsEmptyWithoutCallingOpenSearch(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('search');

        $result = $this->buildService($client)->search('a');

        $this->assertSame([], $result->results);
    }

    public function testQueryIsTrimmedAndTruncated(): void
    {
        $longQuery = str_repeat('x', 150);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                // The query sent to OpenSearch must be ≤ 100 chars
                $shouldClauses = $params['body']['query']['bool']['should'];
                $termValue = $shouldClauses[0]['term']['name.keyword']['value'];
                $this->assertSame(100, mb_strlen($termValue));

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search($longQuery);
    }

    // ── four-layer query structure ───────────────────────────────────────────

    public function testSearchBuildsExactKeywordLayer(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $should = $params['body']['query']['bool']['should'];
                $layer1 = $should[0];

                $this->assertArrayHasKey('term', $layer1);
                $this->assertSame('Colegio', $layer1['term']['name.keyword']['value']);
                $this->assertEqualsWithDelta(10.0, $layer1['term']['name.keyword']['boost'], PHP_FLOAT_EPSILON);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('Colegio');
    }

    public function testSearchBuildsPhraseMatchLayer(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $should = $params['body']['query']['bool']['should'];
                $layer2 = $should[1];

                $this->assertArrayHasKey('match_phrase', $layer2);
                $this->assertSame('Colegio', $layer2['match_phrase']['name']['query']);
                $this->assertEqualsWithDelta(5.0, $layer2['match_phrase']['name']['boost'], PHP_FLOAT_EPSILON);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('Colegio');
    }

    public function testSearchBuildsEdgeNgramLayer(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $should = $params['body']['query']['bool']['should'];
                $layer3 = $should[2];

                $this->assertArrayHasKey('match', $layer3);
                $this->assertSame('Colegio', $layer3['match']['name']['query']);
                $this->assertEqualsWithDelta(2.0, $layer3['match']['name']['boost'], PHP_FLOAT_EPSILON);
                $this->assertArrayNotHasKey('fuzziness', $layer3['match']['name']);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('Colegio');
    }

    public function testSearchBuildsFuzzyLayer(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $should = $params['body']['query']['bool']['should'];
                $layer4 = $should[3];

                $this->assertArrayHasKey('match', $layer4);
                $this->assertSame('Colegio', $layer4['match']['name']['query']);
                $this->assertSame('AUTO', $layer4['match']['name']['fuzziness']);
                $this->assertEqualsWithDelta(1.0, $layer4['match']['name']['boost'], PHP_FLOAT_EPSILON);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('Colegio');
    }

    public function testSearchAppliesIsActiveFilter(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $filter = $params['body']['query']['bool']['filter'];
                $this->assertSame([
                    'term' => [
                        'is_active' => true,
                    ],
                ], $filter[0]);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('Escuela');
    }

    public function testSearchIncludesSourceFiltering(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $this->assertArrayHasKey('_source', $params['body']);
                $this->assertContains('school_id', $params['body']['_source']);
                $this->assertContains('name', $params['body']['_source']);
                $this->assertContains('city', $params['body']['_source']);
                $this->assertContains('street_address', $params['body']['_source']);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('test');
    }

    public function testSearchEnablesTrackTotalHits(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $this->assertTrue($params['body']['track_total_hits']);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('test');
    }

    // ── response mapping ─────────────────────────────────────────────────────

    public function testSearchResponseMappedToHits(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('search')->willReturn([
            'hits' => [
                'total' => [
                    'value' => 1,
                ],
                'hits' => [
                    [
                        '_source' => [
                            'school_id' => 5,
                            'name' => 'Escuela San Martín',
                            'city' => 'Buenos Aires',
                            'street_address' => 'Av. Corrientes 1234',
                        ],
                        '_score' => 9.2,
                    ],
                ],
            ],
        ]);

        $result = $this->buildService($client)->search('San Martin');

        $this->assertCount(1, $result->results);
        $hit = $result->results[0];
        $this->assertSame(5, $hit->schoolId);
        $this->assertSame('Escuela San Martín', $hit->name);
        $this->assertSame('Buenos Aires', $hit->city);
        $this->assertSame('Av. Corrientes 1234', $hit->address);
        $this->assertEqualsWithDelta(9.2, $hit->score, PHP_FLOAT_EPSILON);
        $this->assertSame(1, $result->total);
    }

    public function testSearchPaginationIsApplied(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $this->assertSame(10, $params['body']['from']); // page 2, limit 10
                $this->assertSame(10, $params['body']['size']);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $this->buildService($client)->search('Escuela', 2);
    }

    // ── index/delete ─────────────────────────────────────────────────────────

    public function testIndexBuildsCorrectDocument(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getCity')->willReturn('Rosario');
        $address->method('getStreetAddress')->willReturn('Av. Pellegrini 500');

        $school = $this->createStub(School::class);
        $school->method('getId')->willReturn(3);
        $school->method('getName')->willReturn('Colegio Nacional');
        $school->method('getAddress')->willReturn($address);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('index')
            ->with(self::callback(function (array $params): bool {
                $this->assertSame('test_schools', $params['index']);
                $this->assertSame('3', $params['id']);
                $this->assertSame(3, $params['body']['school_id']);
                $this->assertSame('Colegio Nacional', $params['body']['name']);
                $this->assertSame('Rosario', $params['body']['city']);
                $this->assertSame('Av. Pellegrini 500', $params['body']['street_address']);
                $this->assertTrue($params['body']['is_active']);
                $this->assertArrayHasKey('updated_at', $params['body']);

                return true;
            }));

        $this->buildService($client)->index($school);
    }

    public function testIndexFallsBackToEmptyStringWhenNameIsNull(): void
    {
        $school = $this->createStub(School::class);
        $school->method('getId')->willReturn(1);
        $school->method('getName')->willReturn(null);
        $school->method('getAddress')->willReturn(null);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('index')
            ->with(self::callback(function (array $params): bool {
                $this->assertSame('', $params['body']['name']);
                $this->assertSame('', $params['body']['city']);

                return true;
            }));

        $this->buildService($client)->index($school);
    }

    public function testDeletePassesIgnore404ToClient(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'test_schools',
                'id' => '42',
                'client' => [
                    'ignore' => [404],
                ],
            ]);

        $this->buildService($client)->delete(42);
    }

    // ── createIndex ──────────────────────────────────────────────────────────

    public function testCreateIndexUsesSpanishStopWords(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(false);
        $indices->expects($this->once())
            ->method('create')
            ->with(self::callback(function (array $params): bool {
                $filters = $params['body']['settings']['analysis']['filter'];
                $this->assertArrayHasKey('spanish_stop', $filters);
                $this->assertSame('stop', $filters['spanish_stop']['type']);
                $this->assertSame('_spanish_', $filters['spanish_stop']['stopwords']);

                return true;
            }));

        $client = $this->createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $this->buildService($client)->createIndex();
    }

    public function testCreateIndexUsesEdgeNgramFilter(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(false);
        $indices->expects($this->once())
            ->method('create')
            ->with(self::callback(function (array $params): bool {
                $filter = $params['body']['settings']['analysis']['filter']['edge_ngram_filter'];
                $this->assertSame('edge_ngram', $filter['type']);
                $this->assertSame(2, $filter['min_gram']);

                return true;
            }));

        $client = $this->createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $this->buildService($client)->createIndex();
    }

    public function testCreateIndexUsesAsciifoldingInAnalyzers(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(false);
        $indices->expects($this->once())
            ->method('create')
            ->with(self::callback(function (array $params): bool {
                $analyzers = $params['body']['settings']['analysis']['analyzer'];
                $this->assertContains('asciifolding', $analyzers['school_name_index']['filter']);
                $this->assertContains('asciifolding', $analyzers['school_name_search']['filter']);

                return true;
            }));

        $client = $this->createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $this->buildService($client)->createIndex();
    }

    public function testCreateIndexSearchAnalyzerHasNoEdgeNgram(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(false);
        $indices->expects($this->once())
            ->method('create')
            ->with(self::callback(function (array $params): bool {
                $searchFilters = $params['body']['settings']['analysis']['analyzer']['school_name_search']['filter'];
                $this->assertNotContains('edge_ngram_filter', $searchFilters, 'Search analyzer must not expand the query with edge ngrams');

                return true;
            }));

        $client = $this->createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $this->buildService($client)->createIndex();
    }

    public function testCreateIndexNameFieldHasKeywordSubfieldWithNormalizer(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(false);
        $indices->expects($this->once())
            ->method('create')
            ->with(self::callback(function (array $params): bool {
                $mapping = $params['body']['mappings']['properties'];
                $this->assertArrayHasKey('name', $mapping);
                $this->assertSame('school_name_index', $mapping['name']['analyzer']);
                $this->assertSame('school_name_search', $mapping['name']['search_analyzer']);
                $this->assertSame('keyword', $mapping['name']['fields']['keyword']['type']);
                $this->assertSame('lowercase_ascii', $mapping['name']['fields']['keyword']['normalizer']);

                return true;
            }));

        $client = $this->createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $this->buildService($client)->createIndex();
    }

    public function testCreateIndexDropsExistingIndexWhenPresent(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(true);
        $indices->expects($this->once())->method('delete')->with([
            'index' => 'test_schools',
        ]);
        $indices->expects($this->once())->method('create');

        $client = $this->createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $this->buildService($client)->createIndex();
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function buildService(Client $client): SchoolSearchService
    {
        $tracing = $this->createStub(TracingService::class);
        $tracing->method('trace')->willReturnCallback(
            static fn (string $name, callable $fn): mixed => $fn(),
        );

        return new SchoolSearchService($client, self::INDEX_PREFIX, $tracing);
    }

    /**
     * @return array{hits: array{total: array{value: int}, hits: array<int, mixed>}}
     */
    private function emptySearchResponse(): array
    {
        return [
            'hits' => [
                'total' => [
                    'value' => 0,
                ],
                'hits' => [],
            ],
        ];
    }
}
