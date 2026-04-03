<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<Route> */
class RouteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Route::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Route')
            ->setEntityLabelInPlural('Routes')
            ->setDefaultSort([
                'name' => 'ASC',
            ])
            ->setSearchFields(['name'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Name');

        yield ChoiceField::new('type', 'Type')
            ->setChoices([
                'Morning' => 'morning',
                'Afternoon' => 'afternoon',
            ])
            ->allowMultipleChoices(false)
            ->setRequired(true);

        yield AssociationField::new('driver', 'Driver')
            ->autocomplete()
            ->setRequired(false);

        yield AssociationField::new('school', 'School')
            ->autocomplete()
            ->hideOnIndex();

        yield IntegerField::new('estimatedDuration', 'Duration (s)')
            ->setRequired(false)
            ->hideOnIndex();

        yield IntegerField::new('estimatedDistance', 'Distance (m)')
            ->setRequired(false)
            ->hideOnIndex();

        yield BooleanField::new('isActive', 'Active');
        yield BooleanField::new('isTemplate', 'Template')
            ->hideOnIndex();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')->setChoices([
                'Morning' => 'morning',
                'Afternoon' => 'afternoon',
            ]))
            ->add(EntityFilter::new('driver'))
            ->add(EntityFilter::new('school'))
            ->add(BooleanFilter::new('isActive'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
