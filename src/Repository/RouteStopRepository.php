<?php

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
}
