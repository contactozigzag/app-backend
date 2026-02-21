# Mercado Pago Payment Integration — Implementation Complete

## Overview

A complete, production-ready Mercado Pago payment integration for the ZigZag School
Transportation Management System, built with focus on **performance**, **scalability**,
**fail-tolerance**, **idempotency**, **security**, and **real-time user experience**.

The integration uses the **Marketplace + OAuth model**: each driver authorises the ZigZag
app once via OAuth, and every payment a parent makes goes directly into that driver's
Mercado Pago account. The platform can optionally retain a marketplace fee percentage.

## Implementation Status: 100% Complete

### Documentation
- `docs/requirements.md` — Phase 11 with 10 payment requirements
- `docs/plan.md` — Phase 8 with 10 detailed implementation sections
- `docs/tasks.md` — 24 technical tasks documented
- `README.md` — Comprehensive payment integration guide

### Code Implementation

#### Enums (5/5)
- `PaymentStatus` — 7 states: `pending`, `processing`, `approved`, `rejected`, `cancelled`, `refunded`, `partially_refunded`
- `PaymentMethod` — 6 methods: `credit_card`, `debit_card`, `bank_transfer`, `digital_wallet`, `cash`, `mercado_pago`
- `SubscriptionStatus` — 5 states: `active`, `paused`, `cancelled`, `expired`, `payment_failed`
- `BillingCycle` — 4 cycles: `weekly`, `monthly`, `quarterly`, `yearly`
- `TransactionEvent` — 7 events: `created`, `approved`, `rejected`, `refunded`, `cancelled`, `webhook_received`, `status_updated`

#### Entities & Repositories (3/3)
- `Payment` entity — includes `driver` (ManyToOne → Driver, ON DELETE SET NULL) and `idempotency_key`
- `Subscription` entity — recurring billing
- `PaymentTransaction` entity — complete audit trail
- `Driver` entity — extended with `mpAccessToken` (encrypted), `mpRefreshToken` (encrypted), `mpAccountId`, `mpTokenExpiresAt`
- `PaymentRepository`, `SubscriptionRepository`, `PaymentTransactionRepository`

#### Core Services (6/6)
- `IdempotencyService` — Redis-backed with DB fallback and distributed locking
- `MercadoPagoService` — SDK wrapper; uses `RequestOptions` for per-driver API calls
- `WebhookValidator` — HMAC-SHA256 signature validation + replay-attack prevention
- `PaymentProcessor` — Main orchestrator; fetches driver OAuth token for each preference creation
- `TokenEncryptor` — libsodium `secretbox` symmetric encryption for OAuth tokens stored in DB
- `MercadoPagoOAuthService` — Full OAuth flow: build URL, exchange code, refresh tokens, CSRF state via Redis

#### Controllers (5/5)
- `PaymentController` — All parent payment endpoints (`create-preference`, `status`, `list`, `detail`)
- `WebhookController` — Validates MP webhook, looks up payment, dispatches `ProcessWebhookMessage` to RabbitMQ; returns HTTP 200 immediately
- `AdminPaymentController` — Admin operations: refunds, reconciliation, stats
- `OAuthController` — Driver MP OAuth flow: `/oauth/mercadopago/connect`, `/callback`, `/status`
- `MercureController` — Issues short-lived Mercure subscriber JWTs: `GET /api/mercure/token`

#### Async Messaging (2/2)
- `ProcessWebhookMessage` — Immutable DTO carrying `paymentId`, `paymentProviderId`, `webhookData`, `requestId`
- `ProcessWebhookMessageHandler` — Consumes from RabbitMQ; calls MP API, updates DB, dispatches status events; idempotent

#### Events & Subscribers (5/5)
- `PaymentCreatedEvent`, `PaymentApprovedEvent`, `PaymentFailedEvent`, `PaymentRefundedEvent`
- `PaymentEventSubscriber` — Publishes `private: true` Mercure updates on `/payments/{id}`

#### Commands & Scheduler (4/4)
- `ProcessSubscriptionsCommand`, `ProcessSubscriptionsMessage`, `ProcessSubscriptionsMessageHandler`
- `SubscriptionScheduleProvider` — Symfony Scheduler every 5 minutes

#### Configuration (5/5)
- `config/packages/rate_limiter.yaml`
- `config/packages/scheduler.yaml`
- `config/packages/messenger.yaml` — `async` (Doctrine) + `async_webhooks` (RabbitMQ) transports
- `config/services/payment.yaml`
- `.env` — all required variables including `RABBITMQ_DSN`, `TOKEN_ENCRYPTION_KEY`, MP OAuth credentials

#### Database Migrations (3/3)
- `Version20260214000000.php` — Initial payment tables (`payment`, `subscription`, `payment_transaction`)
- `Version20260220120000.php` — Driver OAuth columns (`mp_access_token`, `mp_refresh_token`, `mp_account_id`, `mp_token_expires_at`)
- `Version20260220130000.php` — `payment.driver_id` FK with `ON DELETE SET NULL`

---

## Architecture

### Marketplace + OAuth Model

```
Driver (once, on-boarding)
  └── GET /oauth/mercadopago/connect  →  redirect to MP
        └── POST /oauth/mercadopago/callback  →  exchange code → store encrypted tokens in DB

Parent (each payment)
  └── POST /api/payments/create-preference
        ├── Looks up driver + decrypts MP access token
        ├── Creates MP preference with RequestOptions(driverToken)
        │     → payment goes to driver's MP account directly
        └── Returns init_point URL → parent opens MP checkout
              └── (payment completed by user in MP)
                    └── MP calls POST /api/webhooks/mercadopago
                          └── dispatch ProcessWebhookMessage → RabbitMQ
                                └── ProcessWebhookMessageHandler
                                      ├── Fetch authoritative status from MP API
                                      ├── Persist PaymentTransaction
                                      └── Dispatch PaymentApprovedEvent / PaymentFailedEvent
                                            └── PaymentEventSubscriber → Mercure hub
                                                  └── SSE push to parent app
```

### Two Separate JWTs

A common source of confusion is the use of two different JWTs:

| | API Authentication JWT | Mercure Subscriber JWT |
|---|---|---|
| **Issued by** | `POST /api/login_check` (LexikJWT bundle) | `GET /api/mercure/token` (MercureController) |
| **Signed with** | RSA key-pair (`JWT_SECRET_KEY`) | HMAC-SHA256 (`MERCURE_JWT_SECRET`) |
| **Sent to** | Symfony API (every `/api/*` request) | Mercure hub (EventSource only) |
| **Contains** | User identity, roles | `mercure.subscribe` topic list |
| **Lifetime** | As configured in `lexik_jwt_authentication.yaml` | 1 hour (configurable in `MercureController::TOKEN_TTL`) |

The client must exchange the API JWT for a Mercure JWT before opening the SSE connection.
These tokens are completely independent and must never be swapped.

### Async Webhook Processing

The webhook endpoint (`POST /api/webhooks/mercadopago`) does the minimum synchronous work
to stay under Mercado Pago's ~500 ms retry threshold:

1. Validate HMAC-SHA256 signature
2. Parse payload and check event type
3. Resolve internal `Payment` entity (one indexed DB read)
4. Dispatch `ProcessWebhookMessage` to RabbitMQ
5. Return HTTP 200 immediately

All heavy lifting happens in `ProcessWebhookMessageHandler` (RabbitMQ worker):
- Fetch authoritative status from Mercado Pago REST API
- Persist `PaymentTransaction` audit record
- Dispatch domain events → Mercure push

**Idempotency**: if RabbitMQ delivers the same message twice (at-least-once guarantee),
`PaymentProcessor::updatePaymentFromWebhook()` is a no-op when the status has not changed.

### Additional Architecture Highlights

**Idempotency** (duplicate-charge prevention):
- Client provides a UUID v4 `idempotency_key` per request
- `IdempotencyService` uses Redis (primary) + DB fallback + distributed lock
- Same key within 24 hours returns the cached result without re-processing

**Token encryption**:
- OAuth `access_token` and `refresh_token` stored in DB using libsodium `secretbox`
- 24-byte random nonce prepended to ciphertext, base64-encoded
- Key stored in `TOKEN_ENCRYPTION_KEY` env var (never in DB)

**Rate limiting**: 10 payment API requests / minute / IP via Symfony Rate Limiter.

**Retry strategy** (Messenger):
- `async` (Doctrine): max 3 retries, 2× multiplier
- `async_webhooks` (RabbitMQ): max 3 retries, 1 s → 2 s → 4 s → dead-letter

---

## API Endpoints

### Parent: Create Payment Preference

```http
POST /api/payments/create-preference
Authorization: Bearer {api-jwt}
Content-Type: application/json

{
  "driver_id":       42,
  "student_ids":     [1, 2],
  "amount":          3500.00,
  "description":     "Transporte escolar — febrero 2026",
  "currency":        "ARS",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
}
```

Response `201 Created`:
```json
{
  "payment_id":          123,
  "preference_id":       "123456-abc-def",
  "init_point":          "https://www.mercadopago.com/checkout/v1/redirect?pref_id=...",
  "sandbox_init_point":  "https://sandbox.mercadopago.com/...",
  "status":              "pending",
  "amount":              "3500.00",
  "currency":            "ARS",
  "expires_at":          "2026-02-21T12:00:00+00:00"
}
```

**Error responses:**
- `400` — missing/invalid field or idempotency key format
- `404` — driver not found
- `422` — driver has not connected their Mercado Pago account yet
- `429` — rate limit exceeded

### Parent: Check Payment Status

```http
GET /api/payments/{id}/status
Authorization: Bearer {api-jwt}
```

Response:
```json
{
  "payment_id":      123,
  "status":          "approved",
  "payment_method":  "credit_card",
  "amount":          "3500.00",
  "currency":        "ARS",
  "paid_at":         "2026-02-20T14:30:00+00:00",
  "mercado_pago_id": "1234567890",
  "driver": {
    "id":            42,
    "nickname":      "Carlos G.",
    "mp_account_id": "987654321"
  },
  "students": [
    {"id": 1, "name": "Ana García"},
    {"id": 2, "name": "Luis García"}
  ]
}
```

### Parent: List Payments

```http
GET /api/payments?status=approved&limit=30&offset=0
Authorization: Bearer {api-jwt}
```

### Parent: Payment Detail

```http
GET /api/payments/{id}
Authorization: Bearer {api-jwt}
```

### Parent: Mercure Subscriber Token

```http
GET /api/mercure/token?payment_id={id}
Authorization: Bearer {api-jwt}
```

Response:
```json
{
  "token":   "<mercure-subscriber-jwt>",
  "hub_url": "https://your-domain.com/.well-known/mercure",
  "topics":  ["/payments/123"]
}
```

Use `token` in the Mercure hub `EventSource` connection, **not** the API JWT.
See the "Two Separate JWTs" section above.

### Driver: Connect Mercado Pago Account

```http
GET /oauth/mercadopago/connect
Authorization: Bearer {api-jwt}   (must have ROLE_DRIVER)
```

Response `200`:
```json
{
  "redirect_url": "https://auth.mercadopago.com/authorization?client_id=...&state=..."
}
```

The app opens this URL in the device browser. After the driver grants access, MP
redirects to `MERCADOPAGO_OAUTH_REDIRECT_URI` with `code` and `state` query params.

### Driver: OAuth Callback (public)

```http
GET /oauth/mercadopago/callback?code={code}&state={state}
```

No `Authorization` header required — this is called by the MP browser redirect.
Exchanges `code` for tokens, stores them encrypted in the DB, redirects to the app.

### Driver: OAuth Status

```http
GET /oauth/mercadopago/status
Authorization: Bearer {api-jwt}   (must have ROLE_DRIVER)
```

Response:
```json
{
  "connected":      true,
  "mp_account_id":  "987654321",
  "expires_at":     "2026-08-20T00:00:00+00:00"
}
```

### Webhook Receiver (Mercado Pago → API)

```http
POST /api/webhooks/mercadopago
X-Signature: ts=1234567890,v1=abc123...
X-Request-ID: req-abc-123
Content-Type: application/json
```

Always returns HTTP 200 (even on error) to prevent MP from retrying indefinitely.
Processing is async — see `ProcessWebhookMessageHandler`.

### Admin: Issue Refund

```http
POST /api/admin/payments/{id}/refund
Authorization: Bearer {admin-api-jwt}
Content-Type: application/json

{
  "amount": 1750.00,
  "reason": "Service not provided"
}
```

### Admin: Reconciliation

```http
GET /api/admin/payments/reconciliation?from=2026-02-01&to=2026-02-28
Authorization: Bearer {admin-api-jwt}
```

---

## React Native Integration

Install required packages:

```bash
npm install axios @react-native-async-storage/async-storage uuid react-native-sse
# For driver OAuth (opens browser):
npm install react-native-inappbrowser-reborn
# (or use React Native Linking for simple redirect)
```

### `api/payment.js` (Parent app)

```javascript
import apiClient from './client';
import { v4 as uuidv4 } from 'uuid';
import { Linking } from 'react-native';

/**
 * Create a Mercado Pago payment preference.
 *
 * @param {number}   driverId    - Driver the parent is paying.
 * @param {number[]} studentIds  - Students covered by this payment.
 * @param {number}   amount      - Amount in ARS.
 * @param {string}   description - Description shown in MP checkout.
 */
export const createPayment = async (driverId, studentIds, amount, description) => {
  const response = await apiClient.post('/payments/create-preference', {
    driver_id:       driverId,
    student_ids:     studentIds,
    amount,
    description,
    currency:        'ARS',
    idempotency_key: uuidv4(),
  });
  return response.data;
};

/**
 * Open MP checkout and return the payment_id for status tracking.
 */
export const initiatePayment = async (driverId, studentIds, amount, description) => {
  const payment = await createPayment(driverId, studentIds, amount, description);
  await Linking.openURL(payment.init_point);
  return payment.payment_id;
};

export const checkPaymentStatus = async (paymentId) => {
  const response = await apiClient.get(`/payments/${paymentId}/status`);
  return response.data;
};

/**
 * Obtain a Mercure subscriber JWT scoped to a single payment topic.
 *
 * ── IMPORTANT — two completely different JWTs ─────────────────────────────
 *
 *   API JWT (stored in AsyncStorage as 'jwt_token'):
 *     • Obtained via POST /api/login_check.
 *     • Signed with RSA key. Identifies the user to Symfony.
 *     • Sent as "Authorization: Bearer" on every /api/* call.
 *     • NEVER sent to the Mercure hub directly.
 *
 *   Mercure JWT (returned by this function):
 *     • Obtained via GET /api/mercure/token using the API JWT above.
 *     • Signed with HMAC-SHA256 (MERCURE_JWT_SECRET). Contains subscribe topics.
 *     • Sent ONLY to the Mercure hub when opening the EventSource connection.
 *     • Has nothing to do with user identity in Symfony.
 *
 * ─────────────────────────────────────────────────────────────────────────
 *
 * @param {number} paymentId
 * @returns {{ token: string, hub_url: string, topics: string[] }}
 */
export const getMercureToken = async (paymentId) => {
  const response = await apiClient.get('/mercure/token', {
    params: { payment_id: paymentId },
  });
  return response.data;
};

export const getPaymentHistory = async (filters = {}) => {
  const params = new URLSearchParams(filters).toString();
  const response = await apiClient.get(`/payments?${params}`);
  return response.data;
};
```

### `hooks/usePaymentStatus.js` (Parent app — real-time SSE)

```javascript
import { useCallback, useEffect, useRef, useState } from 'react';
import { EventSource } from 'react-native-sse';
import { getMercureToken } from '../api/payment';

/**
 * Subscribes to real-time payment status updates via Mercure SSE.
 *
 * Authentication flow (two steps, two different tokens):
 *
 *   Step 1 — GET /api/mercure/token?payment_id={id}
 *     The apiClient interceptor attaches the stored API JWT automatically.
 *     The server verifies the user owns the payment and returns a
 *     short-lived Mercure subscriber JWT (signed with MERCURE_JWT_SECRET).
 *
 *   Step 2 — EventSource to Mercure hub
 *     The Mercure JWT from step 1 is sent in the Authorization header.
 *     This is a DIFFERENT token from the API JWT — do not confuse them.
 *
 * @param {number|null} paymentId
 * @returns {{ status: object|null, loading: boolean, error: string|null }}
 */
export const usePaymentStatus = (paymentId) => {
  const [status, setStatus]   = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState(null);
  const esRef                 = useRef(null);

  const connect = useCallback(async () => {
    if (!paymentId) return;

    try {
      // Step 1: exchange API JWT → Mercure subscriber JWT.
      // apiClient adds the stored API JWT automatically via its interceptor.
      const {
        token:   mercureToken,   // Mercure JWT — NOT the API JWT
        hub_url: hubUrl,
        topics,
      } = await getMercureToken(paymentId);

      // Step 2: open the SSE connection using the Mercure JWT.
      const url = new URL(hubUrl);
      topics.forEach((t) => url.searchParams.append('topic', t));

      const es = new EventSource(url.toString(), {
        headers: {
          // This header goes to the Mercure hub, not to our API.
          Authorization: `Bearer ${mercureToken}`,
        },
      });

      es.addEventListener('message', (event) => {
        setStatus(JSON.parse(event.data));
        setLoading(false);
      });

      es.addEventListener('error', (err) => {
        console.error('Mercure SSE error:', err);
        setError('Real-time connection lost. Refresh to retry.');
        es.close();
      });

      esRef.current = es;
    } catch (err) {
      console.error('Failed to obtain Mercure token:', err);
      setError('Could not establish real-time connection.');
      setLoading(false);
    }
  }, [paymentId]);

  useEffect(() => {
    connect();
    return () => esRef.current?.close();
  }, [connect]);

  return { status, loading, error };
};
```

Usage:

```javascript
// screens/PaymentScreen.js
import React from 'react';
import { ActivityIndicator, Text, View } from 'react-native';
import { usePaymentStatus } from '../hooks/usePaymentStatus';

const PaymentScreen = ({ route }) => {
  const { paymentId } = route.params;

  // No jwtToken prop — the hook fetches its own Mercure token internally.
  const { status, loading, error } = usePaymentStatus(paymentId);

  if (loading) return <ActivityIndicator size="large" />;
  if (error)   return <Text style={{ color: 'red' }}>{error}</Text>;

  return (
    <View>
      <Text>Payment Status: {status?.status}</Text>
      <Text>Amount: ${status?.amount} {status?.currency}</Text>
      {status?.status === 'approved' && (
        <Text>Paid at: {new Date(status.paid_at).toLocaleString()}</Text>
      )}
    </View>
  );
};
```

### `api/oauth.js` (Driver app — one-time MP authorisation)

```javascript
import apiClient from './client';
import { Linking } from 'react-native';

/**
 * Start the Mercado Pago OAuth flow for a driver.
 * Opens the MP authorisation page in the device browser.
 * After the driver grants access, MP redirects to the callback URL
 * configured in MERCADOPAGO_OAUTH_REDIRECT_URI.
 */
export const connectMercadoPago = async () => {
  const response = await apiClient.get('/oauth/mercadopago/connect');
  await Linking.openURL(response.data.redirect_url);
};

/**
 * Check whether the current driver has connected their MP account.
 *
 * @returns {{ connected: boolean, mp_account_id: string|null, expires_at: string|null }}
 */
export const getMpConnectionStatus = async () => {
  const response = await apiClient.get('/oauth/mercadopago/status');
  return response.data;
};
```

Usage in the driver onboarding screen:

```javascript
import React, { useEffect, useState } from 'react';
import { Button, Text, View } from 'react-native';
import { connectMercadoPago, getMpConnectionStatus } from '../api/oauth';

const MpConnectScreen = () => {
  const [status, setStatus] = useState(null);

  useEffect(() => {
    getMpConnectionStatus().then(setStatus);
  }, []);

  return (
    <View>
      {status?.connected ? (
        <Text>Connected — account {status.mp_account_id}</Text>
      ) : (
        <>
          <Text>Connect your Mercado Pago account to receive payments.</Text>
          <Button title="Connect Mercado Pago" onPress={connectMercadoPago} />
        </>
      )}
    </View>
  );
};
```

### Complete parent payment flow

```javascript
// Put it all together: create → open checkout → subscribe to real-time updates
import React, { useState } from 'react';
import { Button, Text, View } from 'react-native';
import { initiatePayment } from '../api/payment';
import { usePaymentStatus } from '../hooks/usePaymentStatus';

const PayNowScreen = ({ driverId, studentIds }) => {
  const [paymentId, setPaymentId] = useState(null);
  const { status, loading, error } = usePaymentStatus(paymentId);

  const handlePay = async () => {
    try {
      const id = await initiatePayment(
        driverId,
        studentIds,
        3500,
        'Transporte escolar — febrero 2026',
      );
      setPaymentId(id);
      // Mercure SSE connection starts automatically via usePaymentStatus
    } catch (err) {
      console.error('Payment initiation failed:', err);
    }
  };

  if (!paymentId) {
    return <Button title="Pay ARS 3,500" onPress={handlePay} />;
  }

  if (loading) return <Text>Waiting for payment confirmation...</Text>;
  if (error)   return <Text style={{ color: 'red' }}>{error}</Text>;

  return (
    <View>
      <Text>Status: {status?.status}</Text>
    </View>
  );
};
```

---

## Configuration

### Environment Variables (`.env`)

```bash
###> mercadopago/payment ###

# Mercado Pago platform credentials (from your MP developer panel)
MERCADOPAGO_ACCESS_TOKEN=TEST-your-platform-access-token
MERCADOPAGO_WEBHOOK_SECRET=your-webhook-secret

# Marketplace OAuth (required for per-driver payments)
# APP_ID and APP_SECRET are from "Your integrations" in the MP developer panel
MERCADOPAGO_APP_ID=
MERCADOPAGO_APP_SECRET=
# Full public URL that MP redirects to after driver authorization
MERCADOPAGO_OAUTH_REDIRECT_URI=https://your-domain.com/oauth/mercadopago/callback

# Platform fee percentage (e.g. 2.5 = 2.5%). Set to 0 for no platform fee.
MERCADOPAGO_MARKETPLACE_FEE_PERCENT=0

###< mercadopago/payment ###

# 32-byte encryption key for driver OAuth tokens in the DB.
# Generate once: php -r "echo base64_encode(random_bytes(32));"
# Never change in production — doing so invalidates all stored tokens.
TOKEN_ENCRYPTION_KEY=

# RabbitMQ — phpamqplib DSN for the async_webhooks Messenger transport.
# Vhost "/" must be URL-encoded as "%2f".
RABBITMQ_DSN=phpamqplib://guest:guest@rabbitmq:5672/%2f/webhooks

###> symfony/mercure-bundle ###
MERCURE_URL=https://your-domain.com/.well-known/mercure
MERCURE_PUBLIC_URL=https://your-domain.com/.well-known/mercure
# Used to sign Mercure subscriber JWTs (completely separate from JWT_SECRET_KEY)
MERCURE_JWT_SECRET="change-this-to-a-strong-secret"
###< symfony/mercure-bundle ###
```

### Webhook Setup in Mercado Pago Dashboard

1. Go to: https://www.mercadopago.com/developers/panel/notifications/webhooks
2. Add webhook URL: `https://your-domain.com/api/webhooks/mercadopago`
3. Subscribe to events: `payment.created`, `payment.updated`
4. Copy the generated secret into `MERCADOPAGO_WEBHOOK_SECRET`

---

## Deployment

### 1. Run All Migrations

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 2. Start the Doctrine Worker (async transport — email, SMS, subscriptions)

```bash
docker compose exec php php bin/console messenger:consume async --time-limit=3600 -vv
```

### 3. Start the RabbitMQ Worker (payment webhooks)

```bash
docker compose exec php php bin/console messenger:consume async_webhooks --time-limit=3600 -vv
```

**Supervisord configuration (production):**

```ini
[program:messenger_async]
command=php bin/console messenger:consume async --time-limit=3600
directory=/var/www/html
autostart=true
autorestart=true
numprocs=2

[program:messenger_webhooks]
command=php bin/console messenger:consume async_webhooks --time-limit=3600
directory=/var/www/html
autostart=true
autorestart=true
numprocs=2

[program:scheduler_worker]
command=php bin/console messenger:consume scheduler_default --time-limit=3600
directory=/var/www/html
autostart=true
autorestart=true
numprocs=1
```

### 4. Clear Cache

```bash
docker compose exec php php bin/console cache:clear --env=prod
```

---

## Testing

### Manual Checklist

- [ ] Driver OAuth: connect, verify `mp_account_id` stored
- [ ] Create payment preference (with `driver_id`)
- [ ] Complete payment in MP sandbox
- [ ] Verify webhook received (check logs: `docker compose logs php | grep webhook`)
- [ ] Verify `ProcessWebhookMessageHandler` ran (check RabbitMQ management UI)
- [ ] Check payment status updated in DB
- [ ] Verify Mercure real-time update sent (check browser EventSource)
- [ ] `GET /api/mercure/token` returns a different JWT from the API auth JWT
- [ ] Test idempotency: submit same `idempotency_key` twice
- [ ] Test refund flow
- [ ] Test rate limiting (>10 requests/min)

### Mercado Pago Test Cards (sandbox)

```
Approved:  5031 7557 3453 0604  (CVV: 123, Exp: 11/25)
Rejected:  5031 4332 1540 6351
```

---

## Security Checklist

- [x] Never store credit card data — all card handling by Mercado Pago
- [x] Validate all webhook signatures (HMAC-SHA256)
- [x] Replay-attack prevention (5-minute timestamp window)
- [x] OAuth tokens encrypted at rest (libsodium secretbox)
- [x] CSRF state tokens for OAuth callback (single-use, 10-min TTL)
- [x] Rate limiting on payment endpoints (10 req/min/IP)
- [x] Idempotency keys required (UUID v4)
- [x] HTTPS only (TLS via Caddy)
- [x] Comprehensive audit logging in `payment_transaction`
- [x] Mercure updates are `private: true` (hub enforces subscriber JWT)

---

## Monitoring & Metrics

**Key metrics:**
- `payments.created`, `payments.approved`, `payments.failed`
- `payment.processing_time` histogram
- RabbitMQ queue depth for `async_webhooks`

**Alerts to configure:**
- Payment failure rate > 5 %
- Average webhook processing time > 10 s
- RabbitMQ queue depth growing (worker may be down)
- Webhook signature failures (possible spoofing attempt)
- MP OAuth token refresh failures

---

## Additional Resources

- [Mercado Pago Marketplace docs](https://www.mercadopago.com/developers/en/docs/marketplaces/introduction)
- [MP OAuth guide](https://www.mercadopago.com/developers/en/docs/security/oauth/introduction)
- [MP Webhooks guide](https://www.mercadopago.com/developers/en/docs/your-integrations/notifications/webhooks)
- [Symfony Messenger docs](https://symfony.com/doc/current/messenger.html)
- [Mercure docs](https://mercure.rocks)

---

**Last updated**: February 20, 2026
**Status**: Production Ready
