<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationPreference;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<NotificationPreference> */
class NotificationPreferenceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationPreference::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Notification Preference')
            ->setEntityLabelInPlural('Notification Preferences')
            ->setSearchFields(['user.firstName', 'user.lastName', 'user.email'])
            ->setDefaultSort([
                'id' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('user', 'User');

        yield BooleanField::new('emailEnabled', 'Email');

        yield BooleanField::new('smsEnabled', 'SMS');

        yield BooleanField::new('pushEnabled', 'Push');

        yield BooleanField::new('notifyOnArriving', 'On Arriving')
            ->hideOnIndex();

        yield BooleanField::new('notifyOnPickup', 'On Pickup')
            ->hideOnIndex();

        yield BooleanField::new('notifyOnDropoff', 'On Dropoff')
            ->hideOnIndex();

        yield BooleanField::new('notifyOnRouteStart', 'On Route Start')
            ->hideOnIndex();

        yield BooleanField::new('notifyOnDelay', 'On Delay')
            ->hideOnIndex();

        yield BooleanField::new('notifyOnCancellation', 'On Cancellation')
            ->hideOnIndex();

        yield IntegerField::new('arrivalNotificationMinutes', 'Arrival Notice (min)')
            ->hideOnIndex();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user'))
            ->add(BooleanFilter::new('emailEnabled', 'Email'))
            ->add(BooleanFilter::new('smsEnabled', 'SMS'))
            ->add(BooleanFilter::new('pushEnabled', 'Push'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
