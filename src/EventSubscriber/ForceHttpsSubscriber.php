<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forces HTTPS scheme in production.
 *
 * Traefik terminates TLS and forwards to Caddy on plain HTTP.
 * Without this, Symfony generates HTTP URLs for redirects, assets,
 * and API Platform IRIs. Setting HTTPS=on and SERVER_PORT=443
 * makes Symfony treat the request as HTTPS.
 */
final readonly class ForceHttpsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $appEnv,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($this->appEnv !== 'prod') {
            return;
        }

        if (! $event->isMainRequest()) {
            return;
        }

        $event->getRequest()->server->set('HTTPS', 'on');
        $event->getRequest()->server->set('SERVER_PORT', 443);
    }
}
