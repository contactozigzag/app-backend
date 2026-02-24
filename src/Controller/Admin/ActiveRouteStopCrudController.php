<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ActiveRouteStop;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<ActiveRouteStop> */
class ActiveRouteStopCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ActiveRouteStop::class;
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
