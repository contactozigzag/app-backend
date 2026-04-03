<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Attendance;
use App\Service\Admin\CsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/** @extends AbstractCrudController<Attendance> */
class AttendanceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CsvExporter $csvExporter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Attendance::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Attendance')
            ->setEntityLabelInPlural('Attendance Records')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['student.firstName', 'student.lastName'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('student', 'Student');

        yield AssociationField::new('activeRouteStop', 'Route Stop')
            ->hideOnIndex();

        yield DateField::new('date', 'Date');

        yield ChoiceField::new('status', 'Status')
            ->setChoices([
                'Picked Up' => 'picked_up',
                'Dropped Off' => 'dropped_off',
                'No Show' => 'no_show',
                'Cancelled' => 'cancelled',
            ])
            ->allowMultipleChoices(false);

        yield DateTimeField::new('pickedUpAt', 'Picked Up At')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('droppedOffAt', 'Dropped Off At')
            ->hideOnIndex()
            ->hideOnForm();

        yield TextField::new('pickupLatitude', 'Pickup Lat')
            ->hideOnIndex()
            ->hideOnForm();

        yield TextField::new('pickupLongitude', 'Pickup Lng')
            ->hideOnIndex()
            ->hideOnForm();

        yield AssociationField::new('recordedBy', 'Recorded By')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('student'))
            ->add(ChoiceFilter::new('status')->setChoices([
                'Picked Up' => 'picked_up',
                'Dropped Off' => 'dropped_off',
                'No Show' => 'no_show',
                'Cancelled' => 'cancelled',
            ]))
            ->add(DateTimeFilter::new('date', 'Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $exportCsv = Action::new('exportCsv', 'Export CSV', 'fas fa-download')
            ->linkToRoute('admin_attendance_export_csv')
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportCsv);
    }

    #[Route('/admin/attendance/export-csv', name: 'admin_attendance_export_csv', priority: 1)]
    public function exportCsv(): StreamedResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $rows = $this->entityManager->createQueryBuilder()
            ->select('a', 's', 'rb')
            ->from(Attendance::class, 'a')
            ->leftJoin('a.student', 's')
            ->leftJoin('a.recordedBy', 'rb')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $headers = ['ID', 'Student', 'Date', 'Status', 'Picked Up At', 'Dropped Off At', 'Recorded By', 'Created At'];

        $data = array_map(static fn (Attendance $a): array => [
            $a->getId(),
            trim(($a->getStudent()?->getFirstName() ?? '') . ' ' . ($a->getStudent()?->getLastName() ?? '')),
            $a->getDate()?->format('Y-m-d') ?? '',
            $a->getStatus(),
            $a->getPickedUpAt()?->format('Y-m-d H:i:s') ?? '',
            $a->getDroppedOffAt()?->format('Y-m-d H:i:s') ?? '',
            $a->getRecordedBy()?->getUser()?->getFullName() ?? '',
            $a->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $rows);

        return $this->csvExporter->export('attendance.csv', $headers, $data);
    }
}
