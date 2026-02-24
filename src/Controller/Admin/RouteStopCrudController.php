<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RouteStop;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<RouteStop> */
class RouteStopCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RouteStop::class;
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
