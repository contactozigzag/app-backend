# Technical Task List

## Phase 1: Setup & Identity
[x] **Task 1.0: Public User Registration Endpoint**
- Configure User entity API to allow public POST access.
- Update security.yaml to permit unauthenticated POST /api/users.
- Enable client/app registration workflow.
- Link: Plan P1.0 | Req 1.0

[x] **Task 1.1: Database Schema Design**
- Design tables for `Schools`, `Users`, `Roles`, `Students`, `Parents`, `Drivers`, `Address`.
- Implement relationship mappings:
  - Student ↔ User (many-to-many, Student owns via `student_parent` join table)
  - User → Address (one-to-one, User owns via `address_id` FK)
  - School → Address (one-to-one, School owns via `address_id` FK)
  - User ↔ Driver (one-to-one, Driver owns)
  - Driver ↔ Vehicle (one-to-one, Driver owns)
- Link: Plan P1.1, P1.2 | Req 1.1, 1.2, 1.3, 1.4, 7.2

[x] **Task 1.2: Authentication & Authorization Setup**
- Implement JWT-based auth.
- Configure Role-Based Access Control (RBAC) middleware.
- Link: Plan P1.1 | Req 1.1

[x] **Task 1.3: Multi-school Context Logic**
- Implement middleware to filter data by school context.
- Link: Plan P1.1 | Req 1.2

## Phase 2: Core Routing Features
[x] **Task 2.1: Google API Integration**
- Setup API keys and clients for Places, Routes, and Distance Matrix.
- Link: Plan P2.1, P2.2 | Req 2.1, 7.1

[x] **Task 2.2: Address Validation Endpoint**
- Create API to validate and geocode addresses using Google Places.
- Link: Plan P2.1 | Req 7.1

[x] **Task 2.3: Route Optimization Algorithm**
- Implement TSP (Traveling Salesperson) solver for stop sequences.
- Link: Plan P2.2 | Req 2.2

[x] **Task 2.4: Route Template Management**
- CRUD for morning and afternoon route templates.
- Link: Plan P2.2 | Req 2.2

[x] **Task 2.5: Parent Route Stop Creation**
- Implement POST /api/route-stops endpoint for parents.
- Validate parent-student relationship and school associations.
- Set isConfirmed to false by default.
- Link: Plan P2.3 | Req 2.3

[x] **Task 2.6: Driver Route Stop Confirmation**
- Implement GET /api/route-stops/unconfirmed endpoint for drivers.
- Implement PATCH /api/route-stops/{id}/confirm endpoint.
- Implement PATCH /api/route-stops/{id}/reject endpoint.
- Update route optimization to filter by isActive AND isConfirmed.
- Link: Plan P2.3 | Req 2.4

[x] **Task 2.7: RouteStop Entity Enhancement**
- Add isConfirmed boolean field to RouteStop entity (default: false).
- Create database migration for new field.
- Update RouteStopRepository with filtering methods.
- Link: Plan P2.3 | Req 2.3, 2.4

## Phase 3: Driver & Real-time Tracking
[x] **Task 3.1: GPS Tracking API**
- Endpoint for drivers to post location updates (5-10s interval).
- Link: Plan P3.1 | Req 3.1

[x] **Task 3.2: Geofencing Engine**
- Logic to check vehicle coordinates against stop radiuses.
- Link: Plan P3.1 | Req 3.2

[x] **Task 3.3: Attendance & Manifest API**
- Endpoints for student check-in/check-out.
- Link: Plan P3.2 | Req 8.1

[x] **Task 3.4: Absence Reporting System**
- API for parents to report absences and trigger route recalculation.
- Link: Plan P3.2 | Req 8.2, 2.3

## Phase 4: Portals & UI Backend
[x] **Task 4.1: Parent Dashboard API**
- Aggregate active route status, bus location, and child status.
- Link: Plan P4.1 | Req 4.1

[x] **Task 4.2: School Admin Dashboard API**
- Statistics on active routes, driver status, and school-wide alerts.
- Link: Plan P4.2 | Req 4.1, 1.2

[x] **Task 4.3: Vehicle Entity & Driver Association**
- Create Vehicle entity with required fields and optional attributes.
- Associate vehicles to drivers and expose as API resources.
- Link: Plan P4.3 | Req 9.1

## Phase 5: Notifications
[x] **Task 5.1: Multi-provider Notification Service**
- Generic service for Push, SMS, and Email delivery.
- Link: Plan P5.1 | Req 5.1

[x] **Task 5.2: Event-driven Notification Triggers**
- Listen for "Arriving", "Picked Up", "Dropped Off" events to send alerts.
- Link: Plan P5.1 | Req 5.1, 3.2, 8.1

## Phase 6: Analytics & Testing
[x] **Task 6.1: Ride Logging & Archiving**
- Background job to archive completed route data for history.
- Link: Plan P6.1 | Req 4.2, 6.1

[x] **Task 6.2: Performance Report Generator**
- API to calculate on-time rates and efficiency metrics.
- Link: Plan P6.2 | Req 6.1

[x] **Task 6.3: End-to-End Safety Audit**
- Verification of check-in/check-out logs and child security.
- Link: Plan P6.1 | Req 8.1

## Phase 7: Administrative Tools
[x] **Task 7.1: Create User Console Command**
- Implement Symfony console command `app:create-user`.
- Accept email, password, firstName, lastName, phoneNumber, identificationNumber.
- Support --super-admin/-s flag for ROLE_SUPER_ADMIN creation.
- Include validation (8-10 digit ID, unique email/ID).
- Hash passwords securely and display created user info.
- Link: Plan P7.1 | Req 10.1

[x] **Task 7.2: Environment-Aware Makefile**
- Update Makefile to detect dev/prod environment from command arguments.
- Use --env-file .env.local only for dev environment.
- Apply to all docker compose commands (build, up, down, etc.).
- Link: Plan P7.2

[x] **Task 7.3: Entity Relationship Refinement**
- Update User-Address relationship from OneToMany to OneToOne.
- Add School-Address OneToOne relationship.
- Ensure User and School are owning sides with address_id FK.
- Remove bidirectional references from Address entity.
- Update all getter/setter methods accordingly.
- Link: Plan P1.2 | Req 1.4, 7.2

## Phase 8: Payment Processing Integration
[ ] **Task 8.1: Payment Entity Design**
- Create Payment entity with fields: id, user, students (ManyToMany), amount, currency, paymentMethod, status, paymentProviderId, preferenceId, metadata, idempotencyKey, createdAt, updatedAt, paidAt, expiresAt.
- Add PaymentStatus enum: PENDING, PROCESSING, APPROVED, REJECTED, CANCELLED, REFUNDED, PARTIALLY_REFUNDED.
- Create PaymentMethod enum: CREDIT_CARD, DEBIT_CARD, BANK_TRANSFER, DIGITAL_WALLET, CASH.
- Implement many-to-many relationship with Student entity (payment_student join table).
- Add JSON metadata field for Mercado Pago response data.
- Add encrypted field for sensitive data.
- Create PaymentRepository with custom queries for filtering and reporting.
- Link: Plan P8.1 | Req 11.1, 11.10

[ ] **Task 8.2: Subscription Entity Design**
- Create Subscription entity with fields: id, user, students (ManyToMany), planType, status, amount, currency, billingCycle, nextBillingDate, mercadoPagoSubscriptionId, createdAt, updatedAt, cancelledAt.
- Add SubscriptionStatus enum: ACTIVE, PAUSED, CANCELLED, EXPIRED, PAYMENT_FAILED.
- Add BillingCycle enum: WEEKLY, MONTHLY, QUARTERLY, YEARLY.
- Create SubscriptionRepository with billing date queries.
- Link: Plan P8.1, P8.7 | Req 11.4

[ ] **Task 8.3: PaymentTransaction Audit Entity**
- Create PaymentTransaction entity for audit trail: id, payment, eventType, status, providerResponse (JSON), idempotencyKey, createdAt.
- Add TransactionEvent enum: CREATED, APPROVED, REJECTED, REFUNDED, CANCELLED, WEBHOOK_RECEIVED.
- Store complete Mercado Pago responses for troubleshooting.
- Create PaymentTransactionRepository.
- Link: Plan P8.1 | Req 11.1

[ ] **Task 8.4: Payment Database Migration**
- Generate migration for Payment, Subscription, PaymentTransaction tables.
- Create indexes: idx_payments_user_status, idx_payments_provider_id, idx_payments_idempotency, idx_payments_created_at.
- Create indexes: idx_subscriptions_user_status, idx_subscriptions_next_billing.
- Create indexes: idx_transactions_payment_id, idx_transactions_created_at.
- Add foreign key constraints with proper cascade rules.
- Link: Plan P8.1 | Req 11.1

[ ] **Task 8.5: MercadoPagoService Implementation**
- Create MercadoPagoService in src/Service/Payment/.
- Initialize Mercado Pago SDK with access token from environment.
- Implement createPreference() method with item details and payer info.
- Implement getPaymentStatus() method with caching (1 min TTL).
- Implement refundPayment() method for full and partial refunds.
- Implement getPaymentDetails() method.
- Add error handling and exception mapping.
- Configure timeout and retry settings for HTTP client.
- Link: Plan P8.2 | Req 11.1, 11.5, 11.8

[ ] **Task 8.6: IdempotencyService Implementation**
- Create IdempotencyService in src/Service/Payment/.
- Implement processWithIdempotency() method using Redis.
- Add database fallback when Redis unavailable.
- Set TTL to 24 hours for idempotency keys.
- Store both success and failure results.
- Add logging for idempotency hits/misses.
- Handle concurrent requests gracefully with locks.
- Link: Plan P8.3 | Req 11.2

[ ] **Task 8.7: PaymentProcessor Orchestrator**
- Create PaymentProcessor in src/Service/Payment/.
- Implement createPayment() method with validation.
- Add multi-student bundle payment logic.
- Implement payment status synchronization.
- Add retry mechanism with exponential backoff (3 retries).
- Implement circuit breaker for Mercado Pago API.
- Cache payment preferences in Redis (30 min TTL).
- Link: Plan P8.2, P8.3 | Req 11.1, 11.10

[ ] **Task 8.8: WebhookValidator Implementation**
- Create WebhookValidator in src/Service/Payment/.
- Implement signature validation using x-signature and x-request-id headers.
- Validate timestamp to prevent replay attacks (5 min window).
- Use Mercado Pago public key for verification.
- Add logging for failed validation attempts.
- Link: Plan P8.4 | Req 11.3

[ ] **Task 8.9: Payment API Resource Configuration**
- Configure Payment entity as API Platform resource.
- Add custom operations for create-preference and status check.
- Implement security: IS_AUTHENTICATED for list/get, ROLE_USER for create.
- Add normalization groups for different contexts.
- Configure pagination (30 items per page).
- Link: Plan P8.5 | Req 11.1, 11.5

[ ] **Task 8.10: PaymentController Implementation**
- Create PaymentController in src/Controller/.
- Implement POST /api/payments/create-preference endpoint.
- Implement GET /api/payments/{id}/status endpoint.
- Implement GET /api/payments endpoint with filtering.
- Add rate limiting annotation (10 req/min per user).
- Validate user permissions (can only access own payments).
- Return standardized error responses.
- Link: Plan P8.5, P8.8 | Req 11.1, 11.5, 11.7

[ ] **Task 8.11: Webhook Endpoint Implementation**
- Create WebhookController in src/Controller/.
- Implement POST /api/webhooks/mercadopago endpoint.
- Validate webhook signature via WebhookValidator.
- Return 200 immediately after validation.
- Dispatch webhook message to RabbitMQ for async processing.
- Add extensive logging with webhook ID.
- Handle duplicate webhooks via idempotency.
- Link: Plan P8.4 | Req 11.3

[ ] **Task 8.12: Payment Message Handlers**
- Create ProcessPaymentMessage and ProcessPaymentHandler.
- Create HandleWebhookMessage and HandleWebhookHandler.
- Create SendPaymentNotificationMessage and SendPaymentNotificationHandler.
- Configure RabbitMQ routing for payment messages.
- Implement retry policy (3 retries with exponential backoff).
- Add dead letter queue for failed messages.
- Link: Plan P8.4 | Req 11.3

[ ] **Task 8.13: Payment Event System**
- Create PaymentCreatedEvent, PaymentApprovedEvent, PaymentFailedEvent, PaymentRefundedEvent.
- Create PaymentEventSubscriber to handle events.
- Dispatch events at appropriate lifecycle points.
- Add event logging for audit trail.
- Link: Plan P8.6 | Req 11.6

[ ] **Task 8.14: Mercure Payment Updates**
- Implement PaymentEventSubscriber for Mercure publishing.
- Publish to topic: /payments/{paymentId}.
- Include status, amount, timestamp in updates.
- Configure Mercure authorization for payment topics.
- Add JWT claims for payment access control.
- Link: Plan P8.6 | Req 11.6

[ ] **Task 8.15: Payment Notifications**
- Integrate payment events with NotificationService.
- Create payment notification templates (email/SMS/push).
- Trigger on: payment created, approved, failed, refunded.
- Include payment details and receipt link.
- Respect user notification preferences.
- Link: Plan P8.6 | Req 11.6

[ ] **Task 8.16: Subscription Processing Command**
- Create app:process-subscriptions console command.
- Query subscriptions with nextBillingDate <= today.
- Create payment for each due subscription.
- Handle payment failures with retry policy.
- Update nextBillingDate after successful payment.
- Send notifications for billing events.
- Link: Plan P8.7 | Req 11.4

[ ] **Task 8.17: Subscription Management Endpoints**
- Implement POST /api/subscriptions for creation.
- Implement GET /api/subscriptions for listing.
- Implement PATCH /api/subscriptions/{id}/cancel for cancellation.
- Implement PATCH /api/subscriptions/{id}/pause for pausing.
- Add validation for subscription changes.
- Link: Plan P8.7 | Req 11.4

[ ] **Task 8.18: Rate Limiting Configuration**
- Configure rate limiter for payment endpoints in config/packages/rate_limiter.yaml.
- Set limit: 10 requests per minute per user.
- Use Redis as storage backend.
- Return 429 Too Many Requests with Retry-After header.
- Add rate limit headers to all responses.
- Link: Plan P8.8 | Req 11.7

[ ] **Task 8.19: Payment Security Enhancements**
- Implement field encryption for Payment.metadata using sodium.
- Add comprehensive audit logging for all payment operations.
- Configure security headers for payment endpoints.
- Implement anomaly detection (unusual amounts, frequency).
- Add administrator alerting for suspicious activity.
- Link: Plan P8.8 | Req 11.7

[ ] **Task 8.20: Admin Refund Endpoint**
- Create AdminPaymentController in src/Controller/Admin/.
- Implement POST /api/admin/payments/{id}/refund.
- Validate ROLE_SCHOOL_ADMIN or ROLE_SUPER_ADMIN.
- Support full and partial refunds.
- Create PaymentTransaction record for refund.
- Send notification to user about refund.
- Link: Plan P8.9 | Req 11.8

[ ] **Task 8.21: Payment Reconciliation Endpoint**
- Implement GET /api/admin/payments/reconciliation endpoint.
- Accept date range parameters (from/to).
- Fetch transactions from Mercado Pago API.
- Compare with internal payment records.
- Generate discrepancy report (missing, mismatched).
- Export results as JSON/CSV.
- Link: Plan P8.9 | Req 11.9

[ ] **Task 8.22: Payment Testing Suite**
- Write unit tests for MercadoPagoService.
- Write unit tests for IdempotencyService.
- Write unit tests for PaymentProcessor.
- Write integration tests for payment creation flow.
- Write integration tests for webhook processing.
- Test idempotency under concurrent requests.
- Test retry logic and circuit breaker.
- Mock Mercado Pago API responses.
- Link: Plan P8.10

[ ] **Task 8.23: Payment Monitoring & Metrics**
- Configure Monolog for payment logging.
- Implement metrics collection (payments.created, payment.processing_time, payments.pending).
- Set up alerts for payment failures (>5% failure rate).
- Create dashboard queries for payment analytics.
- Add health check for Mercado Pago connectivity.
- Link: Plan P8.10

[ ] **Task 8.24: Environment Configuration**
- Add MERCADOPAGO_ACCESS_TOKEN to .env.
- Add MERCADOPAGO_PUBLIC_KEY to .env.
- Add MERCADOPAGO_WEBHOOK_SECRET to .env.
- Document configuration in README.
- Create .env.example with placeholder values.
- Link: Plan P8.2, P8.8
