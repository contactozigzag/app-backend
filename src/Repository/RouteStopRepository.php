<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteStop>
 */
class RouteStopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteStop::class);
    }

    /**
     * Find all stops for a route, ordered by stop order
     * This method returns all active stops regardless of confirmation status
     *
     * @return RouteStop[]
     */
    public function findByRouteOrdered(Route $route): array
    {
        return $this->createQueryBuilder('rs')
            ->andWhere('rs.route = :route')
            ->andWhere('rs.isActive = :active')
            ->setParameter('route', $route)
            ->setParameter('active', true)
            ->orderBy('rs.stopOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active and confirmed stops for a route, ordered by stop order
     * This should be used for route optimization and actual route operations
     *
     * @return RouteStop[]
     */
    public function findActiveAndConfirmedByRoute(Route $route): array
    {
        return $this->createQueryBuilder('rs')
            ->andWhere('rs.route = :route')
            ->andWhere('rs.isActive = :active')
            ->andWhere('rs.isConfirmed = :confirmed')
            ->setParameter('route', $route)
            ->setParameter('active', true)
            ->setParameter('confirmed', true)
            ->orderBy('rs.stopOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all routes that include a specific student
     *
     * @return RouteStop[]
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('rs')
            ->andWhere('rs.student = :student')
            ->andWhere('rs.isActive = :active')
            ->setParameter('student', $student)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active and confirmed routes that include a specific student
     *
     * @return RouteStop[]
     */
    public function findActiveAndConfirmedByStudent(Student $student): array
    {
        return $this->createQueryBuilder('rs')
            ->andWhere('rs.student = :student')
            ->andWhere('rs.isActive = :active')
            ->andWhere('rs.isConfirmed = :confirmed')
            ->setParameter('student', $student)
            ->setParameter('active', true)
            ->setParameter('confirmed', true)
            ->getQuery()
            ->getResult();
    }
}
