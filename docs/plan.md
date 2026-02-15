# Implementation Plan

This document outlines the strategic approach for developing the School Transportation Management System.

## Phase 1: Foundation & Identity (Priority: High)
Establish the core infrastructure, multi-tenant database schema, and authentication systems.

- **P1.0 Public User Registration**
    - Configure User entity API to allow public POST access for registration.
    - Implement security rules to permit unauthenticated user creation.
    - Enable client/app registration workflow via POST /api/users.
    - *Links: Requirement 1.0*
- **P1.1 Multi-tenant User Management**
    - Implement Role-Based Access Control (RBAC).
    - Design schema for Schools, Users, Roles, and their associations.
    - *Links: Requirement 1.1, 1.2*
- **P1.2 Family & Student Profiles**
    - Develop Parent-Child relationship mapping (many-to-many with Student as owning side).
    - Implement Student profiles with associated school.
    - Implement User and School address relationships (one-to-one with User/School as owning sides).
    - *Links: Requirement 1.3, 1.4, 7.2*

## Phase 2: Location & Route Core (Priority: High)
Integrate external mapping services and build the routing engine.

- **P2.1 Geocoding & Address Management**
    - Integrate Google Places API for address validation.
    - Store geocoded coordinates for all stops and school campuses.
    - *Links: Requirement 7.1*
- **P2.2 Route Computation Engine**
    - Integrate Google Routes & Distance Matrix API.
    - Implement multi-stop sequence optimization.
    - Handle morning/afternoon route templates.
    - *Links: Requirement 2.1, 2.2*
- **P2.3 Parent-Driver Route Stop Workflow**
    - Implement parent route stop creation endpoint.
    - Add driver confirmation/rejection workflow for route stops.
    - Ensure optimization only includes confirmed stops.
    - *Links: Requirement 2.3, 2.4*

## Phase 3: Real-time Operations (Priority: High)
Enable live tracking and the driver's operational flow.

- **P3.1 Live Tracking Service**
    - Implement WebSocket or high-frequency polling for GPS updates.
    - Build geofencing logic for automatic arrival detection.
    - *Links: Requirement 3.1, 3.2*
- **P3.2 Driver Manifest & Attendance**
    - Implement check-in/check-out workflow for students.
    - Integrate absence management into the daily route manifest.
    - *Links: Requirement 8.1, 8.2*

## Phase 4: Portals & Dashboards (Priority: Medium)
Develop user interfaces for parents and school administrators.

- **P4.1 Parent Tracking Portal**
    - Live map view of the child's bus.
    - ETA displays and status updates.
    - *Links: Requirement 4.1*
- **P4.2 School Admin Console**
    - Overview of all active routes.
    - Management of schools, drivers, and vehicles.
    - *Links: Requirement 4.1, 1.2*
- **P4.3 Fleet & Vehicle Management**
    - Implement vehicle CRUD and driver assignment.
    - Surface vehicle capacity and profile data in admin workflows.
    - *Links: Requirement 9.1*

## Phase 5: Notifications & Communication (Priority: Medium)
Keep users informed via multiple channels.

- **P5.1 Notification Engine**
    - Integrate Push (FCM), SMS (Twilio), and Email (SendGrid/Mailgun).
    - Implement preference management system.
    - *Links: Requirement 5.1*

## Phase 6: Analytics & Compliance (Priority: Low)
Long-term data storage and performance reporting.

- **P6.1 History & Audit Logs**
    - Store detailed ride logs for every completed stop.
    - Implement audit trail for compliance.
    - *Links: Requirement 4.2, 6.1*
- **P6.2 Performance Analytics**
    - Build reporting dashboard for on-time rates and efficiency.
    - Driver and vehicle performance metrics.
    - *Links: Requirement 6.1*

## Phase 7: Administrative Tools (Priority: Medium)
Command-line tools for system administration and maintenance.

- **P7.1 CLI User Management**
    - Implement Symfony console command for user creation.
    - Support super admin creation for initial system setup.
    - Include validation and error handling.
    - *Links: Requirement 10.1*
- **P7.2 Environment-Aware Build System**
    - Update Makefile to support dev/prod environments.
    - Use .env.local for local development only.
    - Ensure proper environment variable handling for production.

## Phase 8: Payment Processing & Integration (Priority: High)
Integrate Mercado Pago for secure payment processing with emphasis on performance, scalability, and resilience.

- **P8.1 Payment Core Infrastructure**
    - Design and implement Payment, Subscription, and PaymentTransaction entities.
    - Establish database schema with proper indexing for performance.
    - Create many-to-many relationship between Payment and Student entities.
    - Implement encrypted fields for sensitive payment metadata.
    - *Links: Requirement 11.1, 11.4, 11.10*

- **P8.2 Mercado Pago Integration Layer**
    - Implement MercadoPagoService as SDK wrapper with error handling.
    - Build payment preference creation with support for multi-student bundles.
    - Implement payment status checking with caching strategy.
    - Add support for refunds and partial refunds via API.
    - Configure secure credential management via environment variables.
    - *Links: Requirement 11.1, 11.5, 11.8*

- **P8.3 Idempotency & Resilience**
    - Implement Redis-backed IdempotencyService for duplicate prevention.
    - Add database fallback for idempotency when Redis unavailable.
    - Build retry mechanism with exponential backoff for failed operations.
    - Implement circuit breaker pattern for Mercado Pago API calls.
    - Cache payment preferences and status in Redis (TTL: 30 min and 1 min respectively).
    - *Links: Requirement 11.2*

- **P8.4 Webhook Processing & Async Operations**
    - Create webhook endpoint with signature validation.
    - Implement WebhookValidator using Mercado Pago public key.
    - Build async webhook processing via RabbitMQ message handlers.
    - Implement retry logic for failed webhook processing (3 retries with backoff).
    - Ensure webhook endpoint returns 200 immediately per Mercado Pago requirements.
    - *Links: Requirement 11.3*

- **P8.5 Payment API Endpoints**
    - Implement POST /api/payments/create-preference for payment initialization.
    - Create GET /api/payments/{id}/status for real-time status checking.
    - Build GET /api/payments for listing user payment history.
    - Add GET /api/payments/{id} for detailed payment information.
    - Implement filtering by status, date range, and student.
    - *Links: Requirement 11.1, 11.5*

- **P8.6 Real-time Updates & Notifications**
    - Integrate Mercure for real-time payment status updates to mobile apps.
    - Implement PaymentEventSubscriber for publishing status changes.
    - Build payment notification system via Symfony Notifier (Push, Email, SMS).
    - Create event classes: PaymentCreatedEvent, PaymentApprovedEvent, PaymentFailedEvent, PaymentRefundedEvent.
    - *Links: Requirement 11.6*

- **P8.7 Subscription Management**
    - Implement subscription entity with billing cycle tracking.
    - Build cron job for processing recurring billing (app:process-subscriptions).
    - Add subscription cancellation workflow with grace period.
    - Implement failed payment retry policy for subscriptions.
    - Create subscription management API endpoints.
    - *Links: Requirement 11.4*

- **P8.8 Security & Rate Limiting**
    - Configure rate limiting for payment endpoints (10 req/min per user).
    - Implement comprehensive audit logging for all payment operations.
    - Add encryption for sensitive payment metadata fields.
    - Ensure HTTPS/TLS 1.2+ for all Mercado Pago communications.
    - Implement anomaly detection and administrator alerting.
    - *Links: Requirement 11.7*

- **P8.9 Admin Tools & Reconciliation**
    - Build POST /api/admin/payments/{id}/refund for refund processing.
    - Implement GET /api/admin/payments/reconciliation for payment reconciliation.
    - Create discrepancy reporting and resolution workflow.
    - Add admin dashboard for payment monitoring and analytics.
    - Implement payment export functionality for accounting.
    - *Links: Requirement 11.8, 11.9*

- **P8.10 Testing & Monitoring**
    - Implement comprehensive unit tests for payment services.
    - Create integration tests for Mercado Pago API interactions.
    - Build end-to-end tests for complete payment flows.
    - Configure monitoring metrics (payments.created, payment.processing_time, payments.pending).
    - Set up alerting for payment failures and anomalies.
    - Test idempotency under concurrent requests and network failures.
