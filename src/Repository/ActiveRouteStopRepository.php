<?php

namespace App\Repository;

use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActiveRouteStop>
 */
class ActiveRouteStopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActiveRouteStop::class);
    }

    /**
     * Find all stops for an active route, ordered
     *
     * @return ActiveRouteStop[]
     */
    public function findByActiveRouteOrdered(ActiveRoute $activeRoute): array
    {
        return $this->createQueryBuilder('ars')
            ->andWhere('ars.activeRoute = :activeRoute')
            ->setParameter('activeRoute', $activeRoute)
            ->orderBy('ars.stopOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the next pending stop for an active route
     */
    public function findNextPendingStop(ActiveRoute $activeRoute): ?ActiveRouteStop
    {
        return $this->createQueryBuilder('ars')
            ->andWhere('ars.activeRoute = :activeRoute')
            ->andWhere('ars.status IN (:statuses)')
            ->setParameter('activeRoute', $activeRoute)
            ->setParameter('statuses', ['pending', 'approaching'])
            ->orderBy('ars.stopOrder', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find stops by student for a date range
     *
     * @return ActiveRouteStop[]
     */
    public function findByStudentAndDateRange(
        Student $student,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        return $this->createQueryBuilder('ars')
            ->join('ars.activeRoute', 'ar')
            ->andWhere('ars.student = :student')
            ->andWhere('ar.date >= :start')
            ->andWhere('ar.date <= :end')
            ->setParameter('student', $student)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('ar.date', 'DESC')
            ->addOrderBy('ars.stopOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active stops for a student on a specific date
     *
     * @return ActiveRouteStop[]
     */
    public function findActiveStopsByStudentAndDate(
        Student $student,
        \DateTimeImmutable $date
    ): array {
        return $this->createQueryBuilder('ars')
            ->join('ars.activeRoute', 'ar')
            ->andWhere('ars.student = :student')
            ->andWhere('ar.date = :date')
            ->andWhere('ar.status IN (:statuses)')
            ->setParameter('student', $student)
            ->setParameter('date', $date)
            ->setParameter('statuses', ['scheduled', 'in_progress'])
            ->orderBy('ars.stopOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
