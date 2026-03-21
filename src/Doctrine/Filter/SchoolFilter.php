<?php

declare(strict_types=1);

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine filter to automatically filter entities by school context.
 *
 * Applies to every entity that has a `school` ManyToOne association
 * (User, Student, Route, etc.). The association must map to a
 * `school_id` join column.
 */
class SchoolFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (! $targetEntity->hasAssociation('school')) {
            return '';
        }

        if (! $this->hasParameter('school_id')) {
            return '';
        }

        $schoolId = $this->getParameter('school_id');

        return sprintf('%s.school_id = %s', $targetTableAlias, $schoolId);
    }
}
