<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\JsonPreviewField;
use App\EasyAdmin\Field\StatusBadgeField;
use App\Entity\DriverAlert;
use App\Enum\AlertStatus;
use App\Repository\ChatMessageRepository;
use App\Service\Payment\TokenEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/** @extends AbstractCrudController<DriverAlert> */
class DriverAlertCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly TokenEncryptor $tokenEncryptor,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DriverAlert::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Alert')
            ->setEntityLabelInPlural('Alerts')
            ->setDefaultSort([
                'triggeredAt' => 'DESC',
            ])
            ->setSearchFields(['distressedDriver.user.firstName', 'distressedDriver.user.lastName', 'alertId'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('alertId', 'Alert ID')
            ->hideOnIndex();

        yield AssociationField::new('distressedDriver', 'Driver');

        yield StatusBadgeField::new('status', 'Status')
            ->setStatusMap([
                AlertStatus::PENDING->value => 'danger',
                AlertStatus::RESPONDED->value => 'warning',
                AlertStatus::RESOLVED->value => 'success',
            ]);

        yield DateTimeField::new('triggeredAt', 'Triggered');

        yield DateTimeField::new('resolvedAt', 'Resolved')
            ->hideOnIndex();

        yield AssociationField::new('respondingDriver', 'Responding Driver')
            ->hideOnIndex();

        yield AssociationField::new('routeSession', 'Route Session')
            ->hideOnIndex();

        yield TextField::new('locationLat', 'Latitude')
            ->hideOnIndex()
            ->hideOnForm();

        yield TextField::new('locationLng', 'Longitude')
            ->hideOnIndex()
            ->hideOnForm();

        yield AssociationField::new('resolvedBy', 'Resolved By')
            ->hideOnIndex();

        yield JsonPreviewField::new('nearbyDriverIds', 'Nearby Driver IDs')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnForm()
            ->hideOnIndex();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                'Pending' => AlertStatus::PENDING->value,
                'Responded' => AlertStatus::RESPONDED->value,
                'Resolved' => AlertStatus::RESOLVED->value,
            ]))
            ->add(EntityFilter::new('distressedDriver', 'Driver'))
            ->add(DateTimeFilter::new('triggeredAt', 'Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $viewChat = Action::new('viewChat', 'Chat Messages', 'fas fa-comments')
            ->linkToRoute('admin_alert_chat', fn (DriverAlert $a): array => [
                'id' => $a->getId(),
            ])
            ->addCssClass('btn btn-sm btn-info');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $viewChat);
    }

    #[Route('/admin/alert/{id}/chat', name: 'admin_alert_chat', priority: 1)]
    public function chat(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var DriverAlert|null $alert */
        $alert = $this->entityManager->getRepository(DriverAlert::class)->find($id);

        if ($alert === null) {
            throw $this->createNotFoundException(sprintf('Alert #%d not found.', $id));
        }

        $rawMessages = $this->chatMessageRepository->findByAlert($alert);

        $messages = [];
        foreach ($rawMessages as $message) {
            $decrypted = '[encrypted]';
            try {
                $decrypted = $this->tokenEncryptor->decrypt($message->getContent());
            } catch (Throwable) {
                // content stays as placeholder if decryption fails
            }

            $messages[] = [
                'id' => $message->getId(),
                'sender' => $message->getSender(),
                'content' => $decrypted,
                'sentAt' => $message->getSentAt(),
                'readByCount' => count($message->getReadBy()),
            ];
        }

        return $this->render('admin/alert/chat.html.twig', [
            'alert' => $alert,
            'messages' => $messages,
        ]);
    }
}
