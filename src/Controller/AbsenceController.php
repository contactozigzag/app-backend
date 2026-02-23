<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Absence;
use App\Repository\AbsenceRepository;
use App\Repository\StudentRepository;
use App\Service\RouteRecalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(name: 'api_absences_')]
class AbsenceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AbsenceRepository $absenceRepository,
        private readonly StudentRepository $studentRepository,
        private readonly RouteRecalculationService $recalculationService
    ) {
    }

    /**
     * Report a student absence
     */
    #[Route('/api/absences', name: 'api_absences_create', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function reportAbsence(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (! isset($data['student_id']) || ! isset($data['date']) || ! isset($data['type'])) {
            return $this->json([
                'error' => 'student_id, date, and type are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $student = $this->studentRepository->find($data['student_id']);
        if (! $student instanceof \App\Entity\Student) {
            return $this->json([
                'error' => 'Student not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $date = new \DateTimeImmutable($data['date']);
        } catch (\Exception) {
            return $this->json([
                'error' => 'Invalid date format',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if absence already reported
        $existingAbsences = $this->absenceRepository->findByStudentAndDate($student, $date);
        foreach ($existingAbsences as $existing) {
            if ($existing->getType() === $data['type'] || $existing->getType() === 'full_day' || $data['type'] === 'full_day') {
                return $this->json([
                    'error' => 'Absence already reported for this date and type',
                ], Response::HTTP_CONFLICT);
            }
        }

        $absence = new Absence();
        $absence->setStudent($student);
        $absence->setDate($date);
        $absence->setType($data['type']);
        $absence->setReason($data['reason'] ?? 'other');
        $absence->setReportedBy($this->getUser());

        if (isset($data['notes'])) {
            $absence->setNotes($data['notes']);
        }

        $this->entityManager->persist($absence);
        $this->entityManager->flush();

        // Trigger route recalculation
        $recalculationResult = $this->recalculationService->recalculateForAbsence($absence);

        return $this->json([
            'success' => true,
            'absence_id' => $absence->getId(),
            'recalculation' => $recalculationResult,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get absences for a student
     */
    #[Route('/api/absences/student/{studentId}', name: 'api_absences_by_student', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getStudentAbsences(int $studentId, Request $request): JsonResponse
    {
        $student = $this->studentRepository->find($studentId);
        if (! $student instanceof \App\Entity\Student) {
            return $this->json([
                'error' => 'Student not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if ($start && $end) {
            try {
                $startDate = new \DateTimeImmutable($start);
                $endDate = new \DateTimeImmutable($end);
            } catch (\Exception) {
                return $this->json([
                    'error' => 'Invalid date format',
                ], Response::HTTP_BAD_REQUEST);
            }

            $absences = $this->absenceRepository->createQueryBuilder('a')
                ->andWhere('a.student = :student')
                ->andWhere('a.date >= :start')
                ->andWhere('a.date <= :end')
                ->setParameter('student', $student)
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->orderBy('a.date', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $absences = $this->absenceRepository->createQueryBuilder('a')
                ->andWhere('a.student = :student')
                ->setParameter('student', $student)
                ->orderBy('a.date', 'DESC')
                ->setMaxResults(50)
                ->getQuery()
                ->getResult();
        }

        $result = array_map(fn (Absence $absence): array => [
            'id' => $absence->getId(),
            'date' => $absence->getDate()->format('Y-m-d'),
            'type' => $absence->getType(),
            'reason' => $absence->getReason(),
            'notes' => $absence->getNotes(),
            'route_recalculated' => $absence->isRouteRecalculated(),
            'created_at' => $absence->getCreatedAt()->format('c'),
        ], $absences);

        return $this->json([
            'student_id' => $studentId,
            'count' => count($result),
            'absences' => $result,
        ]);
    }

    /**
     * Get absences for a specific date
     */
    #[Route('/api/absences/date/{date}', name: 'api_absences_by_date', methods: ['GET'])]
    #[IsGranted('ROUTE_MANAGE')]
    public function getAbsencesByDate(string $date): JsonResponse
    {
        try {
            $dateObj = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return $this->json([
                'error' => 'Invalid date format',
            ], Response::HTTP_BAD_REQUEST);
        }

        $absences = $this->absenceRepository->findByDate($dateObj);

        $result = array_map(fn (Absence $absence): array => [
            'id' => $absence->getId(),
            'student_id' => $absence->getStudent()->getId(),
            'student_name' => $absence->getStudent()->getFirstName() . ' ' . $absence->getStudent()->getLastName(),
            'type' => $absence->getType(),
            'reason' => $absence->getReason(),
            'notes' => $absence->getNotes(),
            'route_recalculated' => $absence->isRouteRecalculated(),
        ], $absences);

        return $this->json([
            'date' => $dateObj->format('Y-m-d'),
            'count' => count($result),
            'absences' => $result,
        ]);
    }

    /**
     * Trigger manual recalculation for pending absences
     */
    #[Route('/api/absences/recalculate-pending', name: 'api_absences_recalculate_pending', methods: ['POST'])]
    #[IsGranted('ROUTE_MANAGE')]
    public function recalculatePending(): JsonResponse
    {
        $results = $this->recalculationService->processPendingRecalculations();

        return $this->json([
            'success' => true,
            'processed_count' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * Cancel an absence
     */
    #[Route('/api/absences/{id}', name: 'api_absences_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_PARENT')]
    public function cancelAbsence(int $id): JsonResponse
    {
        $absence = $this->absenceRepository->find($id);

        if (! $absence instanceof \App\Entity\Absence) {
            return $this->json([
                'error' => 'Absence not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if absence is in the future
        if ($absence->getDate() < new \DateTimeImmutable('today')) {
            return $this->json([
                'error' => 'Cannot cancel past absences',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($absence);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Absence cancelled successfully',
        ]);
    }
}
