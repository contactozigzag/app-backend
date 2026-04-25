<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActiveRoute;
use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\School;
use App\Entity\User;
use DateTimeImmutable;
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
    public function findActiveByDriverAndDate(Driver $driver, DateTimeImmutable $date): ?ActiveRoute
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
     * Find all in-progress routes for today.
     *
     * Scoped to today so stale zombie rows (a trip that was never completed
     * days ago) do not pollute live dashboards, anomaly detection, or
     * geofencing iteration. The nightly stale-route expiration scheduler
     * cancels old non-terminal rows; this filter guards the read side in
     * case the scheduler has not run yet.
     *
     * @return ActiveRoute[]
     */
    public function findInProgress(): array
    {
        $today = new DateTimeImmutable('today');

        return $this->createQueryBuilder('ar')
            ->andWhere('ar.status = :status')
            ->andWhere('ar.date = :today')
            ->setParameter('status', 'in_progress')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find routes by school and date
     *
     * @return ActiveRoute[]
     */
    public function findBySchoolAndDate(School $school, DateTimeImmutable $date): array
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
        User $parent,
        DateTimeImmutable $start,
        DateTimeImmutable $end
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
     * Find non-terminal routes whose trip date is older than today — these
     * are zombies a driver never completed, so they pollute dashboards and
     * the parent tracking screen (stale lat/lng). The expire scheduler
     * cancels these nightly.
     *
     * @return ActiveRoute[]
     */
    public function findStaleNonTerminal(int $limit = 500): array
    {
        $today = new DateTimeImmutable('today');

        return $this->createQueryBuilder('ar')
            ->andWhere('ar.status IN (:statuses)')
            ->andWhere('ar.date < :today')
            ->setParameter('statuses', ['scheduled', 'in_progress', 'arriving'])
            ->setParameter('today', $today)
            ->orderBy('ar.date', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find non-terminal ActiveRoutes for the same (routeTemplate, driver, date)
     * triple — the duplicates that ActiveRouteCreateProcessor auto-cancels
     * before persisting a fresh trip. Scoped by template so a morning trip is
     * not cancelled when the afternoon route is scheduled (Route.type is
     * either 'morning' or 'afternoon', and the driver may run both).
     *
     * @return ActiveRoute[]
     */
    public function findNonTerminalForTemplate(Route $template, Driver $driver, DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.routeTemplate = :template')
            ->andWhere('ar.driver = :driver')
            ->andWhere('ar.date = :date')
            ->andWhere('ar.status IN (:statuses)')
            ->setParameter('template', $template)
            ->setParameter('driver', $driver)
            ->setParameter('date', $date)
            ->setParameter('statuses', ['scheduled', 'in_progress', 'arriving'])
            ->getQuery()
            ->getResult();
    }

    public function countInProgressToday(): int
    {
        $today = new DateTimeImmutable('today');

        return (int) $this->createQueryBuilder('ar')
            ->select('COUNT(ar.id)')
            ->andWhere('ar.status = :status')
            ->andWhere('ar.date = :today')
            ->setParameter('status', 'in_progress')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findWeeklyStats(): array
    {
        $start = new DateTimeImmutable('-6 days midnight');
        $today = new DateTimeImmutable('today');

        return $this->createQueryBuilder('ar')
            ->select('ar.date, ar.status, COUNT(ar.id) as cnt')
            ->andWhere('ar.date >= :start')
            ->andWhere('ar.date <= :today')
            ->setParameter('start', $start)
            ->setParameter('today', $today)
            ->groupBy('ar.date, ar.status')
            ->orderBy('ar.date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return ActiveRoute[]
     */
    public function findInProgressBySchool(School $school): array
    {
        $today = new DateTimeImmutable('today');

        return $this->createQueryBuilder('ar')
            ->join('ar.routeTemplate', 'rt')
            ->where('rt.school = :school')
            ->andWhere('ar.status = :status')
            ->andWhere('ar.date = :today')
            ->setParameter('school', $school)
            ->setParameter('status', 'in_progress')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent active routes for a driver (last 90 days by default)
     *
     * @return ActiveRoute[]
     */
    public function findByDriver(Driver $driver, DateTimeImmutable $from, int $limit = 15, int $offset = 0): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.driver = :driver')
            ->andWhere('ar.date >= :from')
            ->setParameter('driver', $driver)
            ->setParameter('from', $from)
            ->orderBy('ar.date', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByDriver(Driver $driver, DateTimeImmutable $from): int
    {
        return (int) $this->createQueryBuilder('ar')
            ->select('COUNT(ar.id)')
            ->andWhere('ar.driver = :driver')
            ->andWhere('ar.date >= :from')
            ->setParameter('driver', $driver)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find active routes by school for today
     *
     * @return ActiveRoute[]
     */
    public function findActiveBySchool(School $school, DateTimeImmutable $date): array
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
