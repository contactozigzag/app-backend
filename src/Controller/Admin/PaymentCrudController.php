<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\JsonPreviewField;
use App\EasyAdmin\Field\StatusBadgeField;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Service\Payment\MercadoPagoService;
use App\Service\Payment\PaymentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Override;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/** @extends AbstractCrudController<Payment> */
class PaymentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Payment')
            ->setEntityLabelInPlural('Payments')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['user.email', 'user.firstName', 'user.lastName', 'paymentProviderId'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('user', 'Parent');

        yield AssociationField::new('driver', 'Driver');

        yield TextField::new('amount', 'Amount');

        yield TextField::new('currency', 'Currency')
            ->hideOnDetail();

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

        yield TextField::new('description', 'Description')
            ->hideOnIndex();

        yield TextField::new('preferenceId', 'Preference ID')
            ->hideOnIndex();

        yield TextField::new('paymentProviderId', 'Provider ID')
            ->hideOnIndex();

        yield TextField::new('idempotencyKey', 'Idempotency Key')
            ->hideOnIndex();

        yield TextField::new('refundedAmount', 'Refunded')
            ->hideOnIndex();

        yield JsonPreviewField::new('rateSnapshot', 'Rate Snapshot')
            ->hideOnIndex();

        yield AssociationField::new('transactions', 'Transactions')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('paidAt', 'Paid At')
            ->hideOnIndex();

        yield DateTimeField::new('expiresAt', 'Expires At')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm();

        yield DateTimeField::new('updatedAt', 'Updated')
            ->hideOnIndex()
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                'Pending' => PaymentStatus::PENDING->value,
                'Processing' => PaymentStatus::PROCESSING->value,
                'Approved' => PaymentStatus::APPROVED->value,
                'Rejected' => PaymentStatus::REJECTED->value,
                'Cancelled' => PaymentStatus::CANCELLED->value,
                'Refunded' => PaymentStatus::REFUNDED->value,
                'Partially Refunded' => PaymentStatus::PARTIALLY_REFUNDED->value,
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Date'))
            ->add(NumericFilter::new('amount', 'Amount'))
            ->add(EntityFilter::new('driver'));

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $filters->add(EntityFilter::new('user', 'Parent'));
        }

        return $filters;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $refund = Action::new('adminRefund', 'Refund', 'fas fa-undo')
            ->linkToRoute('admin_payment_refund', fn (Payment $p): array => [
                'id' => $p->getId(),
            ])
            ->addCssClass('btn btn-sm btn-warning')
            ->displayIf(fn (Payment $p): bool => $p->getStatus() === PaymentStatus::APPROVED);

        $sync = Action::new('adminSync', 'Sync from MP', 'fas fa-sync')
            ->linkToRoute('admin_payment_sync', fn (Payment $p): array => [
                'id' => $p->getId(),
            ])
            ->addCssClass('btn btn-sm btn-secondary')
            ->displayIf(fn (Payment $p): bool => in_array($p->getStatus(), [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true));

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $refund)
            ->add(Crud::PAGE_DETAIL, $sync);
    }

    #[Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $rootAlias = $qb->getRootAliases()[0];
        $qb->leftJoin(sprintf('%s.user', $rootAlias), 'paymentUser')
            ->addSelect('paymentUser')
            ->leftJoin(sprintf('%s.driver', $rootAlias), 'paymentDriver')
            ->addSelect('paymentDriver')
            ->leftJoin('paymentDriver.user', 'paymentDriverUser')
            ->addSelect('paymentDriverUser');

        return $qb;
    }

    #[Route('/admin/payment/{id}/refund', name: 'admin_payment_refund', priority: 1)]
    public function refund(int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var Payment|null $payment */
        $payment = $this->entityManager->getRepository(Payment::class)->find($id);

        if ($payment === null) {
            $this->addFlash('danger', 'Payment not found.');
            return $this->redirectToRoute('admin_payment_index');
        }

        try {
            $providerId = $payment->getPaymentProviderId();
            if ($providerId === null) {
                throw new RuntimeException('No provider payment ID to refund.');
            }

            $this->mercadoPagoService->refundPayment($providerId);
            $payment->setStatus(PaymentStatus::REFUNDED);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Payment #%d refunded successfully.', $id));
        } catch (Throwable $throwable) {
            $this->addFlash('danger', sprintf('Refund failed: %s', $throwable->getMessage()));
        }

        return $this->redirectToRoute('admin_payment_detail', [
            'entityId' => $id,
        ]);
    }

    #[Route('/admin/payment/{id}/sync', name: 'admin_payment_sync', priority: 1)]
    public function sync(int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var Payment|null $payment */
        $payment = $this->entityManager->getRepository(Payment::class)->find($id);

        if ($payment === null) {
            $this->addFlash('danger', 'Payment not found.');
            return $this->redirectToRoute('admin_payment_index');
        }

        try {
            $this->paymentProcessor->syncPaymentStatus($payment);
            $this->addFlash('success', sprintf('Payment #%d synced. New status: %s', $id, $payment->getStatus()->value));
        } catch (Throwable $throwable) {
            $this->addFlash('danger', sprintf('Sync failed: %s', $throwable->getMessage()));
        }

        return $this->redirectToRoute('admin_payment_detail', [
            'entityId' => $id,
        ]);
    }
}
