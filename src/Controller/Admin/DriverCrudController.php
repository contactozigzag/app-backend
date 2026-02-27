<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Driver;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<Driver> */
class DriverCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Driver::class;
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
