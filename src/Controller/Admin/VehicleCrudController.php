<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\School;
use App\Entity\User;
use App\Entity\Vehicle;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

/** @extends AbstractCrudController<Vehicle> */
class VehicleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Vehicle::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Vehicle')
            ->setEntityLabelInPlural('Vehicles')
            ->setDefaultSort([
                'licensePlate' => 'ASC',
            ])
            ->setSearchFields(['licensePlate', 'make', 'model']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('licensePlate', 'License Plate');
        yield TextField::new('make', 'Make');
        yield TextField::new('model', 'Model');
        yield IntegerField::new('year', 'Year')->setRequired(false);
        yield IntegerField::new('capacity', 'Capacity');
        yield TextField::new('color', 'Color')->setRequired(false);

        yield ChoiceField::new('type', 'Type')
            ->setChoices([
                'Bus' => 'bus',
                'Minibus' => 'minibus',
                'Van' => 'van',
                'Car' => 'car',
            ])
            ->setRequired(false);

        yield AssociationField::new('driver', 'Driver')
            ->autocomplete()
            ->setRequired(true);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(NumericFilter::new('capacity', 'Capacity'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        // DETAIL is not on INDEX by default; EDIT and DELETE are already on DETAIL
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    #[Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Join driver and driver.user to avoid N+1 on driver display
        $rootAlias = $qb->getRootAliases()[0];
        $qb->leftJoin(sprintf('%s.driver', $rootAlias), 'vehicleDriver')
            ->addSelect('vehicleDriver')
            ->leftJoin('vehicleDriver.user', 'vehicleDriverUser')
            ->addSelect('vehicleDriverUser');

        // For non-super-admins, scope to vehicles whose driver belongs to the admin's school
        $currentUser = $this->security->getUser();
        if (
            $currentUser instanceof User
            && ! $this->security->isGranted('ROLE_SUPER_ADMIN')
            && $currentUser->getSchool() instanceof School
        ) {
            $qb->andWhere('vehicleDriverUser.school = :adminSchool')
                ->setParameter('adminSchool', $currentUser->getSchool());
        }

        return $qb;
    }
}
