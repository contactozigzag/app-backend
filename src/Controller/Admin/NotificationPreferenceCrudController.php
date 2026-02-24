<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationPreference;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<NotificationPreference> */
class NotificationPreferenceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationPreference::class;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
