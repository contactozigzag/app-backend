<?php

declare(strict_types=1);

namespace App\State\Student;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Handles POST /api/students.
 *
 * Persists the student and automatically links the authenticated ROLE_PARENT as a parent.
 * Sets school from the user's school if not already set.
 *
 * @implements ProcessorInterface<Student, Student>
 */
final readonly class StudentCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Student
    {
        /** @var Student $student */
        $student = $data;

        /** @var User $user */
        $user = $this->security->getUser();

        $student->addParent($user);

        if ($student->getSchool() === null && $user->getSchool() !== null) {
            $student->setSchool($user->getSchool());
        }

        $this->entityManager->persist($student);
        $this->entityManager->flush();

        return $student;
    }
}
