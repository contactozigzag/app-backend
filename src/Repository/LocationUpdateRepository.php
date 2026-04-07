<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActiveRoute;
use App\Entity\Driver;
use App\Entity\LocationUpdate;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocationUpdate>
 */
class LocationUpdateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocationUpdate::class);
    }

    /**
     * Get the latest location for a driver
     */
    public function findLatestByDriver(Driver $driver): ?LocationUpdate
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.driver = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('l.timestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get location history for a driver within a date range
     *
     * @return LocationUpdate[]
     */
    public function findByDriverAndDateRange(
        Driver $driver,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        return $this->createQueryBuilder('l')
            ->andWhere('l.driver = :driver')
            ->andWhere('l.timestamp >= :start')
            ->andWhere('l.timestamp <= :end')
            ->setParameter('driver', $driver)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('l.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find an existing update for the same driver/route/timestamp (idempotency dedup).
     */
    public function findDuplicate(Driver $driver, ?ActiveRoute $activeRoute, DateTimeImmutable $recordedAt): ?LocationUpdate
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.driver = :driver')
            ->andWhere('l.timestamp = :ts')
            ->setParameter('driver', $driver)
            ->setParameter('ts', $recordedAt)
            ->setMaxResults(1);

        if ($activeRoute instanceof ActiveRoute) {
            $qb->andWhere('l.activeRoute = :route')->setParameter('route', $activeRoute);
        } else {
            $qb->andWhere('l.activeRoute IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Count location updates older than the given date (used for dry-run preview).
     */
    public function countOlderThan(DateTimeImmutable $date): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete old location updates (for cleanup)
     */
    public function deleteOlderThan(DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Find the most-recent location update for each in-progress driver within a given radius,
     * using PostGIS ST_DWithin on the generated geography(Point,4326) column.
     *
     * Returns results sorted by ascending distance. Only considers location updates received
     * within the last $maxAgeSeconds seconds so stale Redis-less positions are excluded.
     *
     * @return array<int, array{driverId: int, lat: float, lng: float, distanceMeters: float}>
     */
    public function findNearbyDriversInProgress(
        float $lat,
        float $lng,
        float $radiusMeters,
        int $excludeDriverId,
        int $maxAgeSeconds = 300,
    ): array {
        // CTE defines the reference point once so named params are not repeated.
        $sql = <<<'SQL'
            WITH ref AS (
                SELECT ST_MakePoint(:lng, :lat)::geography AS point
            )
            SELECT driver_id, lat, lng, distance_meters
            FROM (
                SELECT DISTINCT ON (lu.driver_id)
                    lu.driver_id,
                    lu.latitude::float8  AS lat,
                    lu.longitude::float8 AS lng,
                    ST_Distance(lu.point, ref.point) AS distance_meters
                FROM location_updates lu
                CROSS JOIN ref
                INNER JOIN active_routes ar ON ar.id = lu.active_route_id
                WHERE ar.status IN ('in_progress', 'arriving')
                  AND lu.driver_id != :exclude_driver_id
                  AND lu.point IS NOT NULL
                  AND ST_DWithin(lu.point, ref.point, :radius_meters)
                  AND lu.timestamp >= NOW() - make_interval(secs => :max_age_seconds)
                ORDER BY lu.driver_id, lu.timestamp DESC
            ) sub
            ORDER BY distance_meters ASC
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'lat' => $lat,
            'lng' => $lng,
            'exclude_driver_id' => $excludeDriverId,
            'radius_meters' => $radiusMeters,
            'max_age_seconds' => $maxAgeSeconds,
        ])->fetchAllAssociative();

        return array_map(
            static function (array $row): array {
                $driverId = $row['driver_id'];
                $lat = $row['lat'];
                $lng = $row['lng'];
                $distance = $row['distance_meters'];

                return [
                    'driverId' => is_numeric($driverId) ? (int) $driverId : 0,
                    'lat' => is_numeric($lat) ? (float) $lat : 0.0,
                    'lng' => is_numeric($lng) ? (float) $lng : 0.0,
                    'distanceMeters' => is_numeric($distance) ? (float) $distance : 0.0,
                ];
            },
            $rows,
        );
    }

    /**
     * Get the latest location for an active route
     */
    public function findLatestByActiveRoute(ActiveRoute $activeRoute): ?LocationUpdate
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.activeRoute = :activeRoute')
            ->setParameter('activeRoute', $activeRoute)
            ->orderBy('l.timestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
