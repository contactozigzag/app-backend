<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RouteStop;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<RouteStop> */
class RouteStopCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RouteStop::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Route Stop')
            ->setEntityLabelInPlural('Route Stops')
            ->setDefaultSort([
                'stopOrder' => 'ASC',
            ])
            ->setSearchFields(['route.name', 'student.firstName', 'student.lastName'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('route', 'Route')
            ->autocomplete();

        yield AssociationField::new('student', 'Student')
            ->autocomplete();

        yield IntegerField::new('stopOrder', 'Order');

        yield BooleanField::new('isConfirmed', 'Confirmed')
            ->hideOnForm()
            ->setDisabled();

        yield BooleanField::new('isActive', 'Active');

        yield IntegerField::new('estimatedArrivalTime', 'ETA (s)')
            ->setRequired(false)
            ->hideOnIndex();

        yield IntegerField::new('geofenceRadius', 'Geofence (m)')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextareaField::new('notes', 'Notes')
            ->setRequired(false)
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('route'))
            ->add(BooleanFilter::new('isConfirmed'))
            ->add(BooleanFilter::new('isActive'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
