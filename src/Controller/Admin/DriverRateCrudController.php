<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DriverRate;
use App\Enum\PricingModel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<DriverRate> */
class DriverRateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DriverRate::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Driver Rate')
            ->setEntityLabelInPlural('Driver Rates')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('driver', 'Driver')
            ->autocomplete();

        yield ChoiceField::new('pricingModel', 'Model')
            ->setChoices([
                'Flat' => PricingModel::FLAT,
                'Per Route' => PricingModel::PER_ROUTE,
                'Per Student' => PricingModel::PER_STUDENT,
                'Per Route + Student' => PricingModel::PER_ROUTE_STUDENT,
            ])
            ->allowMultipleChoices(false);

        yield TextField::new('amount', 'Amount')
            ->setRequired(false);

        yield TextField::new('perStudentAmount', 'Per Student Amount')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('currency', 'Currency')
            ->hideOnIndex();

        yield AssociationField::new('route', 'Route')
            ->autocomplete()
            ->setRequired(false)
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('driver'))
            ->add(ChoiceFilter::new('pricingModel', 'Model')->setChoices([
                'Flat' => PricingModel::FLAT->value,
                'Per Route' => PricingModel::PER_ROUTE->value,
                'Per Student' => PricingModel::PER_STUDENT->value,
                'Per Route + Student' => PricingModel::PER_ROUTE_STUDENT->value,
            ]))
            ->add(EntityFilter::new('route'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
