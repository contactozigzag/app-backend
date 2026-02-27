<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Attendance;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<Attendance> */
class AttendanceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Attendance::class;
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
