<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PushDevice;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/** @extends AbstractCrudController<PushDevice> */
class PushDeviceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PushDevice::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Push Device')
            ->setEntityLabelInPlural('Push Devices')
            ->setDefaultSort([
                'lastSeenAt' => 'DESC',
            ])
            ->setSearchFields(['expoPushToken', 'platform', 'deviceName'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('user', 'User');

        yield TextField::new('expoPushToken', 'Expo Token')
            ->hideOnIndex();

        yield TextField::new('platform', 'Platform');

        yield TextField::new('deviceName', 'Device')
            ->hideOnIndex();

        yield TextField::new('osVersion', 'OS Version')
            ->hideOnIndex();

        yield BooleanField::new('isActive', 'Active')
            ->renderAsSwitch(false);

        yield DateTimeField::new('lastSeenAt', 'Last Seen');

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnIndex();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user'))
            ->add(BooleanFilter::new('isActive', 'Active'))
            ->add(TextFilter::new('platform'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $deactivate = Action::new('deactivate', 'Deactivate', 'fas fa-ban')
            ->linkToRoute('admin_push_device_deactivate', fn (PushDevice $d): array => [
                'id' => $d->getId(),
            ])
            ->displayIf(fn (PushDevice $d): bool => $d->isActive())
            ->addCssClass('btn btn-sm btn-danger');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $deactivate)
            ->add(Crud::PAGE_DETAIL, $deactivate);
    }

    #[Route('/admin/push-device/{id}/deactivate', name: 'admin_push_device_deactivate', priority: 1)]
    public function deactivate(int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var PushDevice|null $device */
        $device = $this->entityManager->getRepository(PushDevice::class)->find($id);

        if ($device !== null && $device->isActive()) {
            $device->deactivate();
            $this->entityManager->flush();
            $this->addFlash('success', 'Push device deactivated.');
        }

        return $this->redirectToRoute('admin', [
            'crudControllerFqcn' => self::class,
            'crudAction' => 'index',
        ]);
    }
}
