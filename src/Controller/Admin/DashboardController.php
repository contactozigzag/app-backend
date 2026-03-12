<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\DashboardStatsService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly DashboardStatsService $statsService,
        private readonly ChartBuilderInterface $chartBuilder,
        #[Autowire(env: 'MERCURE_PUBLIC_URL')]
        private readonly string $mercurePublicUrl,
    ) {
    }

    #[Override]
    public function index(): Response
    {
        $weeklyChart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $weeklyChart->setData($this->statsService->getWeeklyRouteChartData());
        $weeklyChart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                ],
            ],
        ]);

        $alertChart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $alertChart->setData($this->statsService->getAlertChartData());
        $alertChart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                ],
            ],
        ]);

        return $this->render('admin/dashboard/index.html.twig', [
            'kpis' => $this->statsService->getPlatformKpis(),
            'activeRoutes' => $this->statsService->getActiveRoutesNow(),
            'openAlerts' => $this->statsService->getOpenAlerts(),
            'weeklyChart' => $weeklyChart,
            'alertChart' => $alertChart,
            'mercurePublicUrl' => $this->mercurePublicUrl,
        ]);
    }

    #[Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Zigzag Dashboard');
    }

    #[Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(SchoolCrudController::class, 'School', 'fas fa-school');
        yield MenuItem::linkTo(UserCrudController::class, 'User', 'fas fa-user');
        yield MenuItem::linkTo(StudentCrudController::class, 'Student', 'fas fa-user-graduate');
        yield MenuItem::linkTo(DriverCrudController::class, 'Driver', 'fas fa-bus');
        yield MenuItem::linkTo(RouteCrudController::class, 'Route', 'fas fa-route');
        yield MenuItem::linkTo(RouteStopCrudController::class, 'Route Stop', 'fas fa-location-dot');
    }

    #[Override]
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getFullName())
            ->setGravatarEmail($user->getEmail())
           /* ->addMenuItems([
                MenuItem::linkToRoute('My Profile', 'fa fa-id-card', '...', ['...' => '...']),
                MenuItem::linkToRoute('Settings', 'fa fa-user-cog', '...', ['...' => '...']),
                MenuItem::section(),
                MenuItem::linkToLogout('Logout', 'fa fa-sign-out'),
            ])*/;
    }
}
