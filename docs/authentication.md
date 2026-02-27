# Authentication

ZigZag uses stateless JWT authentication for all `/api/*` routes via `lexik/jwt-authentication-bundle`. Refresh tokens are managed by `gesdinet/jwt-refresh-token-bundle` (v2).

## Token Lifetimes

| Token | TTL | Storage |
|-------|-----|---------|
| JWT (access token) | 2 hours (`token_ttl: 7200`) | Client memory / secure storage |
| Refresh token | 30 days (`ttl: 2592000`) | `refresh_tokens` DB table |

## Endpoints

### Login — `POST /api/login`

Public. Returns a JWT and a refresh token.

**Request:**
```json
{ "email": "user@example.com", "password": "password123" }
```

**Response `200`:**
```json
{
  "token": "<JWT>",
  "refresh_token": "<opaque 128-char token>",
  "refresh_token_expiration": 1751000000
}
```

**Response `401`:** invalid credentials.

---

### Token Refresh — `POST /api/token/refresh`

Public. Exchanges a valid (non-expired, not-yet-used) refresh token for a new JWT + refresh token pair.

Refresh tokens are **single-use** (`single_use: true`): the submitted token is deleted and a brand-new one is issued on every successful refresh. This is token rotation — it limits the blast radius of a stolen refresh token.

**Request:**
```json
{ "refresh_token": "<opaque 128-char token>" }
```

**Response `200`:**
```json
{
  "token": "<new JWT>",
  "refresh_token": "<new opaque token>",
  "refresh_token_expiration": 1754000000
}
```

**Response `401`:** token not found, expired, or already consumed.

---

### Logout

The `api_token_refresh` firewall has `invalidate_token_on_logout: true` (bundle default), so a logout event automatically deletes the stored refresh token from the database. On the client side always clear both `jwt_token` and `refresh_token` from storage.

---

## Security Configuration

### Firewalls (`config/packages/security.yaml`)

| Firewall | Pattern | Mechanism |
|----------|---------|-----------|
| `login` | `^/api/login` | `json_login` → issues JWT on success |
| `api_token_refresh` | `^/api/token/refresh` | `refresh_jwt` authenticator |
| `api` | `^/api` | `jwt` authenticator (Bearer token) |
| `main` | `^/` | `form_login` (web/admin only) |

### Bundle Configuration (`config/packages/gesdinet_jwt_refresh_token.yaml`)

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken
    ttl: 2592000          # 30 days
    ttl_update: false     # TTL is fixed at creation; rotation handles freshness
    single_use: true      # rotate on each use
    token_parameter_name: refresh_token
    return_expiration: true
```

---

## Database

The bundle persists refresh tokens in a `refresh_tokens` table (MySQL):

```sql
CREATE TABLE refresh_tokens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    refresh_token   VARCHAR(128) NOT NULL UNIQUE,
    username        VARCHAR(255) NOT NULL,
    valid           DATETIME NOT NULL
);
```

Migration: `migrations/Version20260227000000.php`

To run the migration:
```bash
docker compose --env-file .env.local exec php bin/console doctrine:migrations:migrate
```

To purge expired tokens (use as a scheduled task or console command):
```bash
docker compose --env-file .env.local exec php bin/console gesdinet:jwt:clear-invalid-refresh-tokens
```

---

## Flow Diagram

```
Client                        API
  │                            │
  │─── POST /api/login ───────►│  json_login firewall
  │◄── { token, refresh_token }│  AttachRefreshTokenOnSuccessListener
  │                            │  creates + persists refresh token
  │                            │
  │─── GET /api/... ──────────►│  api firewall (JWT)
  │◄── 200 OK ─────────────────│
  │                            │
  │  (JWT expires after 2h)    │
  │                            │
  │─── GET /api/... ──────────►│  api firewall → 401 Unauthorized
  │◄── 401 ────────────────────│
  │                            │
  │─── POST /api/token/refresh►│  api_token_refresh firewall
  │    { refresh_token }        │  RefreshTokenAuthenticator validates token
  │◄── { token, refresh_token }│  old token deleted, new token persisted
  │                            │
  │  (refresh token expires    │
  │   or user logs out)        │
  │─── POST /logout ──────────►│  LogoutEventListener deletes DB token
  │◄── redirect / 200 ─────────│
```

---

## Role Hierarchy

```
ROLE_SUPER_ADMIN
  └─ ROLE_SCHOOL_ADMIN
       ├─ ROLE_PARENT
       │    └─ ROLE_USER
       └─ ROLE_DRIVER
            └─ ROLE_USER
```

All authenticated API requests require at minimum `IS_AUTHENTICATED_FULLY`. Role-specific restrictions are enforced per-operation via `security` expressions on `#[ApiResource]` operations.
