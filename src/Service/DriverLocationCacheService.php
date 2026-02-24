<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DriverLocationCacheService
{
    private const int LOCATION_TTL_SECONDS = 15;

    private const int LAST_SEEN_TTL_SECONDS = 600; // 10 minutes

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function cacheLocation(
        int $driverId,
        float $lat,
        float $lng,
        float|null $speed,
        float|null $heading,
        int|null $activeRouteId,
    ): void {
        $locationKey = $this->locationKey($driverId);
        $lastSeenKey = $this->lastSeenKey($driverId);

        // Delete stale entry so the closure is called again with a fresh TTL
        $this->cache->delete($locationKey);
        $this->cache->delete($lastSeenKey);

        $this->cache->get($locationKey, static function (ItemInterface $item) use ($lat, $lng, $speed, $heading): string {
            $item->expiresAfter(self::LOCATION_TTL_SECONDS);

            return json_encode([
                'lat' => $lat,
                'lng' => $lng,
                'speed' => $speed,
                'heading' => $heading,
                'cachedAt' => new DateTimeImmutable()->format('c'),
            ], JSON_THROW_ON_ERROR);
        });

        $this->cache->get($lastSeenKey, static function (ItemInterface $item) use ($activeRouteId): string {
            $item->expiresAfter(self::LAST_SEEN_TTL_SECONDS);

            return json_encode([
                'ts' => time(),
                'routeId' => $activeRouteId,
            ], JSON_THROW_ON_ERROR);
        });
    }

    /**
     * @return array{lat: float, lng: float, speed: float|null, heading: float|null, cachedAt: string}|null
     */
    public function getLocation(int $driverId): array|null
    {
        $raw = $this->cache->get($this->locationKey($driverId), static fn (): string => '');

        if ($raw === '') {
            return null;
        }

        /** @var array{lat: float, lng: float, speed: float|null, heading: float|null, cachedAt: string} $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    public function getLastSeen(int $driverId): DateTimeImmutable|null
    {
        $raw = $this->cache->get($this->lastSeenKey($driverId), static fn (): string => '');

        if ($raw === '') {
            return null;
        }

        /** @var array{ts: int, routeId: int|null} $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return new DateTimeImmutable()->setTimestamp($data['ts']);
    }

    private function locationKey(int $driverId): string
    {
        return sprintf('driver.location.%d', $driverId);
    }

    private function lastSeenKey(int $driverId): string
    {
        return sprintf('driver.last_seen.%d', $driverId);
    }
}
