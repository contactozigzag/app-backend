<?php

namespace App\Repository;

use App\Entity\Driver;
use App\Entity\LocationUpdate;
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
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
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
     * Delete old location updates (for cleanup)
     */
    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
