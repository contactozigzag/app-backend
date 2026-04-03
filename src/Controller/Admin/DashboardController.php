<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
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
            'pushHealth' => $this->statsService->getPushNotificationHealth(),
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
        yield MenuItem::linkToRoute('Live Map', 'fas fa-map-location-dot', 'admin_live_operations')
            ->setBadge($this->statsService->countActiveRoutes(), 'info');

        yield MenuItem::section('People');
        yield MenuItem::subMenu('Users', 'fas fa-users')->setSubItems([
            MenuItem::linkTo(UserCrudController::class, 'All Users', 'fas fa-user'),
            MenuItem::linkTo(DriverCrudController::class, 'Drivers', 'fas fa-id-card'),
            MenuItem::linkTo(VehicleCrudController::class, 'Vehicles', 'fas fa-truck'),
        ]);
        yield MenuItem::linkTo(StudentCrudController::class, 'Students', 'fas fa-user-graduate');
        yield MenuItem::linkTo(ParentCrudController::class, 'Parents', 'fas fa-person-shelter');
        yield MenuItem::linkTo(SchoolCrudController::class, 'Schools', 'fas fa-school');

        yield MenuItem::section('Transport');
        yield MenuItem::subMenu('Routes', 'fas fa-route')->setSubItems([
            MenuItem::linkTo(RouteCrudController::class, 'Route Templates', 'fas fa-route'),
            MenuItem::linkTo(RouteStopCrudController::class, 'Route Stops', 'fas fa-location-dot'),
            MenuItem::linkTo(ActiveRouteCrudController::class, 'Active Sessions', 'fas fa-play-circle'),
            MenuItem::linkTo(SpecialEventRouteCrudController::class, 'Special Events', 'fas fa-map-location-dot'),
            MenuItem::linkTo(ArchivedRouteCrudController::class, 'Archived', 'fas fa-archive'),
        ]);

        yield MenuItem::section('Finance');
        yield MenuItem::subMenu('Payments', 'fas fa-credit-card')->setSubItems([
            MenuItem::linkTo(PaymentCrudController::class, 'Payments', 'fas fa-money-bill'),
            MenuItem::linkTo(PaymentTransactionCrudController::class, 'Transactions', 'fas fa-receipt'),
            MenuItem::linkTo(DriverRateCrudController::class, 'Driver Rates', 'fas fa-tags'),
            MenuItem::linkTo(SubscriptionCrudController::class, 'Subscriptions', 'fas fa-sync'),
        ]);
        yield MenuItem::linkToRoute('Reconciliation', 'fas fa-balance-scale', 'admin_reconciliation');

        yield MenuItem::section('Safety & Ops');
        yield MenuItem::linkTo(DriverAlertCrudController::class, 'Alerts', 'fas fa-triangle-exclamation')
            ->setBadge($this->statsService->countOpenAlerts(), 'danger');
        yield MenuItem::linkTo(AttendanceCrudController::class, 'Attendance', 'fas fa-clipboard-check');
        yield MenuItem::linkTo(AbsenceCrudController::class, 'Absences', 'fas fa-user-slash');

        yield MenuItem::section('System');
        yield MenuItem::subMenu('Notifications', 'fas fa-bell')->setSubItems([
            MenuItem::linkTo(PushDeviceCrudController::class, 'Push Devices', 'fas fa-mobile'),
            MenuItem::linkTo(PushTicketCrudController::class, 'Push Tickets', 'fas fa-ticket'),
            MenuItem::linkTo(NotificationPreferenceCrudController::class, 'Preferences', 'fas fa-sliders'),
        ]);
        yield MenuItem::linkTo(LocationUpdateCrudController::class, 'Location Updates', 'fas fa-map-pin');
        yield MenuItem::linkTo(AuditLogCrudController::class, 'Audit Log', 'fas fa-shield-halved');
    }

    #[Override]
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        /** @var User $user */
        return parent::configureUserMenu($user)
            ->setName($user->getFullName())
            ->setGravatarEmail((string) $user->getEmail());
    }
}
