<?php

namespace App\Repository;

use App\Entity\Absence;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /**
     * Find absences for a student on a specific date
     *
     * @return Absence[]
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
     * Find all absences for a date
     *
     * @return Absence[]
     */
    public function findByDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find absences that haven't triggered route recalculation
     *
     * @return Absence[]
     */
    public function findPendingRecalculation(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.routeRecalculated = :recalculated')
            ->andWhere('a.date >= :today')
            ->setParameter('recalculated', false)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if student is absent for a specific route type on a date
     */
    public function isStudentAbsent(Student $student, \DateTimeImmutable $date, string $routeType): bool
    {
        $absences = $this->findByStudentAndDate($student, $date);

        foreach ($absences as $absence) {
            if ($absence->getType() === 'full_day') {
                return true;
            }
            if ($absence->getType() === $routeType) {
                return true;
            }
        }

        return false;
    }
}
