<?php

namespace App\Repository;

use App\Entity\Attendance;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attendance>
 */
class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    /**
     * Find attendance records for a student within a date range
     *
     * @return Attendance[]
     */
    public function findByStudentAndDateRange(
        Student $student,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.student = :student')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('student', $student)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attendance records for a student on a specific date (can be multiple)
     *
     * @return Attendance[]
     */
    public function findByStudentAndDate(Student $student, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.student = :student')
            ->andWhere('a.date = :date')
            ->setParameter('student', $student)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get attendance statistics for a date range
     */
    public function getStatsByDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($qb as $row) {
            $stats[$row['status']] = $row['count'];
        }

        return $stats;
    }
}
