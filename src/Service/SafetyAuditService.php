<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\School;
use App\Repository\AttendanceRepository;

class SafetyAuditService
{
    public function __construct(
        private readonly AttendanceRepository $attendanceRepository,
    ) {
    }

    /**
     * Perform comprehensive safety audit for a school
     */
    public function performSafetyAudit(
        School $school,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'school' => [
                'id' => $school->getId(),
                'name' => $school->getName(),
            ],
            'check_in_out_verification' => $this->verifyCheckInCheckOut($school, $startDate, $endDate),
            'orphaned_records' => $this->findOrphanedRecords($school, $startDate, $endDate),
            'missing_check_outs' => $this->findMissingCheckOuts($school, $startDate, $endDate),
            'duplicate_records' => $this->findDuplicateRecords($school, $startDate, $endDate),
            'time_anomalies' => $this->detectTimeAnomalies($school, $startDate, $endDate),
            'safety_score' => $this->calculateSafetyScore($school, $startDate, $endDate),
        ];
    }

    /**
     * Verify check-in/check-out consistency
     */
    private function verifyCheckInCheckOut(
        School $school,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $qb = $this->attendanceRepository->createQueryBuilder('a')
            ->join('a.student', 's')
            ->andWhere('s.school = :school')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('school', $school)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        $allRecords = $qb->getQuery()->getResult();

        $totalRecords = count($allRecords);
        $validRecords = 0;
        $invalidRecords = [];

        foreach ($allRecords as $record) {
            $isValid = true;
            $issues = [];

            // Check if pickup happened before dropoff
            if ($record->getPickedUpAt() && $record->getDroppedOffAt() && $record->getPickedUpAt() > $record->getDroppedOffAt()) {
                $isValid = false;
                $issues[] = 'Pickup time is after dropoff time';
            }

            // Check if status matches the timestamps
            if ($record->getStatus() === 'picked_up' && ! $record->getPickedUpAt()) {
                $isValid = false;
                $issues[] = 'Status is picked_up but no pickup timestamp';
            }

            if ($record->getStatus() === 'dropped_off' && ! $record->getDroppedOffAt()) {
                $isValid = false;
                $issues[] = 'Status is dropped_off but no dropoff timestamp';
            }

            if ($isValid) {
                $validRecords++;
            } else {
                $invalidRecords[] = [
                    'id' => $record->getId(),
                    'student_id' => $record->getStudent()->getId(),
                    'date' => $record->getDate()->format('Y-m-d'),
                    'status' => $record->getStatus(),
                    'issues' => $issues,
                ];
            }
        }

        return [
            'total_records' => $totalRecords,
            'valid_records' => $validRecords,
            'invalid_records_count' => count($invalidRecords),
            'invalid_records' => array_slice($invalidRecords, 0, 50), // Limit to first 50
            'validation_rate' => $totalRecords > 0
                ? round(($validRecords / $totalRecords) * 100, 2)
                : 100,
        ];
    }

    /**
     * Find attendance records without corresponding route stops
     */
    private function findOrphanedRecords(
        School $school,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $qb = $this->attendanceRepository->createQueryBuilder('a')
            ->join('a.student', 's')
            ->leftJoin('a.activeRouteStop', 'ars')
            ->andWhere('s.school = :school')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->andWhere('ars.id IS NULL')
            ->setParameter('school', $school)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        $orphanedRecords = $qb->getQuery()->getResult();

        return [
            'count' => count($orphanedRecords),
            'records' => array_slice(array_map(fn ($record): array => [
                'id' => $record->getId(),
                'student_id' => $record->getStudent()->getId(),
                'student_name' => $record->getStudent()->getFirstName() . ' ' .
                                 $record->getStudent()->getLastName(),
                'date' => $record->getDate()->format('Y-m-d'),
                'status' => $record->getStatus(),
            ], $orphanedRecords), 0, 50),
        ];
    }

    /**
     * Find students picked up but not dropped off
     */
    private function findMissingCheckOuts(
        School $school,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $qb = $this->attendanceRepository->createQueryBuilder('a')
            ->join('a.student', 's')
            ->andWhere('s.school = :school')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->andWhere('a.pickedUpAt IS NOT NULL')
            ->andWhere('a.droppedOffAt IS NULL')
            ->andWhere('a.status != :noShow')
            ->setParameter('school', $school)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('noShow', 'no_show');

        $missingCheckOuts = $qb->getQuery()->getResult();

        return [
            'count' => count($missingCheckOuts),
            'records' => array_map(fn ($record): array => [
                'id' => $record->getId(),
                'student_id' => $record->getStudent()->getId(),
                'student_name' => $record->getStudent()->getFirstName() . ' ' .
                                 $record->getStudent()->getLastName(),
                'date' => $record->getDate()->format('Y-m-d'),
                'picked_up_at' => $record->getPickedUpAt()?->format('Y-m-d H:i:s'),
                'status' => $record->getStatus(),
            ], $missingCheckOuts),
        ];
    }

    /**
     * Find duplicate attendance records
     */
    private function findDuplicateRecords(
        School $school,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $qb = $this->attendanceRepository->createQueryBuilder('a')
            ->select('a.date, s.id as student_id, COUNT(a.id) as record_count')
            ->join('a.student', 's')
            ->andWhere('s.school = :school')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('school', $school)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('a.date', 's.id')
            ->having('COUNT(a.id) > 1');

        $duplicates = $qb->getQuery()->getResult();

        return [
            'count' => count($duplicates),
            'records' => array_slice($duplicates, 0, 50),
        ];
    }

    /**
     * Detect time anomalies (e.g., very long or very short ride times)
     */
    private function detectTimeAnomalies(
        School $school,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $qb = $this->attendanceRepository->createQueryBuilder('a')
            ->join('a.student', 's')
            ->andWhere('s.school = :school')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->andWhere('a.pickedUpAt IS NOT NULL')
            ->andWhere('a.droppedOffAt IS NOT NULL')
            ->setParameter('school', $school)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        $records = $qb->getQuery()->getResult();

        $anomalies = [];

        foreach ($records as $record) {
            $duration = $record->getDroppedOffAt()->getTimestamp() -
                       $record->getPickedUpAt()->getTimestamp();

            // Flag if ride is less than 1 minute or more than 3 hours
            if ($duration < 60 || $duration > 10800) {
                $anomalies[] = [
                    'id' => $record->getId(),
                    'student_id' => $record->getStudent()->getId(),
                    'student_name' => $record->getStudent()->getFirstName() . ' ' .
                                     $record->getStudent()->getLastName(),
                    'date' => $record->getDate()->format('Y-m-d'),
                    'duration_minutes' => round($duration / 60, 2),
                    'picked_up_at' => $record->getPickedUpAt()->format('Y-m-d H:i:s'),
                    'dropped_off_at' => $record->getDroppedOffAt()->format('Y-m-d H:i:s'),
                    'issue' => $duration < 60 ? 'Too short' : 'Too long',
                ];
            }
        }

        return [
            'count' => count($anomalies),
            'records' => array_slice($anomalies, 0, 50),
        ];
    }

    /**
     * Calculate overall safety score
     */
    private function calculateSafetyScore(
        School $school,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $checkInOut = $this->verifyCheckInCheckOut($school, $startDate, $endDate);
        $orphaned = $this->findOrphanedRecords($school, $startDate, $endDate);
        $missingCheckOuts = $this->findMissingCheckOuts($school, $startDate, $endDate);
        $duplicates = $this->findDuplicateRecords($school, $startDate, $endDate);
        $anomalies = $this->detectTimeAnomalies($school, $startDate, $endDate);

        // Calculate score based on issues found
        $score = 100;

        // Deduct points for each category
        $totalRecords = $checkInOut['total_records'] ?: 1;
        $score -= ($checkInOut['invalid_records_count'] / $totalRecords) * 20;
        $score -= ($orphaned['count'] / $totalRecords) * 20;
        $score -= ($missingCheckOuts['count'] / $totalRecords) * 30;
        $score -= ($duplicates['count'] / $totalRecords) * 15;
        $score -= ($anomalies['count'] / $totalRecords) * 15;

        $score = max(0, min(100, $score)); // Clamp between 0 and 100

        return [
            'score' => round($score, 2),
            'rating' => $this->getScoreRating($score),
            'total_issues' => $checkInOut['invalid_records_count'] +
                            $orphaned['count'] +
                            $missingCheckOuts['count'] +
                            $duplicates['count'] +
                            $anomalies['count'],
            'breakdown' => [
                'invalid_check_ins' => $checkInOut['invalid_records_count'],
                'orphaned_records' => $orphaned['count'],
                'missing_check_outs' => $missingCheckOuts['count'],
                'duplicates' => $duplicates['count'],
                'time_anomalies' => $anomalies['count'],
            ],
        ];
    }

    private function getScoreRating(float $score): string
    {
        if ($score >= 95) {
            return 'Excellent';
        }

        if ($score >= 85) {
            return 'Good';
        }

        if ($score >= 70) {
            return 'Fair';
        }

        return 'Needs Attention';
    }
}
