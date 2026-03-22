<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Validates a Lexik JWT and returns the user identifier.
 *
 * Used by the oauth_connect firewall so the mobile app can pass
 * the JWT as a query parameter when opening the browser.
 */
final readonly class JwtAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        try {
            $payload = $this->jwtManager->parse($accessToken);
        } catch (\Throwable) {
            throw new BadCredentialsException('Invalid JWT token.');
        }

        $userIdentifier = $payload[$this->jwtManager->getUserIdClaim()] ?? null;

        if (! is_string($userIdentifier) || $userIdentifier === '') {
            throw new BadCredentialsException('Invalid JWT token.');
        }

        return new UserBadge($userIdentifier);
    }
}
