<?php

declare(strict_types=1);

namespace App\Enum;

enum EducationalLevel: string
{
    case Kindergarten = 'kindergarten';
    case ElementarySchool = 'elementary_school';
    case HighSchool = 'high_school';
}
