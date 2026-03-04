<?php

declare(strict_types=1);

namespace App\State\Absence;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Absence;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Scopes GetCollection /api/absences to the authenticated user.
 *
 * - ROLE_SCHOOL_ADMIN / ROLE_SUPER_ADMIN: all absences (SchoolFilter already scopes by school)
 * - ROLE_PARENT: only absences for students where the user is a parent
 * - Others: empty collection
 *
 * @implements ProviderInterface<Absence>
 */
final readonly class AbsenceCollectionProvider implements ProviderInterface
{
    public function __construct(
        private AbsenceRepository $absenceRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @return Absence[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        if ($this->security->isGranted('ROLE_SCHOOL_ADMIN')) {
            return $this->absenceRepository->findAll();
        }

        if ($this->security->isGranted('ROLE_PARENT')) {
            /** @var User $user */
            $user = $this->security->getUser();

            return $this->entityManager->createQuery(
                'SELECT a FROM App\Entity\Absence a
                 JOIN a.student s
                 JOIN s.parents p
                 WHERE p = :user'
            )
                ->setParameter('user', $user)
                ->getResult();
        }

        return [];
    }
}
