<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Security\Voter\RouteManagementVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class RouteManagementVoterTest extends TestCase
{
    /**
     * @param string[] $roles
     */
    private function makeToken(array $roles): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn($roles);

        return $token;
    }

    /**
     * @param string[] $reachable
     */
    private function makeHierarchy(array $reachable): RoleHierarchyInterface
    {
        $hierarchy = $this->createStub(RoleHierarchyInterface::class);
        $hierarchy->method('getReachableRoleNames')->willReturn($reachable);

        return $hierarchy;
    }

    // ── Flag disabled ──────────────────────────────────────────────────────────

    public function testFlagDisabledSchoolAdminIsGranted(): void
    {
        $voter = new RouteManagementVoter(
            $this->makeHierarchy(['ROLE_USER', 'ROLE_PARENT', 'ROLE_DRIVER', 'ROLE_SCHOOL_ADMIN']),
            false,
        );

        $result = $voter->vote($this->makeToken(['ROLE_SCHOOL_ADMIN']), null, [RouteManagementVoter::ATTRIBUTE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testFlagDisabledDriverIsDenied(): void
    {
        $voter = new RouteManagementVoter(
            $this->makeHierarchy(['ROLE_USER', 'ROLE_DRIVER']),
            false,
        );

        $result = $voter->vote($this->makeToken(['ROLE_DRIVER']), null, [RouteManagementVoter::ATTRIBUTE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── Flag enabled ───────────────────────────────────────────────────────────

    public function testFlagEnabledDriverIsGranted(): void
    {
        $voter = new RouteManagementVoter(
            $this->makeHierarchy(['ROLE_USER', 'ROLE_DRIVER']),
            true,
        );

        $result = $voter->vote($this->makeToken(['ROLE_DRIVER']), null, [RouteManagementVoter::ATTRIBUTE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testFlagEnabledParentWithoutDriverRoleIsDenied(): void
    {
        $voter = new RouteManagementVoter(
            $this->makeHierarchy(['ROLE_USER', 'ROLE_PARENT']),
            true,
        );

        $result = $voter->vote($this->makeToken(['ROLE_PARENT']), null, [RouteManagementVoter::ATTRIBUTE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── Abstain on unknown attribute ───────────────────────────────────────────

    public function testAbstainsOnUnknownAttribute(): void
    {
        $voter = new RouteManagementVoter(
            $this->makeHierarchy(['ROLE_SCHOOL_ADMIN']),
            false,
        );

        $result = $voter->vote($this->makeToken(['ROLE_SCHOOL_ADMIN']), null, ['SOME_OTHER_ATTRIBUTE']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}
