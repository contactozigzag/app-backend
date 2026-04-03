<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\JsonPreviewField;
use App\EasyAdmin\Field\StatusBadgeField;
use App\Entity\PaymentTransaction;
use App\Enum\PaymentStatus;
use App\Enum\TransactionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

/** @extends AbstractCrudController<PaymentTransaction> */
class PaymentTransactionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PaymentTransaction::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Transaction')
            ->setEntityLabelInPlural('Transactions')
            ->setSearchFields(['payment.paymentProviderId', 'payment.description'])
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('payment', 'Payment');

        yield ChoiceField::new('eventType', 'Event')
            ->setChoices([
                'Created' => TransactionEvent::CREATED,
                'Approved' => TransactionEvent::APPROVED,
                'Rejected' => TransactionEvent::REJECTED,
                'Refunded' => TransactionEvent::REFUNDED,
                'Cancelled' => TransactionEvent::CANCELLED,
                'Webhook Received' => TransactionEvent::WEBHOOK_RECEIVED,
                'Status Updated' => TransactionEvent::STATUS_UPDATED,
            ])
            ->allowMultipleChoices(false);

        yield StatusBadgeField::new('status', 'Status')
            ->setStatusMap([
                PaymentStatus::PENDING->value => 'warning',
                PaymentStatus::PROCESSING->value => 'info',
                PaymentStatus::APPROVED->value => 'success',
                PaymentStatus::REJECTED->value => 'danger',
                PaymentStatus::CANCELLED->value => 'secondary',
                PaymentStatus::REFUNDED->value => 'dark',
                PaymentStatus::PARTIALLY_REFUNDED->value => 'secondary',
            ]);

        yield JsonPreviewField::new('providerResponse', 'Provider Response')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('payment'))
            ->add(ChoiceFilter::new('eventType', 'Event')->setChoices([
                'Created' => TransactionEvent::CREATED->value,
                'Approved' => TransactionEvent::APPROVED->value,
                'Rejected' => TransactionEvent::REJECTED->value,
                'Refunded' => TransactionEvent::REFUNDED->value,
                'Cancelled' => TransactionEvent::CANCELLED->value,
                'Webhook Received' => TransactionEvent::WEBHOOK_RECEIVED->value,
                'Status Updated' => TransactionEvent::STATUS_UPDATED->value,
            ]))
            ->add(ChoiceFilter::new('status')->setChoices([
                'Pending' => PaymentStatus::PENDING->value,
                'Approved' => PaymentStatus::APPROVED->value,
                'Rejected' => PaymentStatus::REJECTED->value,
                'Cancelled' => PaymentStatus::CANCELLED->value,
                'Refunded' => PaymentStatus::REFUNDED->value,
            ]))
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
