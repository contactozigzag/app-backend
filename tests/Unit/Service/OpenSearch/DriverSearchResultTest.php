<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OpenSearch;

use App\Service\OpenSearch\DriverSearchHit;
use App\Service\OpenSearch\DriverSearchResult;
use PHPUnit\Framework\TestCase;

final class DriverSearchResultTest extends TestCase
{
    public function testDriverSearchHitConstruction(): void
    {
        $hit = new DriverSearchHit(
            driverId: 7,
            nickname: 'Carlitos',
            firstName: 'Carlos',
            lastName: 'García',
            identificationNumber: '12345678',
            score: 8.45,
        );

        $this->assertSame(7, $hit->driverId);
        $this->assertSame('Carlitos', $hit->nickname);
        $this->assertSame('Carlos', $hit->firstName);
        $this->assertSame('García', $hit->lastName);
        $this->assertSame('12345678', $hit->identificationNumber);
        $this->assertEqualsWithDelta(8.45, $hit->score, PHP_FLOAT_EPSILON);
    }

    public function testDriverSearchResultConstruction(): void
    {
        $hit = new DriverSearchHit(1, 'Nick', 'First', 'Last', '111', 1.0);
        $result = new DriverSearchResult([$hit], 1, 1, 10);

        $this->assertCount(1, $result->results);
        $this->assertSame(1, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(10, $result->limit);
        $this->assertSame($hit, $result->results[0]);
    }

    public function testEmptyResult(): void
    {
        $result = new DriverSearchResult([], 0, 1, 10);

        $this->assertSame([], $result->results);
        $this->assertSame(0, $result->total);
    }
}
