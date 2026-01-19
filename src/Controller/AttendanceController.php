<?php

namespace App\Controller;

use App\Entity\Attendance;
use App\Repository\ActiveRouteStopRepository;
use App\Repository\AttendanceRepository;
use App\Repository\DriverRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/attendance', name: 'api_attendance_')]
class AttendanceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttendanceRepository $attendanceRepository,
        private readonly ActiveRouteStopRepository $stopRepository,
        private readonly StudentRepository $studentRepository,
        private readonly DriverRepository $driverRepository
    ) {
    }

    /**
     * Record student pickup
     */
    #[Route('/pickup', name: 'pickup', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function recordPickup(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['stop_id'])) {
            return $this->json([
                'error' => 'stop_id is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $stop = $this->stopRepository->find($data['stop_id']);
        if (!$stop) {
            return $this->json([
                'error' => 'Stop not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if already picked up
        $existingAttendance = $this->attendanceRepository->findByStudentAndDate(
            $stop->getStudent(),
            $stop->getActiveRoute()->getDate()
        );

        if ($existingAttendance && $existingAttendance->getStatus() === 'picked_up') {
            return $this->json([
                'error' => 'Student already picked up'
            ], Response::HTTP_CONFLICT);
        }

        $now = new \DateTimeImmutable();

        // Create or update attendance record
        if ($existingAttendance) {
            $attendance = $existingAttendance;
        } else {
            $attendance = new Attendance();
            $attendance->setStudent($stop->getStudent());
            $attendance->setActiveRouteStop($stop);
            $attendance->setDate($stop->getActiveRoute()->getDate());
        }

        $attendance->setStatus('picked_up');
        $attendance->setPickedUpAt($now);

        if (isset($data['latitude']) && isset($data['longitude'])) {
            $attendance->setPickupLatitude((string)$data['latitude']);
            $attendance->setPickupLongitude((string)$data['longitude']);
        }

        if (isset($data['driver_id'])) {
            $driver = $this->driverRepository->find($data['driver_id']);
            if ($driver) {
                $attendance->setRecordedBy($driver);
            }
        }

        if (isset($data['notes'])) {
            $attendance->setNotes($data['notes']);
        }

        // Update stop status
        $stop->setStatus('picked_up');
        $stop->setPickedUpAt($now);

        if (!$existingAttendance) {
            $this->entityManager->persist($attendance);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'attendance_id' => $attendance->getId(),
            'student_id' => $stop->getStudent()->getId(),
            'picked_up_at' => $now->format('c'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Record student drop-off
     */
    #[Route('/dropoff', name: 'dropoff', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function recordDropoff(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['stop_id'])) {
            return $this->json([
                'error' => 'stop_id is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $stop = $this->stopRepository->find($data['stop_id']);
        if (!$stop) {
            return $this->json([
                'error' => 'Stop not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Find attendance record
        $attendance = $this->attendanceRepository->findByStudentAndDate(
            $stop->getStudent(),
            $stop->getActiveRoute()->getDate()
        );

        if (!$attendance) {
            return $this->json([
                'error' => 'Student was not picked up'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($attendance->getStatus() === 'dropped_off') {
            return $this->json([
                'error' => 'Student already dropped off'
            ], Response::HTTP_CONFLICT);
        }

        $now = new \DateTimeImmutable();

        $attendance->setStatus('dropped_off');
        $attendance->setDroppedOffAt($now);

        if (isset($data['latitude']) && isset($data['longitude'])) {
            $attendance->setDropoffLatitude((string)$data['latitude']);
            $attendance->setDropoffLongitude((string)$data['longitude']);
        }

        if (isset($data['notes'])) {
            $attendance->setNotes($data['notes']);
        }

        // Update stop status
        $stop->setStatus('dropped_off');
        $stop->setDroppedOffAt($now);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'attendance_id' => $attendance->getId(),
            'student_id' => $stop->getStudent()->getId(),
            'dropped_off_at' => $now->format('c'),
        ]);
    }

    /**
     * Mark student as no-show
     */
    #[Route('/no-show', name: 'no_show', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function recordNoShow(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['stop_id'])) {
            return $this->json([
                'error' => 'stop_id is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $stop = $this->stopRepository->find($data['stop_id']);
        if (!$stop) {
            return $this->json([
                'error' => 'Stop not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $attendance = new Attendance();
        $attendance->setStudent($stop->getStudent());
        $attendance->setActiveRouteStop($stop);
        $attendance->setDate($stop->getActiveRoute()->getDate());
        $attendance->setStatus('no_show');

        if (isset($data['driver_id'])) {
            $driver = $this->driverRepository->find($data['driver_id']);
            if ($driver) {
                $attendance->setRecordedBy($driver);
            }
        }

        if (isset($data['notes'])) {
            $attendance->setNotes($data['notes']);
        }

        // Update stop status
        $stop->setStatus('skipped');

        $this->entityManager->persist($attendance);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'attendance_id' => $attendance->getId(),
            'student_id' => $stop->getStudent()->getId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Get manifest for an active route
     */
    #[Route('/manifest/{routeId}', name: 'manifest', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getManifest(int $routeId): JsonResponse
    {
        $stops = $this->stopRepository->createQueryBuilder('s')
            ->andWhere('s.activeRoute = :routeId')
            ->setParameter('routeId', $routeId)
            ->orderBy('s.stopOrder', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($stops)) {
            return $this->json([
                'error' => 'Route not found or has no stops'
            ], Response::HTTP_NOT_FOUND);
        }

        $manifest = [];
        foreach ($stops as $stop) {
            $student = $stop->getStudent();
            $address = $stop->getAddress();

            $manifest[] = [
                'stop_id' => $stop->getId(),
                'stop_order' => $stop->getStopOrder(),
                'student_id' => $student->getId(),
                'student_name' => $student->getFirstName() . ' ' . $student->getLastName(),
                'address' => $address->getStreet() . ', ' . $address->getCity(),
                'status' => $stop->getStatus(),
                'estimated_arrival' => $stop->getEstimatedArrivalTime(),
                'arrived_at' => $stop->getArrivedAt()?->format('c'),
                'picked_up_at' => $stop->getPickedUpAt()?->format('c'),
                'dropped_off_at' => $stop->getDroppedOffAt()?->format('c'),
            ];
        }

        return $this->json([
            'route_id' => $routeId,
            'total_stops' => count($manifest),
            'manifest' => $manifest,
        ]);
    }

    /**
     * Get attendance history for a student
     */
    #[Route('/student/{studentId}', name: 'student_history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getStudentHistory(int $studentId, Request $request): JsonResponse
    {
        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            return $this->json([
                'error' => 'Student not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if (!$start || !$end) {
            // Default to last 30 days
            $end = new \DateTimeImmutable('today');
            $start = $end->modify('-30 days');
        } else {
            try {
                $start = new \DateTimeImmutable($start);
                $end = new \DateTimeImmutable($end);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Invalid date format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $attendanceRecords = $this->attendanceRepository->findByStudentAndDateRange($student, $start, $end);

        $history = array_map(function ($attendance) {
            return [
                'id' => $attendance->getId(),
                'date' => $attendance->getDate()->format('Y-m-d'),
                'status' => $attendance->getStatus(),
                'picked_up_at' => $attendance->getPickedUpAt()?->format('c'),
                'dropped_off_at' => $attendance->getDroppedOffAt()?->format('c'),
                'notes' => $attendance->getNotes(),
            ];
        }, $attendanceRecords);

        return $this->json([
            'student_id' => $studentId,
            'student_name' => $student->getFirstName() . ' ' . $student->getLastName(),
            'count' => count($history),
            'history' => $history,
        ]);
    }

    /**
     * Get attendance statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function getStats(Request $request): JsonResponse
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if (!$start || !$end) {
            return $this->json([
                'error' => 'Start and end dates are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = new \DateTimeImmutable($end);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Invalid date format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $stats = $this->attendanceRepository->getStatsByDateRange($startDate, $endDate);

        return $this->json([
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'statistics' => $stats,
        ]);
    }
}
