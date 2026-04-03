<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\LocationUpdate;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<LocationUpdate> */
class LocationUpdateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LocationUpdate::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Location Update')
            ->setEntityLabelInPlural('Location Updates')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['driver.user.firstName', 'driver.user.lastName'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('driver', 'Driver');

        yield AssociationField::new('activeRoute', 'Session')
            ->hideOnIndex();

        yield TextField::new('latitude', 'Lat');
        yield TextField::new('longitude', 'Lng');

        yield TextField::new('speed', 'Speed (km/h)')
            ->hideOnIndex();

        yield TextField::new('heading', 'Heading (°)')
            ->hideOnIndex();

        yield TextField::new('accuracy', 'Accuracy (m)')
            ->hideOnIndex();

        yield DateTimeField::new('timestamp', 'Recorded At');

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('driver'))
            ->add(DateTimeFilter::new('createdAt', 'Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
