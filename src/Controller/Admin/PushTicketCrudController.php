<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\JsonPreviewField;
use App\EasyAdmin\Field\StatusBadgeField;
use App\Entity\PushTicket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Override;

/** @extends AbstractCrudController<PushTicket> */
class PushTicketCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PushTicket::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Push Ticket')
            ->setEntityLabelInPlural('Push Tickets')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['ticketId', 'expoPushToken', 'notificationType'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('ticketId', 'Ticket ID')
            ->hideOnIndex();

        yield TextField::new('expoPushToken', 'Expo Token')
            ->hideOnIndex();

        yield TextField::new('notificationType', 'Type');

        yield StatusBadgeField::new('status', 'Status')
            ->setStatusMap([
                'ok' => 'success',
                'error' => 'danger',
                'pending' => 'warning',
            ]);

        yield JsonPreviewField::new('errorDetails', 'Error Details')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Created');

        yield DateTimeField::new('checkedAt', 'Checked')
            ->hideOnIndex();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                'OK' => 'ok',
                'Error' => 'error',
                'Pending' => 'pending',
            ]))
            ->add(ChoiceFilter::new('notificationType')->setChoices([
                'Trips' => 'trips',
                'Payments' => 'payments',
                'Messages' => 'messages',
                'Reminders' => 'reminders',
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Created'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
