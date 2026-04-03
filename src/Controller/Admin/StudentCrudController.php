<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Student;
use App\Enum\EducationalLevel;
use App\Enum\Grade;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

/** @extends AbstractCrudController<Student> */
class StudentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Student::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Student')
            ->setEntityLabelInPlural('Students')
            ->setDefaultSort([
                'lastName' => 'ASC',
            ])
            ->setSearchFields(['firstName', 'lastName', 'identificationNumber'])
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/detail', 'admin/crud/student/detail.html.twig');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('firstName', 'First Name');
        yield TextField::new('lastName', 'Last Name');
        yield TextField::new('identificationNumber', 'DNI')
            ->hideOnIndex();

        yield AssociationField::new('school')
            ->autocomplete()
            ->hideOnIndex();

        // Use enum cases as choice values so Doctrine receives the typed enum, not a raw string
        yield ChoiceField::new('grade', 'Grade')
            ->setChoices([
                '1st' => Grade::One,
                '2nd' => Grade::Two,
                '3rd' => Grade::Three,
                '4th' => Grade::Four,
                '5th' => Grade::Five,
                '6th' => Grade::Six,
            ])
            ->allowMultipleChoices(false)
            ->setRequired(false);

        yield ChoiceField::new('educationalLevel', 'Level')
            ->setChoices([
                'Kindergarten' => EducationalLevel::Kindergarten,
                'Elementary School' => EducationalLevel::ElementarySchool,
                'High School' => EducationalLevel::HighSchool,
            ])
            ->allowMultipleChoices(false)
            ->setRequired(false)
            ->hideOnIndex();

        yield DateField::new('birthday', 'Date of Birth')
            ->hideOnIndex();

        yield TextareaField::new('medicalHistory', 'Medical Notes')
            ->hideOnIndex()
            ->setRequired(false);

        yield TextareaField::new('additionalInfo', 'Additional Info')
            ->hideOnIndex()
            ->setRequired(false);

        yield TextField::new('emergencyContact', 'Emergency Contact')
            ->hideOnIndex()
            ->setRequired(false);

        yield TextField::new('emergencyContactNumber', 'Emergency Phone')
            ->hideOnIndex()
            ->setRequired(false);

        yield AssociationField::new('parents', 'Parents')
            ->autocomplete()
            ->setRequired(false)
            ->hideOnIndex();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        $filters
            ->add(ChoiceFilter::new('grade')->setChoices([
                '1st' => Grade::One->value,
                '2nd' => Grade::Two->value,
                '3rd' => Grade::Three->value,
                '4th' => Grade::Four->value,
                '5th' => Grade::Five->value,
                '6th' => Grade::Six->value,
            ]))
            ->add(ChoiceFilter::new('educationalLevel')->setChoices([
                'Kindergarten' => EducationalLevel::Kindergarten->value,
                'Elementary School' => EducationalLevel::ElementarySchool->value,
                'High School' => EducationalLevel::HighSchool->value,
            ]));
        // Filters use raw string values (not enum cases) — ChoiceFilter compares against the DB column value

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $filters->add(EntityFilter::new('school'));
        }

        return $filters;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        // DETAIL is not on INDEX by default; EDIT and DELETE are already on DETAIL
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
