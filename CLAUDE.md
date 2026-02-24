# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ZigZag is a school transportation management system built with PHP 8.4+, Symfony 8.0, API Platform 4.2, and Doctrine ORM. It features multi-tenancy (school-based data isolation), real-time GPS tracking, an async messaging pipeline, driver distress alerts, emergency chat, and MercadoPago payment integration.

## Common Commands

All commands run inside Docker. The Makefile wraps `docker compose --env-file .env.local exec php`.

```bash
make up dev            # Start containers
make sh                # Open shell in PHP container
make test              # Run full test suite (PHPUnit)
make quality           # CI mode: run ECS + PHPStan + Rector + linters (no auto-fix)
make fix               # Apply all auto-fixes (ECS + Rector)
make phpstan           # Static analysis (level 9)
make rector-dry        # Rector dry-run
make ecs-dry           # ECS dry-run
```

**Running a single test:**
```bash
docker compose --env-file .env.local exec -e APP_ENV=test php bin/phpunit tests/Path/To/TestClass.php
docker compose --env-file .env.local exec -e APP_ENV=test php bin/phpunit --filter testMethodName
```

**Clearing test cache** (needed after config changes):
```bash
docker compose --env-file .env.local exec -e APP_ENV=test php sh -c 'php -d memory_limit=512M bin/console cache:clear --env=test --no-warmup'
```

## Architecture

### Multi-Tenancy
`App\Doctrine\Filter\SchoolFilter` is a Doctrine filter applied globally for school-scoped data isolation. All entities belonging to a school are filtered automatically once the filter is enabled with a school ID.

### Dual Firewall
- `/api/*` → `api` firewall (stateless JWT) — unauthenticated returns **401**
- Web/OAuth routes → `main` firewall (form-login) — unauthenticated returns **302 to /login**
- Use `$client->loginUser()` (not JWT) for `main` firewall tests

### Async Messaging Pipeline
Three Symfony Messenger transports:
- `async` (Doctrine): general messages — email, SMS, chat, subscription processing
- `async_webhooks` (RabbitMQ): payment webhook processing, isolated for fast turnaround
- `async_tracking` (RabbitMQ): GPS events (`DriverLocationUpdatedMessage`), isolated from webhooks

In `test` environment all transports use `test://` (synchronous, assertable).

### API Platform
Entities use `#[ApiResource]` with attribute-based Doctrine mapping. Custom controllers handle complex operations. If a custom controller handles `GET /api/{entity}` or `GET /api/{entity}/{id}`, remove the corresponding `Get`/`GetCollection` operations from `#[ApiResource]` to avoid route conflicts.

### Real-Time
Mercure hub (Caddy module) publishes live updates. `EventSubscriber` classes publish to topics; `MercureController` handles client subscriptions.

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

- PHP 8.4+ on FrankenPHP (Caddy-based)
- MySQL 8.4 in dev/test (SQLite with `dbname_suffix` for parallel PHPUnit workers)
- PostgreSQL supported (default `.env` DSN)
- Redis for sessions, cache, and rate limiting
- RabbitMQ for tracking and webhook transports
- JWT keys in `config/jwt/` (generated via `make keys` or CI workflow)
