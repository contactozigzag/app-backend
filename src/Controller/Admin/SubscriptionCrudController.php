<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\StatusBadgeField;
use App\Entity\Subscription;
use App\Enum\BillingCycle;
use App\Enum\SubscriptionStatus;
use Doctrine\ORM\EntityManagerInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/** @extends AbstractCrudController<Subscription> */
class SubscriptionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Subscription::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Subscription')
            ->setEntityLabelInPlural('Subscriptions')
            ->setSearchFields(['user.email', 'user.firstName', 'user.lastName', 'planType'])
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('user', 'User');

        yield AssociationField::new('driver', 'Driver');

        yield TextField::new('planType', 'Plan');

        yield StatusBadgeField::new('status', 'Status')
            ->setStatusMap([
                SubscriptionStatus::ACTIVE->value => 'success',
                SubscriptionStatus::PAUSED->value => 'warning',
                SubscriptionStatus::CANCELLED->value => 'secondary',
                SubscriptionStatus::EXPIRED->value => 'dark',
                SubscriptionStatus::PAYMENT_FAILED->value => 'danger',
            ]);

        yield TextField::new('amount', 'Amount');

        yield ChoiceField::new('billingCycle', 'Cycle')
            ->setChoices([
                'Weekly' => BillingCycle::WEEKLY,
                'Monthly' => BillingCycle::MONTHLY,
                'Quarterly' => BillingCycle::QUARTERLY,
                'Yearly' => BillingCycle::YEARLY,
            ])
            ->allowMultipleChoices(false)
            ->hideOnIndex();

        yield DateTimeField::new('nextBillingDate', 'Next Billing')
            ->hideOnIndex();

        yield DateTimeField::new('cancelledAt', 'Cancelled At')
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
                'Active' => SubscriptionStatus::ACTIVE->value,
                'Paused' => SubscriptionStatus::PAUSED->value,
                'Cancelled' => SubscriptionStatus::CANCELLED->value,
                'Expired' => SubscriptionStatus::EXPIRED->value,
                'Payment Failed' => SubscriptionStatus::PAYMENT_FAILED->value,
            ]))
            ->add(EntityFilter::new('driver'))
            ->add(DateTimeFilter::new('createdAt', 'Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $cancel = Action::new('cancelSubscription', 'Cancel', 'fas fa-ban')
            ->linkToRoute('admin_subscription_cancel', fn (Subscription $s): array => [
                'id' => $s->getId(),
            ])
            ->addCssClass('btn btn-sm btn-warning')
            ->displayIf(fn (Subscription $s): bool => $s->getStatus() === SubscriptionStatus::ACTIVE);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $cancel);
    }

    #[Route('/admin/subscription/{id}/cancel', name: 'admin_subscription_cancel', priority: 1)]
    public function cancel(int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var Subscription|null $subscription */
        $subscription = $this->entityManager->getRepository(Subscription::class)->find($id);

        if ($subscription === null) {
            $this->addFlash('danger', 'Subscription not found.');
            return $this->redirectToRoute('admin_subscription_index');
        }

        $subscription->setStatus(SubscriptionStatus::CANCELLED);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Subscription #%d cancelled.', $id));

        return $this->redirectToRoute('admin_subscription_detail', [
            'entityId' => $id,
        ]);
    }
}
