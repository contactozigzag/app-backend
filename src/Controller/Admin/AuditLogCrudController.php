<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\JsonPreviewField;
use App\Entity\AdminAuditLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Override;

/** @extends AbstractCrudController<AdminAuditLog> */
class AuditLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AdminAuditLog::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Audit Log Entry')
            ->setEntityLabelInPlural('Audit Log')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['entityType', 'entityId', 'adminEmail'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield DateTimeField::new('createdAt', 'When')
            ->hideOnForm();

        yield TextField::new('adminEmail', 'Admin')
            ->hideOnForm();

        yield ChoiceField::new('action', 'Action')
            ->setChoices([
                'Create' => 'create',
                'Update' => 'update',
                'Delete' => 'delete',
            ])
            ->renderAsBadges([
                'create' => 'success',
                'update' => 'warning',
                'delete' => 'danger',
            ])
            ->hideOnForm();

        yield TextField::new('entityType', 'Entity')
            ->hideOnForm();

        yield TextField::new('entityId', 'ID')
            ->hideOnForm();

        yield JsonPreviewField::new('changes', 'Changes')
            ->onlyOnDetail();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('action')->setChoices([
                'Create' => 'create',
                'Update' => 'update',
                'Delete' => 'delete',
            ]))
            ->add(TextFilter::new('entityType', 'Entity Type'))
            ->add(TextFilter::new('adminEmail', 'Admin Email'))
            ->add(DateTimeFilter::new('createdAt', 'Date'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
