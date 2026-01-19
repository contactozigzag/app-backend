<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\PerformanceMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route('/api/reports')]
#[IsGranted('ROLE_SCHOOL_ADMIN')]
class PerformanceReportController extends AbstractController
{
    public function __construct(
        private readonly PerformanceMetricsService $metricsService,
    ) {
    }

    #[Route('/performance', name: 'performance_report', methods: ['GET'])]
    public function performanceReport(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $school = $user->getSchool();

        if (!$school) {
            return $this->json(['error' => 'No school associated with this admin'], 400);
        }

        // Get date range from query parameters
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        // Default to last 30 days if not provided
        if (!$startDate) {
            $startDate = new \DateTimeImmutable('-30 days');
        } else {
            $startDate = new \DateTimeImmutable($startDate);
        }

        if (!$endDate) {
            $endDate = new \DateTimeImmutable('today');
        } else {
            $endDate = new \DateTimeImmutable($endDate);
        }

        $report = $this->metricsService->generatePerformanceReport($school, $startDate, $endDate);

        return $this->json($report);
    }

    #[Route('/efficiency', name: 'efficiency_metrics', methods: ['GET'])]
    public function efficiencyMetrics(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $school = $user->getSchool();

        if (!$school) {
            return $this->json(['error' => 'No school associated with this admin'], 400);
        }

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if (!$startDate) {
            $startDate = new \DateTimeImmutable('-30 days');
        } else {
            $startDate = new \DateTimeImmutable($startDate);
        }

        if (!$endDate) {
            $endDate = new \DateTimeImmutable('today');
        } else {
            $endDate = new \DateTimeImmutable($endDate);
        }

        $metrics = $this->metricsService->calculateEfficiencyMetrics($school, $startDate, $endDate);

        return $this->json($metrics);
    }

    #[Route('/top-performing', name: 'top_performing_routes', methods: ['GET'])]
    public function topPerformingRoutes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $school = $user->getSchool();

        if (!$school) {
            return $this->json(['error' => 'No school associated with this admin'], 400);
        }

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $limit = (int) ($request->query->get('limit', 10));

        if (!$startDate) {
            $startDate = new \DateTimeImmutable('-30 days');
        } else {
            $startDate = new \DateTimeImmutable($startDate);
        }

        if (!$endDate) {
            $endDate = new \DateTimeImmutable('today');
        } else {
            $endDate = new \DateTimeImmutable($endDate);
        }

        $routes = $this->metricsService->getTopPerformingRoutes($school, $startDate, $endDate, $limit);

        return $this->json([
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'top_routes' => $routes,
        ]);
    }

    #[Route('/comparative', name: 'comparative_metrics', methods: ['GET'])]
    public function comparativeMetrics(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $school = $user->getSchool();

        if (!$school) {
            return $this->json(['error' => 'No school associated with this admin'], 400);
        }

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if (!$startDate) {
            $startDate = new \DateTimeImmutable('-30 days');
        } else {
            $startDate = new \DateTimeImmutable($startDate);
        }

        if (!$endDate) {
            $endDate = new \DateTimeImmutable('today');
        } else {
            $endDate = new \DateTimeImmutable($endDate);
        }

        $comparison = $this->metricsService->getComparativeMetrics($school, $startDate, $endDate);

        return $this->json($comparison);
    }
}
