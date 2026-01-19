<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SafetyAuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route('/api/safety')]
#[IsGranted('ROLE_SCHOOL_ADMIN')]
class SafetyAuditController extends AbstractController
{
    public function __construct(
        private readonly SafetyAuditService $safetyAuditService,
    ) {
    }

    #[Route('/audit', name: 'safety_audit', methods: ['GET'])]
    public function safetyAudit(Request $request): JsonResponse
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

        $audit = $this->safetyAuditService->performSafetyAudit($school, $startDate, $endDate);

        return $this->json($audit);
    }
}
