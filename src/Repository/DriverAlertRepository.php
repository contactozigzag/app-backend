<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Driver;
use App\Entity\DriverAlert;
use App\Enum\AlertStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DriverAlert>
 */
class DriverAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DriverAlert::class);
    }

    public function findActiveByDistressedDriver(Driver $driver): ?DriverAlert
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.distressedDriver = :driver')
            ->andWhere('da.status IN (:statuses)')
            ->setParameter('driver', $driver)
            ->setParameter('statuses', [AlertStatus::PENDING, AlertStatus::RESPONDED])
            ->orderBy('da.triggeredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAlertId(string $alertId): ?DriverAlert
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.alertId = :alertId')
            ->setParameter('alertId', $alertId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find open alerts where $driver is listed in nearbyDriverIds.
     *
     * @return DriverAlert[]
     */
    public function findOpenAlertsForDriver(Driver $driver): array
    {
        // JSON_CONTAINS is supported by PostgreSQL via JSON operators but we
        // use a LIKE fallback here that works across databases for testing.
        // In production you may want a proper JSON query.
        $driverId = (string) $driver->getId();

        return $this->createQueryBuilder('da')
            ->andWhere('da.status = :status')
            ->andWhere('da.nearbyDriverIds LIKE :driverId')
            ->setParameter('status', AlertStatus::PENDING)
            ->setParameter('driverId', '%' . $driverId . '%')
            ->orderBy('da.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
