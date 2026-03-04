<?php

declare(strict_types=1);

namespace App\State\Student;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Scopes GetCollection /api/students to the authenticated user.
 *
 * - ROLE_SCHOOL_ADMIN / ROLE_SUPER_ADMIN: all students (SchoolFilter scopes by school)
 * - ROLE_PARENT: only students where the user is a parent
 * - Others (ROLE_DRIVER, etc.): empty collection
 *
 * @implements ProviderInterface<Student>
 */
final readonly class StudentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @return Student[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        if ($this->security->isGranted('ROLE_SCHOOL_ADMIN')) {
            return $this->entityManager->getRepository(Student::class)->findAll();
        }

        if ($this->security->isGranted('ROLE_PARENT')) {
            /** @var User $user */
            $user = $this->security->getUser();

            return $this->entityManager->createQuery(
                'SELECT s FROM App\Entity\Student s JOIN s.parents p WHERE p = :user'
            )
                ->setParameter('user', $user)
                ->getResult();
        }

        return [];
    }
}
