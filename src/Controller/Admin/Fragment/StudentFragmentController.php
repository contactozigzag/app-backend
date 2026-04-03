<?php

declare(strict_types=1);

namespace App\Controller\Admin\Fragment;

use App\Entity\Student;
use App\Repository\AttendanceRepository;
use App\Repository\RouteStopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StudentFragmentController extends AbstractController
{
    public function __construct(
        private readonly AttendanceRepository $attendanceRepository,
        private readonly RouteStopRepository $routeStopRepository,
    ) {
    }

    #[Route('/admin/student/{id}/attendance', name: 'admin_student_fragment_attendance')]
    public function attendance(Student $student): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $records = $this->attendanceRepository->findRecentByStudent($student);

        return $this->render('admin/fragment/student_attendance.html.twig', [
            'student' => $student,
            'records' => $records,
        ]);
    }

    #[Route('/admin/student/{id}/route-assignment', name: 'admin_student_fragment_route_assignment')]
    public function routeAssignment(Student $student): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $routeStops = $this->routeStopRepository->findByStudent($student);

        return $this->render('admin/fragment/student_route_assignment.html.twig', [
            'student' => $student,
            'routeStops' => $routeStops,
        ]);
    }
}
