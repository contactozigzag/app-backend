<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets Cache-Control headers on all API responses so Cloudflare and browsers
 * cache appropriately. Runs late (priority -10) to let API Platform and other
 * subscribers set their own headers first, then we normalise.
 *
 * Rules:
 *  - Webhooks & mutations (non-GET/HEAD)  → no-store (never cache side-effecting requests)
 *  - Authenticated GET (has Authorization) → private, max-age=60 + Vary: Authorization
 *  - Public GET (no Authorization)         → public, s-maxage=300, max-age=60
 *  - Non-API routes (/health, /, etc.)     → untouched
 *  - 4xx/5xx responses                     → no-store (don't cache errors)
 *
 * ETags are added to successful GET responses for conditional-request support
 * (If-None-Match → 304), reducing payload size for unchanged data.
 */
readonly class HttpCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $pathInfo = $request->getPathInfo();

        // Only manage Cache-Control for /api/* routes
        if (! str_starts_with($pathInfo, '/api/')) {
            return;
        }

        $statusCode = $response->getStatusCode();

        // Never cache error responses — they may be transient
        if ($statusCode >= 400) {
            $response->headers->set('Cache-Control', 'no-store');

            return;
        }

        // Webhooks: never cache (fire-and-forget, idempotency handled separately)
        if (str_starts_with($pathInfo, '/api/webhooks')) {
            $response->headers->set('Cache-Control', 'no-store');

            return;
        }

        $method = $request->getMethod();

        // Mutations: no-store — POST/PUT/PATCH/DELETE must never be cached
        if (! in_array($method, ['GET', 'HEAD'], true)) {
            $response->headers->set('Cache-Control', 'no-store');

            return;
        }

        // Successful GET/HEAD: set caching policy based on authentication
        if ($request->headers->has('Authorization')) {
            // Private: user-specific data — browser may cache, CDN must not
            $response->headers->set('Cache-Control', 'private, max-age=60');
            // Vary on Authorization so Cloudflare never serves one user's response to another
            $response->setVary('Authorization');
        } else {
            // Public: no credentials — Cloudflare (s-maxage=300) + browser (max-age=60)
            $response->headers->set('Cache-Control', 'public, s-maxage=300, max-age=60');
        }

        // ETag for conditional requests (If-None-Match → 304 Not Modified).
        // Only set if not already present and the response has a body.
        if (! $response->headers->has('ETag') && $statusCode === 200) {
            $content = $response->getContent();
            if ($content !== false && $content !== '') {
                $response->setEtag(md5($content));
                $response->isNotModified($request);
            }
        }
    }
}
