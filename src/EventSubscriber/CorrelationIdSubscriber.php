<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Logging\CorrelationIdService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the correlation ID from X-Request-ID header (or generates one) at the
 * very start of every HTTP request, and echoes it back as X-Correlation-ID.
 */
class CorrelationIdSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CorrelationIdService $correlationIdService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = $request->headers->get('X-Request-ID');

        if ($requestId !== null && $requestId !== '') {
            // Truncate to 8 chars for consistency
            $this->correlationIdService->set(substr($requestId, 0, 8));
        } else {
            // Force generation
            $this->correlationIdService->set(CorrelationIdService::generate());
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set(
            'X-Correlation-ID',
            $this->correlationIdService->get(),
        );
    }
}
