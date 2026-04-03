<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\JsonPreviewField;
use App\Entity\ArchivedRoute;
use App\Service\Admin\CsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/** @extends AbstractCrudController<ArchivedRoute> */
class ArchivedRouteCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CsvExporter $csvExporter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ArchivedRoute::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Archived Route')
            ->setEntityLabelInPlural('Archived Routes')
            ->setDefaultSort([
                'archivedAt' => 'DESC',
            ])
            ->setSearchFields(['routeName', 'driverName'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('routeName', 'Route');

        yield TextField::new('driverName', 'Driver');

        yield AssociationField::new('school', 'School');

        yield DateField::new('date', 'Date');

        yield TextField::new('status', 'Status');

        yield DateTimeField::new('completedAt', 'Completed')
            ->hideOnIndex();

        yield DateTimeField::new('archivedAt', 'Archived');

        yield IntegerField::new('totalStops', 'Total Stops')
            ->hideOnIndex();

        yield IntegerField::new('completedStops', 'Completed Stops')
            ->hideOnIndex();

        yield IntegerField::new('studentsPickedUp', 'Picked Up')
            ->hideOnIndex();

        yield IntegerField::new('studentsDroppedOff', 'Dropped Off')
            ->hideOnIndex();

        yield IntegerField::new('noShows', 'No Shows')
            ->hideOnIndex();

        yield TextField::new('onTimePercentage', 'On-Time %')
            ->hideOnIndex();

        yield JsonPreviewField::new('stopData', 'Stop Data')
            ->hideOnIndex();

        yield JsonPreviewField::new('performanceMetrics', 'Performance Metrics')
            ->hideOnIndex();

        yield TextField::new('notes', 'Notes')
            ->hideOnIndex();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('school'))
            ->add(TextFilter::new('status'))
            ->add(DateTimeFilter::new('archivedAt', 'Archived At'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $exportCsv = Action::new('exportCsv', 'Export CSV', 'fas fa-download')
            ->linkToRoute('admin_archived_route_export_csv')
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportCsv);
    }

    #[Route('/admin/archived-route/export-csv', name: 'admin_archived_route_export_csv', priority: 1)]
    public function exportCsv(): StreamedResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $rows = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(ArchivedRoute::class, 'a')
            ->orderBy('a.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $headers = [
            'ID', 'Route', 'Driver', 'School', 'Date', 'Status',
            'Total Stops', 'Completed', 'Students Picked Up', 'Students Dropped Off',
            'No Shows', 'On-Time %', 'Archived At',
        ];

        $data = array_map(static fn (ArchivedRoute $row): array => [
            $row->getId(),
            $row->getRouteName(),
            $row->getDriverName(),
            (string) $row->getSchool(),
            $row->getDate()?->format('Y-m-d') ?? '',
            $row->getStatus(),
            $row->getTotalStops(),
            $row->getCompletedStops(),
            $row->getStudentsPickedUp(),
            $row->getStudentsDroppedOff(),
            $row->getNoShows(),
            $row->getOnTimePercentage(),
            $row->getArchivedAt()->format('Y-m-d H:i:s'),
        ], $rows);

        return $this->csvExporter->export('archived-routes.csv', $headers, $data);
    }
}
