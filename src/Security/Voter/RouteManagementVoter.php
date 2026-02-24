<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @extends Voter<string, mixed>
 */
final class RouteManagementVoter extends Voter
{
    public const string ATTRIBUTE = 'ROUTE_MANAGE';

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        #[Autowire('%app.driver_route_management_enabled%')]
        private readonly bool $driverRouteManagementEnabled,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ATTRIBUTE;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());

        if (in_array('ROLE_SCHOOL_ADMIN', $reachableRoles, true)) {
            return true;
        }

        if ($this->driverRouteManagementEnabled && in_array('ROLE_DRIVER', $reachableRoles, true)) {
            return true;
        }

        return false;
    }
}
