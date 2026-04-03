<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Trait\AddressFormTrait;
use App\Entity\School;
use App\Service\AddressGeocoder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;

/** @extends AbstractCrudController<School> */
class SchoolCrudController extends AbstractCrudController
{
    use AddressFormTrait;

    public function __construct(
        AddressGeocoder $addressGeocoder
    ) {
        $this->addressGeocoder = $addressGeocoder;
    }

    public static function getEntityFqcn(): string
    {
        return School::class;
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name')
                ->setRequired(true)
                ->setHelp('The name of the school'),

            FormField::addFieldset('Address Information'),

            ChoiceField::new('addressLanguage', 'Address Language')
                ->setChoices([
                    'English' => 'en',
                    'Spanish / Español' => 'es',
                ])
                ->setRequired(true)
                ->setFormTypeOption('mapped', false)
                ->onlyOnForms()
                ->setHelp('Select the language for address formatting. Spanish uses "Street Name + Number" format (e.g., "Avenida Benavídez 1632").'),

            TextField::new('addressInput', 'Address')
                ->setRequired(true)
                ->setHelp('Enter the full address. English: "123 Main St, City, State, ZIP" | Spanish: "Avenida Benavídez 1632, Ciudad, Provincia, CP"')
                ->setFormTypeOption('mapped', false)
                ->onlyOnForms()
                ->setFormTypeOption('attr', [
                    'placeholder' => 'e.g., 123 Main Street, New York, NY 10001 OR Avenida Benavídez 1632, Buenos Aires',
                    'data-controller' => 'google-address-autocomplete',
                    'data-google-address-autocomplete-api-key-value' => $_ENV['GOOGLE_MAPS_FRONTEND_API_KEY'] ?? '',
                ]),

            TextField::new('address.streetAddress', 'Street Address')
                ->hideOnForm(),
            TextField::new('address.city', 'City')
                ->hideOnForm(),
            TextField::new('address.state', 'State')
                ->hideOnForm(),
            TextField::new('address.country', 'Country')
                ->hideOnForm(),
            TextField::new('address.postalCode', 'Postal Code')
                ->hideOnForm(),
            TextField::new('address.latitude', 'Latitude')
                ->hideOnForm(),
            TextField::new('address.longitude', 'Longitude')
                ->hideOnForm(),
        ];
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('School')
            ->setEntityLabelInPlural('Schools')
            ->setPageTitle(Crud::PAGE_NEW, 'Create New School')
            ->setPageTitle(Crud::PAGE_EDIT, 'Edit School')
            ->setSearchFields(['name', 'city', 'streetAddress'])
            ->setDefaultSort([
                'name' => 'ASC',
            ]);
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    /**
     * @phpstan-param mixed $entityManager
     * @phpstan-param mixed $entityInstance
     */
    #[Override]
    public function persistEntity(mixed $entityManager, mixed $entityInstance): void
    {
        if (! $entityInstance instanceof School) {
            /** @phpstan-ignore argument.type, argument.type */
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        // School implements HasAddressInterface, so this call is type-safe.
        if (! $this->applyAddressFromRequest($entityInstance, 'School')) {
            return;
        }

        /** @phpstan-ignore argument.type, argument.type */
        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * @phpstan-param mixed $entityManager
     * @phpstan-param mixed $entityInstance
     */
    #[Override]
    public function updateEntity(mixed $entityManager, mixed $entityInstance): void
    {
        if (! $entityInstance instanceof School) {
            /** @phpstan-ignore argument.type, argument.type */
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        if (! $this->applyAddressFromRequest($entityInstance, 'School')) {
            return;
        }

        /** @phpstan-ignore argument.type, argument.type */
        parent::updateEntity($entityManager, $entityInstance);
    }
}
