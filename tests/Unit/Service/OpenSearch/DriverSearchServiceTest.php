<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OpenSearch;

use App\Entity\Driver;
use App\Entity\User;
use App\Service\Logging\PerformanceLogger;
use App\Service\OpenSearch\DriverSearchService;
use Doctrine\ORM\EntityManagerInterface;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DriverSearchServiceTest extends TestCase
{
    private const string INDEX_PREFIX = 'test_';

    public function testSearchIncludesSchoolIdFilter(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $filters = $params['body']['query']['bool']['filter'];
                $schoolFilter = $filters[0];
                $this->assertSame([
                    'term' => [
                        'school_id' => 42,
                    ],
                ], $schoolFilter);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->search('Carlos', 42);
    }

    public function testSearchIdentificationNumberUsesPrefix(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $must = $params['body']['query']['bool']['must'][0];
                $shouldClauses = $must['bool']['should'];
                $lastClause = end($shouldClauses);
                $this->assertArrayHasKey('prefix', $lastClause);
                $this->assertArrayHasKey('identification_number', $lastClause['prefix']);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->search('12345', 1);
    }

    public function testShortQueryUsesMatchPhrasePrefix(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $must = $params['body']['query']['bool']['must'][0];
                $shouldClauses = $must['bool']['should'];
                // Short query (2-3 chars): should use match_phrase_prefix
                $this->assertArrayHasKey('match_phrase_prefix', $shouldClauses[0]);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->search('Ca', 1);
    }

    public function testLongerQueryUsesMultiMatch(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $must = $params['body']['query']['bool']['must'][0];
                $shouldClauses = $must['bool']['should'];
                // Longer query (4+ chars): should use multi_match
                $this->assertArrayHasKey('multi_match', $shouldClauses[0]);
                $this->assertSame('AUTO', $shouldClauses[0]['multi_match']['fuzziness']);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->search('Carlos', 1);
    }

    public function testEmptyQueryReturnsEmptyWithoutCallingOpenSearch(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('search');

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $result = $service->search('', 1);

        $this->assertSame([], $result->results);
        $this->assertSame(0, $result->total);
    }

    public function testSingleCharQueryReturnsEmptyWithoutCallingOpenSearch(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('search');

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $result = $service->search('a', 1);

        $this->assertSame([], $result->results);
    }

    public function testQueryIsTrimmedAndTruncated(): void
    {
        $longQuery = str_repeat('x', 150);
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $must = $params['body']['query']['bool']['must'][0];
                $shouldClauses = $must['bool']['should'];
                // multi_match query should be truncated to 100 chars
                $queryValue = $shouldClauses[0]['multi_match']['query'];
                $this->assertSame(100, mb_strlen($queryValue));

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->search($longQuery, 1);
    }

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
                            'driver_id' => 7,
                            'nickname' => 'Carlitos',
                            'first_name' => 'Carlos',
                            'last_name' => 'García',
                            'identification_number' => '12345678',
                        ],
                        '_score' => 8.45,
                    ],
                ],
            ],
        ]);

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $result = $service->search('Carlos', 1);

        $this->assertCount(1, $result->results);
        $hit = $result->results[0];
        $this->assertSame(7, $hit->driverId);
        $this->assertSame('Carlitos', $hit->nickname);
        $this->assertSame('Carlos', $hit->firstName);
        $this->assertSame('García', $hit->lastName);
        $this->assertSame('12345678', $hit->identificationNumber);
        $this->assertEqualsWithDelta(8.45, $hit->score, PHP_FLOAT_EPSILON);
    }

    public function testSourceFilteringPresent(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(self::callback(function (array $params): bool {
                $this->assertArrayHasKey('_source', $params['body']);
                $this->assertContains('driver_id', $params['body']['_source']);
                $this->assertContains('nickname', $params['body']['_source']);

                return true;
            }))
            ->willReturn($this->emptySearchResponse());

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->search('test', 1);
    }

    public function testIndexBuildsCorrectDocument(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(10);
        $user->method('getFirstName')->willReturn('Carlos');
        $user->method('getLastName')->willReturn('García');
        $user->method('getIdentificationNumber')->willReturn('12345678');

        $driver = $this->createStub(Driver::class);
        $driver->method('getId')->willReturn(7);
        $driver->method('getNickname')->willReturn('Carlitos');
        $driver->method('getUser')->willReturn($user);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('index')
            ->with(self::callback(function (array $params): bool {
                $this->assertSame('test_drivers', $params['index']);
                $this->assertSame('7', $params['id']);
                $this->assertSame(7, $params['body']['driver_id']);
                $this->assertSame('Carlitos', $params['body']['nickname']);
                $this->assertSame('Carlos García', $params['body']['full_name']);
                $this->assertSame('12345678', $params['body']['identification_number']);
                $this->assertSame([5, 8], $params['body']['school_id']);
                $this->assertTrue($params['body']['is_active']);

                return true;
            }));

        // Partially mock DriverSearchService to stub getSchoolIdsForDriver
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->getMockBuilder(DriverSearchService::class)
            ->setConstructorArgs([$client, self::INDEX_PREFIX, $em, new PerformanceLogger(new NullLogger())])
            ->onlyMethods(['getSchoolIdsForDriver'])
            ->getMock();
        $service->expects($this->once())->method('getSchoolIdsForDriver')->willReturn([5, 8]);

        $service->index($driver);
    }

    public function testDeleteCallsCorrectId(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'test_drivers',
                'id' => '42',
            ]);

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->delete(42);
    }

    public function testCreateIndexIncludesAsciifoldingAndEdgeNgram(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(false);
        $indices->expects($this->once())
            ->method('create')
            ->with(self::callback(function (array $params): bool {
                $settings = $params['body']['settings'];
                $autocompleteFilter = $settings['analysis']['filter']['autocomplete_filter'];
                $this->assertSame('edge_ngram', $autocompleteFilter['type']);

                $analyzer = $settings['analysis']['analyzer']['autocomplete'];
                $this->assertContains('asciifolding', $analyzer['filter']);
                $this->assertContains('autocomplete_filter', $analyzer['filter']);

                $mapping = $params['body']['mappings']['properties'];
                $this->assertArrayHasKey('nickname', $mapping);
                $this->assertArrayHasKey('full_name', $mapping);
                $this->assertSame('keyword', $mapping['identification_number']['type']);

                return true;
            }));

        $client = $this->createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $service = new DriverSearchService($client, self::INDEX_PREFIX, $this->createStub(EntityManagerInterface::class), new PerformanceLogger(new NullLogger()));
        $service->createIndex();
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
