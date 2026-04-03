<?php

declare(strict_types=1);

namespace App\Controller\Admin\Page;

use App\Service\Admin\ReconciliationService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReconciliationController extends AbstractController
{
    public function __construct(
        private readonly ReconciliationService $reconciliationService,
    ) {
    }

    #[Route('/admin/reconciliation', name: 'admin_reconciliation')]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $fromParam = $request->query->get('from');
        $toParam = $request->query->get('to');

        $from = $fromParam !== null && $fromParam !== ''
            ? new DateTimeImmutable($fromParam)
            : new DateTimeImmutable('first day of this month 00:00:00');

        $to = $toParam !== null && $toParam !== ''
            ? new DateTimeImmutable($toParam . ' 23:59:59')
            : new DateTimeImmutable('last day of this month 23:59:59');

        $summary = $this->reconciliationService->getSummary($from, $to);
        $driverBreakdown = $this->reconciliationService->getDriverBreakdown($from, $to);

        return $this->render('admin/page/reconciliation.html.twig', [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'driverBreakdown' => $driverBreakdown,
        ]);
    }
}
