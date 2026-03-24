<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Logging\LogSanitizer;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Logs authentication and authorization events to the security_audit channel.
 */
class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $securityAuditLogger,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
            Events::JWT_EXPIRED => 'onJwtExpired',
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $context = [
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
        ];

        if ($user instanceof User) {
            $context['user_id'] = $user->getId();
            $context['email'] = LogSanitizer::maskEmail($user->getEmail() ?? '');
            $context['roles'] = $user->getRoles();
        }

        $this->securityAuditLogger->info('Authentication success', $context);
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest() ?? $this->requestStack->getCurrentRequest();

        $context = [
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'failure_reason' => $event->getException()->getMessageKey(),
        ];

        // Try to extract attempted email from request body
        if ($request instanceof Request) {
            $content = $request->getContent();
            if ($content !== '') {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['email']) && is_string($data['email'])) {
                    $context['attempted_email'] = LogSanitizer::maskEmail($data['email']);
                }
            }
        }

        $this->securityAuditLogger->warning('Authentication failure', $context);
    }

    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $this->securityAuditLogger->debug('JWT token expired', [
            'ip' => $request?->getClientIp(),
        ]);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (! $exception instanceof AccessDeniedException) {
            return;
        }

        $request = $event->getRequest();
        $currentUser = $this->security->getUser();
        $user = $currentUser instanceof User ? $currentUser : null;

        $this->securityAuditLogger->warning('Access denied', [
            'user_id' => $user?->getId(),
            'requested_path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'user_roles' => $user?->getRoles(),
            'ip' => $request->getClientIp(),
        ]);
    }
}
