<?php

declare(strict_types=1);

namespace App\Controller\Admin\Fragment;

use App\Entity\RouteStop;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Repository\RouteStopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParentFragmentController extends AbstractController
{
    private const int PAGE_SIZE = 15;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly RouteStopRepository $routeStopRepository,
    ) {
    }

    #[Route('/admin/parent/{id}/children', name: 'admin_parent_fragment_children')]
    public function children(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->render('admin/fragment/parent_children.html.twig', [
            'parent' => $user,
            'students' => $user->getStudents(),
        ]);
    }

    #[Route('/admin/parent/{id}/payments', name: 'admin_parent_fragment_payments')]
    public function payments(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $payments = $this->paymentRepository->findByUser($user, null, self::PAGE_SIZE, $offset);

        return $this->render('admin/fragment/parent_payments.html.twig', [
            'parent' => $user,
            'payments' => $payments,
            'page' => $page,
        ]);
    }

    #[Route('/admin/parent/{id}/route-links', name: 'admin_parent_fragment_route_links')]
    public function routeLinks(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var RouteStop[] $routeStops */
        $routeStops = [];
        foreach ($user->getStudents() as $student) {
            foreach ($this->routeStopRepository->findByStudent($student) as $stop) {
                $routeStops[] = $stop;
            }
        }

        return $this->render('admin/fragment/parent_route_links.html.twig', [
            'parent' => $user,
            'routeStops' => $routeStops,
        ]);
    }
}
