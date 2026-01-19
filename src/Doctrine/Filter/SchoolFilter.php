<?php

namespace App\Doctrine\Filter;

use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine filter to automatically filter entities by school context.
 */
class SchoolFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        // Only apply filter to entities that have a school relationship
        if (!$targetEntity->hasAssociation('school')) {
            return '';
        }

        // Don't filter if no school ID is set
        if (!$this->hasParameter('school_id')) {
            return '';
        }

        $schoolId = $this->getParameter('school_id');

        // Apply filter based on entity type
        if ($targetEntity->getName() === User::class || $targetEntity->getName() === Student::class) {
            return sprintf('%s.school_id = %s', $targetTableAlias, $schoolId);
        }

        return '';
    }
}
