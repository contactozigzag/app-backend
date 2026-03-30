# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ZigZag is a school transportation management system built with PHP 8.5+, Symfony 8.0, API Platform 4.2, and Doctrine ORM. It features multi-tenancy (school-based data isolation), real-time GPS tracking, an async messaging pipeline, driver distress alerts, emergency chat, and MercadoPago payment integration.

## Common Commands

All commands run inside Docker. The Makefile auto-detects `.env.local` (uses it if present, skips if not).

```bash
make up dev            # Start containers
make sh                # Open shell in PHP container
make test              # Run full test suite (PHPUnit)
make quality           # CI mode: run ECS + PHPStan + Rector + linters (no auto-fix)
make fix               # Apply all auto-fixes (ECS + Rector)
make phpstan           # Static analysis (level 9)
make rector-dry        # Rector dry-run
make ecs-dry           # ECS dry-run
make db-reset          # Drop, create, and migrate database
make db-diff           # Generate migration from entity changes
make db-migrate        # Run pending migrations
```

**Running a single test:**
```bash
docker compose exec -e APP_ENV=test php bin/phpunit tests/Path/To/TestClass.php
docker compose exec -e APP_ENV=test php bin/phpunit --filter testMethodName
```

**Clearing test cache** (needed after config changes):
```bash
docker compose exec -e APP_ENV=test php sh -c 'php -d memory_limit=512M bin/console cache:clear --env=test --no-warmup'
```

## Architecture

### Infrastructure Stack

```
Client → Cloudflare (edge TLS, DDoS, CDN) → FrankenPHP/Caddy (ports 80/443)
                                              ├── Mercure SSE hub (built-in, heartbeat 45s)
                                              ├── Vulcain (HTTP/2 preload)
                                              └── PHP worker mode
```

No reverse proxy between Cloudflare and FrankenPHP — Caddy serves directly with `trusted_proxies private_ranges` for X-Forwarded-* headers.

### Compose File Structure (3-file, upstream symfony-docker pattern)

- **`compose.yaml`** — base services (php, database, rabbitmq, redis, opensearch, workers)
- **`compose.override.yaml`** — dev overrides (auto-loaded by `docker compose up`)
- **`compose.prod.yaml`** — production + blue/green deploy (profiles: infra, blue, green)

Dev: `docker compose up` (auto-loads override)
Prod: `docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod --profile infra --profile <slot> up -d`

### Multi-Tenancy
`App\Doctrine\Filter\SchoolFilter` is a Doctrine filter applied globally for school-scoped data isolation. All entities belonging to a school are filtered automatically once the filter is enabled with a school ID.

### Dual Firewall
- `/api/*` → `api` firewall (stateless JWT) — unauthenticated returns **401**
- Web/OAuth routes → `main` firewall (form-login) — unauthenticated returns **302 to /login**
- Use `$client->loginUser()` (not JWT) for `main` firewall tests

### Async Messaging Pipeline
Three Symfony Messenger transports (all RabbitMQ via phpamqplib):
- `async`: general messages — email, SMS, chat, subscription processing
- `async_webhooks`: payment webhook processing, isolated for fast turnaround
- `async_tracking`: GPS events (`DriverLocationUpdatedMessage`), isolated from webhooks

Each transport requires its own dedicated worker process (phpamqplib blocking consumer).
In `test` environment all transports use `test://` (synchronous, assertable).

### Payment Sync
Sandbox/test payments do not trigger real MP webhook notifications. Use the CLI command to manually sync payment status from Mercado Pago:

```bash
# Sync a payment using its MP payment ID (required when paymentProviderId is not yet stored)
php bin/console app:payment:sync <payment-id> --provider-id=<mp-payment-id>

# Sync a payment that already has a provider ID
php bin/console app:payment:sync <payment-id>

# Dry run — preview without changes
php bin/console app:payment:sync <payment-id> --provider-id=<mp-payment-id> --dry-run
```

This runs the full flow: fetches status from MP API → updates payment entity → dispatches events (Mercure notifications, etc.).

### Payment Maintenance
- **Duplicate prevention:** `CreatePaymentPreferenceProcessor` auto-cancels any existing unexpired pending payment for the same user+driver before creating a new one.
- **Stale payment expiration:** `PaymentScheduleProvider` (`#[AsSchedule('payment_maintenance')]`) dispatches `ExpireStalePaymentsMessage` every hour to cancel pending payments past their `expiresAt`. CLI fallback: `php bin/console app:payment:expire-stale`.

### API Platform
Entities use `#[ApiResource]` with attribute-based Doctrine mapping. Custom controllers handle complex operations. If a custom controller handles `GET /api/{entity}` or `GET /api/{entity}/{id}`, remove the corresponding `Get`/`GetCollection` operations from `#[ApiResource]` to avoid route conflicts.

### Real-Time
Mercure hub (Caddy module) publishes live updates with 45s heartbeat (prevents Cloudflare's 100s idle timeout from dropping SSE connections). Vulcain is enabled for HTTP/2 resource preloading. `EventSubscriber` classes publish to topics; `MercureController` handles client subscriptions.

**Trip event pipeline:** `GeofencingService` dispatches `StopApproachingEvent`/`StopArrivedEvent` → `GeofencingBridgeSubscriber` bridges to `BusArrivingEvent` → `TripMercureSubscriber` publishes to `/api/users/{parentId}/notifications` (private) and `/tracking/route/{id}` (public). `AttendanceController` dispatches `StudentPickedUpEvent`/`StudentDroppedOffEvent` after flush. `ActiveRouteStatusListener` (Doctrine postUpdate/postFlush) dispatches `RouteStartedEvent`/`RouteCompletedEvent`. All Mercure publishes are non-fatal (try/catch + log).

**Stop link request pipeline:** `RouteStopCreatedListener` (Doctrine postPersist/postFlush) detects new unconfirmed stops → `RouteStopNotificationPublisher.notifyDriverOfNewRequest()`. `RouteStopConfirmProcessor`/`RouteStopRejectProcessor` call `notifyParentsOfConfirmation()`/`notifyParentsOfRejection()` after flush. All publish to `/api/users/{id}/notifications` (private, non-fatal).

## Testing Conventions

### Boot Order (critical)
Always: `createApiClient()` → create Foundry factories → `loginUser()`. Creating factories before `createClient()` will fail.

### Foundry v2 Factories
- Extend `PersistentObjectFactory` (not `PersistentProxyObjectFactory`)
- Scalar state: `$this->with([...])`
- Collection state: `$this->afterInstantiate(fn($obj) => ...)`
- Factories requiring services (e.g., `UserPasswordHasherInterface`) must be registered as DI services
- `enable_auto_refresh_with_lazy_objects: true` is set in `zenstruck_foundry.yaml`

### Rate Limiter in Tests
`TraceableAdapter` wraps cache pools in debug mode and doesn't implement `StorageInterface`. The `config/packages/rate_limiter.yaml` `when@test` block defines `Symfony\Component\RateLimiter\Storage\InMemoryStorage` as the storage service — preserve this when modifying rate limiter config.

### PHPUnit 12 Mocks
- `createMock()` — when setting expectations with `expects(self::once())`
- `createStub()` — for simple return-value configuration without expectations

## Code Quality

### PHPStan (level 9)
- Config: `phpstan.dist.neon`
- `reportUnmatchedIgnoredErrors: false` — stale suppressions fail the build; remove them when fixed
- Path-based suppressions use the `paths:` key under `ignoreErrors`
- Escape `#` as `\#` inside pattern strings (PHPStan uses `#` as regex delimiter)
- phpstan-doctrine uses: "property can contain X|null but database expects X" message format

### Rector
- Config: `rector.php`
- PHP 8.5 set, Symfony/Doctrine/PHPUnit sets enabled
- Skips: `var/`, `migrations/`, `AppFixtures.php`

### ECS
- Config: `ecs.php`
- PSR-12 + strict comparisons (`===`), `declare(strict_types=1)`, alphabetical ordered imports
- Skips: `var/`, `migrations/`, `AppFixtures.php`

## Key Roles

`ROLE_USER` → `ROLE_PARENT` → `ROLE_DRIVER` → `ROLE_SCHOOL_ADMIN` → `ROLE_SUPER_ADMIN` (hierarchy defined in `security.yaml`).

## Environment

- PHP 8.5+ on FrankenPHP (Caddy-based) — serves directly behind Cloudflare (no Traefik)
- PostgreSQL 18 (geo-spatial via PostGIS, JSONB, advisory locks)
- Redis for sessions, cache, and rate limiting
- RabbitMQ for all three Messenger transports (async, webhooks, tracking)
- OpenSearch 2.x at `http://opensearch:9200` (single-node, security disabled) for driver full-text search
- JWT keys in `config/jwt/` (generated via CI workflow)
- Distributed locks via `postgresql+advisory://` (works across containers)

### Performance Optimizations
- **OPcache JIT** (tracing mode 1255, 128MB buffer) in production
- **Class preloading** via `opcache.preload` in production
- **Static binary build** available (`frankenphp_static` Dockerfile target) — bundles entire app into single executable, eliminates all filesystem stat() calls
- **Vulcain** enabled for HTTP/2 resource preloading
- **Mercure heartbeat** at 45s prevents Cloudflare 100s idle timeout
- **Cloudflare trusted proxies** configured in Caddyfile
- **YAML anchors** in compose.prod.yaml to DRY up OTEL, logging, and env blocks

### Deployment (Blue/Green via Caddy Admin API)
- `compose.prod.yaml` contains blue/green slots with Docker profiles (infra, blue, green)
- `scripts/deploy.sh` runs on the droplet: build → start new slot → health check → migrate → switch via Caddy admin API → drain → stop old slot
- Traffic switching uses Caddy's admin API (`POST http://localhost:2019/load`) for zero-downtime graceful reload — existing connections (including Mercure SSE) drain naturally
- `.active-slot` file on the droplet tracks which slot (blue/green) is currently live
- `.env.prod` on the droplet contains all production secrets (never committed)
- Deploy is triggered by pushing a `v*` tag or via GitHub Actions `workflow_dispatch`
- Shared infrastructure (database, rabbitmq, redis, opensearch, fluent-bit, alloy) uses the `infra` profile and is never restarted during deploys

### Observability
- **Traces & metrics:** OpenTelemetry → Grafana Alloy → Grafana Cloud
- **Logs:** Fluent-bit (fluentd driver) → Grafana Cloud Loki
- **Correlation:** Custom `CorrelationIdMiddleware` for distributed tracing across Messenger transports

### OpenSearch
- **Env vars:** `OPENSEARCH_URL` (default `http://opensearch:9200`), `OPENSEARCH_INDEX_PREFIX` (default `zigzag_dev_`)
- **Re-index command:** `php bin/console app:opensearch:index-drivers [--force] [--batch-size=100] [--school=ID]`
- **Service:** `App\Service\OpenSearch\DriverSearchService` — not `final` (needs PHPUnit mocking)
- **Async indexing:** `DriverIndexListener` dispatches messages via Messenger; never call OpenSearch synchronously on writes
