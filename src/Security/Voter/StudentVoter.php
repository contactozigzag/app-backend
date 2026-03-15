<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Driver;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\RouteStopRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Student>
 */
final class StudentVoter extends Voter
{
    public const string VIEW = 'STUDENT_VIEW';

    public function __construct(
        private readonly RouteStopRepository $routeStopRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Student;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (! $user instanceof User) {
            return false;
        }

        if ($subject->getParents()->contains($user)) {
            return true;
        }

        if (in_array('ROLE_SCHOOL_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        $driver = $user->getDriver();

        if (! $driver instanceof Driver) {
            return false;
        }

        return $this->routeStopRepository->existsForStudentAndDriver($subject, $driver);
    }
}
