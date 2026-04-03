<?php

declare(strict_types=1);

namespace App\Controller\Admin\Fragment;

use App\Entity\Driver;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverAlertRepository;
use App\Repository\RouteRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DriverFragmentController extends AbstractController
{
    private const int PAGE_SIZE = 15;

    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly DriverAlertRepository $driverAlertRepository,
    ) {
    }

    #[Route('/admin/driver/{id}/routes', name: 'admin_driver_fragment_routes')]
    public function routes(Driver $driver, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $routes = $this->routeRepository->findByDriver($driver, self::PAGE_SIZE, $offset);
        $total = $this->routeRepository->countByDriver($driver);
        $totalPages = (int) ceil($total / self::PAGE_SIZE);

        return $this->render('admin/fragment/driver_routes.html.twig', [
            'driver' => $driver,
            'routes' => $routes,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/admin/driver/{id}/rates', name: 'admin_driver_fragment_rates')]
    public function rates(Driver $driver): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->render('admin/fragment/driver_rates.html.twig', [
            'driver' => $driver,
            'rates' => $driver->getRates(),
        ]);
    }

    #[Route('/admin/driver/{id}/history', name: 'admin_driver_fragment_history')]
    public function history(Driver $driver, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;
        $from = new DateTimeImmutable('-90 days');

        $activeRoutes = $this->activeRouteRepository->findByDriver($driver, $from, self::PAGE_SIZE, $offset);
        $total = $this->activeRouteRepository->countByDriver($driver, $from);
        $totalPages = (int) ceil($total / self::PAGE_SIZE);

        return $this->render('admin/fragment/driver_history.html.twig', [
            'driver' => $driver,
            'activeRoutes' => $activeRoutes,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/admin/driver/{id}/alerts', name: 'admin_driver_fragment_alerts')]
    public function alerts(Driver $driver, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $alerts = $this->driverAlertRepository->findByDriver($driver, self::PAGE_SIZE, $offset);
        $total = $this->driverAlertRepository->countByDriver($driver);
        $totalPages = (int) ceil($total / self::PAGE_SIZE);

        return $this->render('admin/fragment/driver_alerts.html.twig', [
            'driver' => $driver,
            'alerts' => $alerts,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }
}
