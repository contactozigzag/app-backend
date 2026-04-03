<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Absence;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<Absence> */
class AbsenceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Absence::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Absence')
            ->setEntityLabelInPlural('Absences')
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

        yield DateField::new('date', 'Date');

        yield ChoiceField::new('type', 'Type')
            ->setChoices([
                'Morning' => 'morning',
                'Afternoon' => 'afternoon',
                'Full Day' => 'full_day',
            ])
            ->allowMultipleChoices(false);

        yield ChoiceField::new('reason', 'Reason')
            ->setChoices([
                'Sick' => 'sick',
                'Family Emergency' => 'family_emergency',
                'Vacation' => 'vacation',
                'Other' => 'other',
            ])
            ->allowMultipleChoices(false);

        yield TextareaField::new('notes', 'Notes')
            ->setRequired(false)
            ->hideOnIndex();

        yield AssociationField::new('reportedBy', 'Reported By')
            ->hideOnIndex()
            ->hideOnForm();

        yield BooleanField::new('routeRecalculated', 'Recalculated')
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
            ->add(DateTimeFilter::new('date', 'Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
