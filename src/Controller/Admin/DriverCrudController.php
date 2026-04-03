<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Driver;
use App\Entity\School;
use App\Entity\User;
use App\Enum\PricingModel;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

/** @extends AbstractCrudController<Driver> */
class DriverCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Driver::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Driver')
            ->setEntityLabelInPlural('Drivers')
            ->setDefaultSort([
                'nickname' => 'ASC',
            ])
            ->setSearchFields(['nickname', 'licenseNumber', 'user.firstName', 'user.lastName'])
            ->overrideTemplate('crud/detail', 'admin/crud/driver/detail.html.twig');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('nickname', 'Alias');

        yield AssociationField::new('user', 'User')
            ->autocomplete()
            ->setFormTypeOption('query_builder', fn ($repo) => $repo->createQueryBuilder('u')
                ->andWhere("CAST_TEXT(u.roles) LIKE :role")
                ->setParameter('role', '%ROLE_DRIVER%')
                ->orderBy('u.lastName', 'ASC'));

        yield TextField::new('licenseNumber', 'License No.')
            ->setRequired(false);

        yield ChoiceField::new('pricingModel', 'Pricing Model')
            ->setChoices([
                'Flat' => PricingModel::FLAT->value,
                'Per Route' => PricingModel::PER_ROUTE->value,
                'Per Student' => PricingModel::PER_STUDENT->value,
                'Per Route + Student' => PricingModel::PER_ROUTE_STUDENT->value,
            ])
            ->allowMultipleChoices(false)
            ->setRequired(false)
            ->hideOnIndex();

        yield BooleanField::new('mpConnected', 'MP Connected')
            ->hideOnForm()
            ->setDisabled();

        yield DateTimeField::new('mpTokenExpiresAt', 'MP Token Expires')
            ->hideOnIndex()
            ->hideOnForm();

        yield AssociationField::new('vehicles', 'Vehicles')
            ->hideOnIndex()
            ->hideOnForm();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('pricingModel')->setChoices([
                'Flat' => PricingModel::FLAT->value,
                'Per Route' => PricingModel::PER_ROUTE->value,
                'Per Student' => PricingModel::PER_STUDENT->value,
                'Per Route + Student' => PricingModel::PER_ROUTE_STUDENT->value,
            ]));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::DELETE);
    }

    #[Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Always join user to avoid N+1 on user.firstName / user.lastName display
        $rootAlias = $qb->getRootAliases()[0];
        $qb->leftJoin(sprintf('%s.user', $rootAlias), 'driverUser')
            ->addSelect('driverUser');

        // For non-super-admins, scope to drivers whose user belongs to the admin's school
        $currentUser = $this->security->getUser();
        if (
            $currentUser instanceof User
            && ! $this->security->isGranted('ROLE_SUPER_ADMIN')
            && $currentUser->getSchool() instanceof School
        ) {
            $qb->andWhere('driverUser.school = :adminSchool')
                ->setParameter('adminSchool', $currentUser->getSchool());
        }

        return $qb;
    }
}
