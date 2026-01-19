<?php

namespace App\Repository;

use App\Entity\ArchivedRoute;
use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArchivedRoute>
 */
class ArchivedRouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArchivedRoute::class);
    }

    /**
     * Find archived routes by school and date range
     *
     * @return ArchivedRoute[]
     */
    public function findBySchoolAndDateRange(
        School $school,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.school = :school')
            ->andWhere('ar.date >= :start')
            ->andWhere('ar.date <= :end')
            ->setParameter('school', $school)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('ar.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate performance metrics for a school in a date range
     */
    public function getPerformanceStats(
        School $school,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        $qb = $this->createQueryBuilder('ar')
            ->select([
                'COUNT(ar.id) as totalRoutes',
                'SUM(ar.totalStops) as totalStops',
                'SUM(ar.completedStops) as completedStops',
                'SUM(ar.skippedStops) as skippedStops',
                'SUM(ar.studentsPickedUp) as studentsPickedUp',
                'SUM(ar.studentsDroppedOff) as studentsDroppedOff',
                'SUM(ar.noShows) as noShows',
                'AVG(ar.onTimePercentage) as avgOnTimePercentage',
                'SUM(ar.totalDistance) as totalDistance',
                'SUM(ar.actualDuration) as totalDuration',
            ])
            ->andWhere('ar.school = :school')
            ->andWhere('ar.date >= :start')
            ->andWhere('ar.date <= :end')
            ->andWhere('ar.status = :status')
            ->setParameter('school', $school)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleResult();

        return $qb;
    }

    /**
     * Get on-time performance by day
     */
    public function getOnTimePerformanceByDay(
        School $school,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        return $this->createQueryBuilder('ar')
            ->select('ar.date, AVG(ar.onTimePercentage) as avgOnTime')
            ->andWhere('ar.school = :school')
            ->andWhere('ar.date >= :start')
            ->andWhere('ar.date <= :end')
            ->andWhere('ar.status = :status')
            ->setParameter('school', $school)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'completed')
            ->groupBy('ar.date')
            ->orderBy('ar.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
