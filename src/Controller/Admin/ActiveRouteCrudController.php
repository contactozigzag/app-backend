<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ActiveRoute;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<ActiveRoute> */
class ActiveRouteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ActiveRoute::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Active Session')
            ->setEntityLabelInPlural('Active Sessions')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['routeTemplate.name', 'driver.user.firstName', 'driver.user.lastName'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('routeTemplate', 'Route');

        yield AssociationField::new('driver', 'Driver');

        yield DateField::new('date', 'Date');

        yield ChoiceField::new('status', 'Status')
            ->setChoices([
                'Scheduled' => 'scheduled',
                'In Progress' => 'in_progress',
                'Completed' => 'completed',
                'Cancelled' => 'cancelled',
            ])
            ->allowMultipleChoices(false);

        yield DateTimeField::new('startedAt', 'Started')
            ->hideOnForm();

        yield DateTimeField::new('completedAt', 'Completed')
            ->hideOnForm();

        yield TextField::new('currentLatitude', 'Lat')
            ->hideOnIndex()
            ->hideOnForm();

        yield TextField::new('currentLongitude', 'Lng')
            ->hideOnIndex()
            ->hideOnForm();

        yield IntegerField::new('totalDistance', 'Distance (m)')
            ->hideOnIndex()
            ->hideOnForm();

        yield IntegerField::new('totalDuration', 'Duration (s)')
            ->hideOnIndex()
            ->hideOnForm();

        yield AssociationField::new('stops', 'Stops')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                'Scheduled' => 'scheduled',
                'In Progress' => 'in_progress',
                'Completed' => 'completed',
                'Cancelled' => 'cancelled',
            ]))
            ->add(EntityFilter::new('driver'))
            ->add(EntityFilter::new('routeTemplate', 'Route'))
            ->add(DateTimeFilter::new('createdAt', 'Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        // Read-only: index shows detail only; detail shows back to list only
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
