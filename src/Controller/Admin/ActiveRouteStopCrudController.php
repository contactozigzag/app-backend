<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ActiveRouteStop;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Override;

/** @extends AbstractCrudController<ActiveRouteStop> */
class ActiveRouteStopCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ActiveRouteStop::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Active Route Stop')
            ->setEntityLabelInPlural('Active Route Stops')
            ->setSearchFields(['student.firstName', 'student.lastName'])
            ->setDefaultSort([
                'stopOrder' => 'ASC',
            ])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('activeRoute', 'Session');

        yield AssociationField::new('student', 'Student');

        yield IntegerField::new('stopOrder', 'Order');

        yield ChoiceField::new('status', 'Status')
            ->setChoices([
                'Pending' => 'pending',
                'Approaching' => 'approaching',
                'Arrived' => 'arrived',
                'Picked Up' => 'picked_up',
                'Dropped Off' => 'dropped_off',
                'Skipped' => 'skipped',
                'Absent' => 'absent',
            ])
            ->allowMultipleChoices(false);

        yield DateTimeField::new('arrivedAt', 'Arrived')
            ->hideOnForm();

        yield DateTimeField::new('pickedUpAt', 'Picked Up')
            ->hideOnForm();

        yield DateTimeField::new('droppedOffAt', 'Dropped Off')
            ->hideOnForm();
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
