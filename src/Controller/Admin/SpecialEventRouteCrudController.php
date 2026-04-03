<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\StatusBadgeField;
use App\Entity\SpecialEventRoute;
use App\Enum\DepartureMode;
use App\Enum\EventType;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/** @extends AbstractCrudController<SpecialEventRoute> */
class SpecialEventRouteCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SpecialEventRoute::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Special Event Route')
            ->setEntityLabelInPlural('Special Event Routes')
            ->setDefaultSort([
                'eventDate' => 'DESC',
            ])
            ->setSearchFields(['name'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('name', 'Name');

        yield AssociationField::new('school', 'School');

        yield ChoiceField::new('eventType', 'Event Type')
            ->setChoices([
                'Field Trip' => EventType::FIELD_TRIP->value,
                'Sports Event' => EventType::SPORTS_EVENT->value,
                'Museum Visit' => EventType::MUSEUM_VISIT->value,
                'Other' => EventType::OTHER->value,
            ])
            ->hideOnForm();

        yield ChoiceField::new('routeMode', 'Route Mode')
            ->setChoices([
                'Full Day Trip' => RouteMode::FULL_DAY_TRIP->value,
                'Return to School' => RouteMode::RETURN_TO_SCHOOL->value,
                'One Way' => RouteMode::ONE_WAY->value,
            ])
            ->hideOnForm();

        yield ChoiceField::new('departureMode', 'Departure Mode')
            ->setChoices([
                'Grouped' => DepartureMode::GROUPED->value,
                'Individual' => DepartureMode::INDIVIDUAL->value,
            ])
            ->hideOnIndex()
            ->hideOnForm();

        yield DateField::new('eventDate', 'Event Date');

        yield StatusBadgeField::new('status', 'Status')
            ->setStatusMap([
                SpecialEventRouteStatus::DRAFT->value => 'secondary',
                SpecialEventRouteStatus::PUBLISHED->value => 'primary',
                SpecialEventRouteStatus::IN_PROGRESS->value => 'warning',
                SpecialEventRouteStatus::COMPLETED->value => 'success',
                SpecialEventRouteStatus::CANCELLED->value => 'danger',
            ]);

        yield AssociationField::new('assignedDriver', 'Driver')
            ->hideOnIndex();

        yield AssociationField::new('assignedVehicle', 'Vehicle')
            ->hideOnIndex();

        yield DateTimeField::new('outboundDepartureTime', 'Outbound Departure')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('returnDepartureTime', 'Return Departure')
            ->hideOnIndex()
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Created')
            ->hideOnIndex()
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('school'))
            ->add(ChoiceFilter::new('status')->setChoices([
                'Draft' => SpecialEventRouteStatus::DRAFT->value,
                'Published' => SpecialEventRouteStatus::PUBLISHED->value,
                'In Progress' => SpecialEventRouteStatus::IN_PROGRESS->value,
                'Completed' => SpecialEventRouteStatus::COMPLETED->value,
                'Cancelled' => SpecialEventRouteStatus::CANCELLED->value,
            ]))
            ->add(ChoiceFilter::new('eventType')->setChoices([
                'Field Trip' => EventType::FIELD_TRIP->value,
                'Sports Event' => EventType::SPORTS_EVENT->value,
                'Museum Visit' => EventType::MUSEUM_VISIT->value,
                'Other' => EventType::OTHER->value,
            ]))
            ->add(ChoiceFilter::new('routeMode')->setChoices([
                'Full Day Trip' => RouteMode::FULL_DAY_TRIP->value,
                'Return to School' => RouteMode::RETURN_TO_SCHOOL->value,
                'One Way' => RouteMode::ONE_WAY->value,
            ]))
            ->add(DateTimeFilter::new('eventDate', 'Event Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $publish = Action::new('publish', 'Publish', 'fas fa-check-circle')
            ->linkToRoute('admin_special_event_route_publish', fn (SpecialEventRoute $r): array => [
                'id' => $r->getId(),
            ])
            ->displayIf(fn (SpecialEventRoute $r): bool => $r->getStatus() === SpecialEventRouteStatus::DRAFT)
            ->addCssClass('btn btn-sm btn-success');

        $cancel = Action::new('cancel', 'Cancel', 'fas fa-times-circle')
            ->linkToRoute('admin_special_event_route_cancel', fn (SpecialEventRoute $r): array => [
                'id' => $r->getId(),
            ])
            ->displayIf(fn (SpecialEventRoute $r): bool => in_array(
                $r->getStatus(),
                [SpecialEventRouteStatus::DRAFT, SpecialEventRouteStatus::PUBLISHED],
                true
            ))
            ->addCssClass('btn btn-sm btn-danger');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $publish)
            ->add(Crud::PAGE_INDEX, $cancel)
            ->add(Crud::PAGE_DETAIL, $publish)
            ->add(Crud::PAGE_DETAIL, $cancel);
    }

    #[Route('/admin/special-event-route/{id}/publish', name: 'admin_special_event_route_publish', priority: 1)]
    public function publish(int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var SpecialEventRoute|null $route */
        $route = $this->entityManager->getRepository(SpecialEventRoute::class)->find($id);

        if ($route !== null && $route->getStatus() === SpecialEventRouteStatus::DRAFT) {
            $route->setStatus(SpecialEventRouteStatus::PUBLISHED);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Route "%s" published.', $route->getName()));
        }

        return $this->redirectToRoute('admin', [
            'crudControllerFqcn' => self::class,
            'crudAction' => 'index',
        ]);
    }

    #[Route('/admin/special-event-route/{id}/cancel', name: 'admin_special_event_route_cancel', priority: 1)]
    public function cancel(int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var SpecialEventRoute|null $route */
        $route = $this->entityManager->getRepository(SpecialEventRoute::class)->find($id);

        if ($route !== null && in_array(
            $route->getStatus(),
            [SpecialEventRouteStatus::DRAFT, SpecialEventRouteStatus::PUBLISHED],
            true
        )) {
            $route->setStatus(SpecialEventRouteStatus::CANCELLED);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Route "%s" cancelled.', $route->getName()));
        }

        return $this->redirectToRoute('admin', [
            'crudControllerFqcn' => self::class,
            'crudAction' => 'index',
        ]);
    }
}
