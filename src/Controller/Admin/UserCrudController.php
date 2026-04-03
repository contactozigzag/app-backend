<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Trait\AddressFormTrait;
use App\Entity\User;
use App\Service\AddressGeocoder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** @extends AbstractCrudController<User> */
class UserCrudController extends AbstractCrudController
{
    use AddressFormTrait;

    public function __construct(
        AddressGeocoder $addressGeocoder,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        $this->addressGeocoder = $addressGeocoder;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('All Users')
            ->setSearchFields(['email', 'firstName', 'lastName', 'identificationNumber'])
            ->setDefaultSort([
                'lastName' => 'ASC',
            ]);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $roles = ['ROLE_SUPER_ADMIN', 'ROLE_SCHOOL_ADMIN', 'ROLE_DRIVER', 'ROLE_PARENT', 'ROLE_USER'];

        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield TextField::new('identificationNumber')->setLabel('DNI');
        yield TextField::new('fullName')->hideOnForm();
        yield TextField::new('firstName')->onlyOnForms();
        yield TextField::new('lastName')->onlyOnForms();
        yield TextField::new('phoneNumber')->onlyOnForms();
        yield ChoiceField::new('roles')
            ->renderAsBadges()
            ->setChoices(array_combine($roles, $roles))
            ->allowMultipleChoices()
            ->renderExpanded();

        yield TextField::new('newPassword', 'Password')
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('mapped', false)
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->onlyOnForms()
            ->setHelp($pageName === Crud::PAGE_EDIT ? 'Leave blank to keep the current password.' : '');

        yield TextField::new('newPasswordConfirm', 'Confirm Password')
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('mapped', false)
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->onlyOnForms();

        yield FormField::addFieldset('Address Information');

        yield ChoiceField::new('addressLanguage', 'Address Language')
            ->setChoices([
                'English' => 'en',
                'Spanish / Español' => 'es',
            ])
            ->setRequired(false)
            ->setFormTypeOption('mapped', false)
            ->onlyOnForms()
            ->setHelp('Select the language for address formatting. Spanish uses "Street Name + Number" format (e.g., "Avenida Benavídez 1632").');

        yield TextField::new('addressInput', 'Address')
            ->setRequired(false)
            ->setHelp('Enter the full address. English: "123 Main St, City, State, ZIP" | Spanish: "Avenida Benavídez 1632, Ciudad, Provincia, CP"')
            ->setFormTypeOption('mapped', false)
            ->onlyOnForms()
            ->setFormTypeOption('attr', [
                'placeholder' => 'e.g., 123 Main Street, New York, NY 10001 OR Avenida Benavídez 1632, Buenos Aires',
                'data-controller' => 'google-address-autocomplete',
                'data-google-address-autocomplete-api-key-value' => $_ENV['GOOGLE_MAPS_FRONTEND_API_KEY'] ?? '',
            ]);

        yield TextField::new('address.streetAddress', 'Street Address')->hideOnForm();
        yield TextField::new('address.city', 'City')->hideOnForm();
        yield TextField::new('address.state', 'State')->hideOnForm();
        yield TextField::new('address.country', 'Country')->hideOnForm();
        yield TextField::new('address.postalCode', 'Postal Code')->hideOnForm();
        yield TextField::new('address.latitude', 'Latitude')->hideOnForm();
        yield TextField::new('address.longitude', 'Longitude')->hideOnForm();
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    /**
     * @phpstan-param mixed $entityManager
     * @phpstan-param mixed $entityInstance
     */
    #[Override]
    public function persistEntity(mixed $entityManager, mixed $entityInstance): void
    {
        if (! $entityInstance instanceof User) {
            /** @phpstan-ignore argument.type, argument.type */
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        if (! $this->applyPassword($entityInstance, true)) {
            return;
        }

        if (! $this->applyAddressFromRequest($entityInstance, 'User')) {
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
        if (! $entityInstance instanceof User) {
            /** @phpstan-ignore argument.type, argument.type */
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        if (! $this->applyPassword($entityInstance, false)) {
            return;
        }

        if (! $this->applyAddressFromRequest($entityInstance, 'User')) {
            return;
        }

        /** @phpstan-ignore argument.type, argument.type */
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Validates and hashes the password from the request.
     * Returns false if validation failed (caller should abort persistence).
     */
    private function applyPassword(User $user, bool $passwordRequired): bool
    {
        $request = $this->getContext()?->getRequest();
        $userData = $request?->request->all('User');

        if (! is_array($userData)) {
            return ! $passwordRequired;
        }

        $plain = is_string($userData['newPassword'] ?? null) ? $userData['newPassword'] : '';
        $confirm = is_string($userData['newPasswordConfirm'] ?? null) ? $userData['newPasswordConfirm'] : '';

        if ($plain === '' && $confirm === '') {
            if ($passwordRequired) {
                $this->addFlash('danger', 'Password is required when creating a new user.');

                return false;
            }

            return true;
        }

        if ($plain !== $confirm) {
            $this->addFlash('danger', 'Passwords do not match.');

            return false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));

        return true;
    }
}
