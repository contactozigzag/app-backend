<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActiveRoute;
use App\Entity\Driver;
use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActiveRoute>
 */
class ActiveRouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActiveRoute::class);
    }

    /**
     * Find active route for a driver on a specific date
     */
    public function findActiveByDriverAndDate(Driver $driver, \DateTimeImmutable $date): ?ActiveRoute
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.driver = :driver')
            ->andWhere('ar.date = :date')
            ->andWhere('ar.status IN (:statuses)')
            ->setParameter('driver', $driver)
            ->setParameter('date', $date)
            ->setParameter('statuses', ['scheduled', 'in_progress'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all in-progress routes
     *
     * @return ActiveRoute[]
     */
    public function findInProgress(): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.status = :status')
            ->setParameter('status', 'in_progress')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find routes by school and date
     *
     * @return ActiveRoute[]
     */
    public function findBySchoolAndDate(School $school, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('ar')
            ->join('ar.routeTemplate', 'rt')
            ->andWhere('rt.school = :school')
            ->andWhere('ar.date = :date')
            ->setParameter('school', $school)
            ->setParameter('date', $date)
            ->orderBy('ar.status', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming routes by parent user
     *
     * @return ActiveRoute[]
     */
    public function findUpcomingByParent(
        \App\Entity\User $parent,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        return $this->createQueryBuilder('ar')
            ->join('ar.stops', 'ars')
            ->join('ars.student', 's')
            ->join('s.parents', 'p')
            ->andWhere('p = :parent')
            ->andWhere('ar.date >= :start')
            ->andWhere('ar.date <= :end')
            ->setParameter('parent', $parent)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('ar.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active routes by school for today
     *
     * @return ActiveRoute[]
     */
    public function findActiveBySchool(School $school, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('ar')
            ->join('ar.routeTemplate', 'rt')
            ->andWhere('rt.school = :school')
            ->andWhere('ar.date = :date')
            ->andWhere('ar.status IN (:statuses)')
            ->setParameter('school', $school)
            ->setParameter('date', $date)
            ->setParameter('statuses', ['scheduled', 'in_progress'])
            ->orderBy('ar.status', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
