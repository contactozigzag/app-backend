# ZigZag School Transportation Management System

A comprehensive, real-time school transportation management system designed to streamline and secure student transport operations. Built with modern PHP/Symfony, featuring multi-tenancy, real-time GPS tracking, route optimization, async event pipelines, driver safety alerts, and special event routing.

## 🎯 Overview

ZigZag provides schools, parents, and drivers with a complete solution for managing school bus operations. The system ensures student safety through real-time tracking, automated notifications, GPS anomaly detection, and a full driver distress & emergency response system.

### Key Highlights

- **Multi-tenant Architecture**: Complete data isolation per school
- **Async GPS Pipeline**: Rate-limited GPS ingestion → Redis cache → RabbitMQ fanout → geofencing + Mercure + proximity evaluation
- **Driver Distress System**: Manual SOS button + automatic GPS-silence detection; nearest drivers notified via Mercure within seconds
- **Emergency Chat**: End-to-end encrypted real-time chat thread attached to each distress alert
- **Special Event Routes**: Full lifecycle management for field trips and sports events — three route modes, two departure modes, live student-ready re-sequencing
- **Route Optimization**: Intelligent route planning with Google Maps integration
- **Multi-channel Notifications**: Push, SMS, and Email alerts
- **Payment Integration**: Mercado Pago Marketplace model with driver-defined rates and real-time SSE status updates
- **Safety First**: Check-in/check-out logging, safety audits, and automated anomaly detection

## 🏗️ Technology Stack

### Backend Framework
- **PHP 8.5** — Modern PHP with strict types and performance improvements
- **Symfony 8.0** — Enterprise-grade PHP framework
- **API Platform 4.2** — REST API development framework
- **Doctrine ORM** — Database abstraction and entity management

### Database & Caching
- **MySQL 8.4** — Primary relational database
- **Redis 8.4** — GPS location cache (15s TTL), rate limiter storage, OAuth idempotency keys

### Message Queue & Async
- **RabbitMQ 4.2** — Three transports: `async` (general), `async_webhooks` (payment), `async_tracking` (GPS pipeline)
- **Symfony Messenger 8.0** — Message bus with async handlers and retry logic
- **Symfony Scheduler 8.0** — Recurring jobs (anomaly detection every 60 s, subscription billing every 5 min)
- **Symfony Lock 8.0** — Distributed debounce lock for individual departure mode

### Real-time
- **Symfony Mercure 0.7** — Server-Sent Events for GPS tracking, distress alerts, and chat

### External Services
- **Google Maps APIs** — Places, Routes, Distance Matrix
- **Firebase Cloud Messaging (FCM)** — Push notifications
- **SMS Provider** — Configurable SMS channel
- **Symfony Mailer** — Email notifications
- **Mercado Pago** — Payment processing (Marketplace + OAuth model)

### Authentication & Security
- **JWT (LexikJWTAuthenticationBundle)** — Stateless API authentication; RSA-256, 2-hour TTL
- **Refresh Tokens (GesdinetJWTRefreshTokenBundle)** — Single-use rotating refresh tokens; 30-day TTL, stored in MySQL
- **Custom Security Voter** — `RouteManagementVoter` for runtime driver privilege elevation
- **RBAC** — Hierarchical role-based access control
- **Multi-tenant Filtering** — Automatic school-based Doctrine filter
- **libsodium secretbox** — Driver OAuth token encryption and chat message encryption

### Infrastructure
- **Traefik v3** — Reverse proxy and TLS termination (Let's Encrypt in prod, plain HTTP in dev)
- **OpenSearch 2.x** — Full-text search engine (single-node, security plugin disabled — Docker network isolation)
- **OpenSearch Dashboards** — OpenSearch management UI (dev only)

### Dev & Quality Tools
- **Docker & Docker Compose** — Containerized development
- **FrankenPHP** — High-performance PHP server (worker mode)
- **Caddy** — Embedded in FrankenPHP for Mercure hub
- **Symfony UID 8.0** — UUID v4 generation for alert identifiers
- **PHPStan** — Static analysis at level 9
- **Rector** — Automated code modernization
- **ECS (Easy Coding Standard)** — Code style enforcement

## 📐 Architecture

### System Architecture

```
┌─────────────────┐
│  React Native   │
│  Mobile Apps    │
│  (iOS/Android)  │
└────────┬────────┘
         │ HTTP (dev) / HTTPS (prod)
         │
┌────────▼────────┐
│   Traefik v3    │  ← single entry-point reverse proxy
│  (TLS in prod)  │    Let's Encrypt ACME in prod; plain HTTP in dev
└────────┬────────┘
         │ Docker internal networking (HTTP)
         │
┌────────▼─────────────────────────────────────────┐
│        FrankenPHP + Caddy (Symfony 8)            │
│  ┌─────────────────────────────────────────────┐ │
│  │  JWT Authentication & Authorization         │ │
│  │  Multi-tenant Context Filtering             │ │
│  │  RouteManagementVoter (runtime flag)        │ │
│  │  Mercure SSE Hub (Caddy module)             │ │
│  └─────────────────────────────────────────────┘ │
│                                                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │  User    │  │  Route   │  │  Safety  │      │
│  │  Service │  │  Service │  │  Service │      │
│  └──────────┘  └──────────┘  └──────────┘      │
│                                                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │ Tracking │  │Distress/ │  │ Payment  │      │
│  │ Pipeline │  │  Chat    │  │ Service  │      │
│  └──────────┘  └──────────┘  └──────────┘      │
└───────────────────┬───────────────────────────────┘
                    │
    ┌───────────────┼────────────────┐
    │               │                │
┌───▼────┐    ┌────▼────┐    ┌─────▼─────┐
│ MySQL  │    │  Redis  │    │ RabbitMQ  │
│   DB   │    │  Cache  │    │  Queues   │
└────────┘    └─────────┘    └───────────┘
                    │
    ┌───────────────┼────────────────┐
    │                                │
┌───▼──────────┐          ┌─────────▼──┐
│   Mercure    │          │ OpenSearch  │
│   SSE Hub    │          │  (search)   │
└──────────────┘          └────────────┘
```

### Async GPS Tracking Pipeline

```
Driver POST /api/tracking/location
         │
         ▼ (rate check: 1 req / 3 s per driver)
   TrackingController
         ├── persist LocationUpdate to MySQL
         ├── update ActiveRoute.currentLat/Lng in MySQL
         ├── cacheLocation() → Redis (15 s TTL)
         └── dispatch DriverLocationUpdatedMessage → async_tracking (RabbitMQ)
                            │
             ┌─────────────┼──────────────────┐
             ▼             ▼                  ▼
  GeofenceEvaluation  MercurePublish   ProximityEvaluation
     Handler             Handler            Handler
  (checkActiveRoute)  (/tracking/       (future proximity
                       driver/{id},      business logic)
                       /tracking/
                       route/{id})
```

### Distress Signal Flow

```
Manual: Driver POST /api/routes/sessions/{id}/distress
Auto:   DetectGpsAnomalyHandler (every 60 s via Scheduler)
         └── GPS silence > 2 min on in-progress route
                    │
                    ▼
             Create DriverAlert (PENDING)
             dispatch DriverDistressMessage → async
                    │
                    ▼
           DriverDistressHandler
             ├── load in-progress drivers' Redis positions
             ├── Haversine filter ≤ DISTRESS_PROXIMITY_KM (default 5 km)
             ├── Mercure → /alerts/driver/{nearbyDriverId}
             ├── Mercure → /alerts/admin/{schoolId}
             └── store nearbyDriverIds on DriverAlert

Driver responds: POST /api/driver-alerts/{alertId}/respond
             └── status PENDING → RESPONDED
             └── Mercure → /alerts/driver/{distressedDriverId}

Resolve: POST /api/driver-alerts/{alertId}/resolve
             └── status → RESOLVED (distressed/responding driver or school admin)
             └── chat becomes read-only
```

### Database Schema Overview

```
Schools
  ├── Address (one-to-one)
  ├── Users (Parents, Drivers, Admins)
  │   └── Address (one-to-one)
  ├── Students
  │   └── Parents (many-to-many with Users)
  └── Routes
      ├── RouteStops
      └── ActiveRoutes
          ├── ActiveRouteStops
          ├── LocationUpdates
          ├── Attendance Records
          └── DriverAlerts                       ← NEW
              ├── ChatMessages                   ← NEW
              └── nearbyDriverIds (JSON)

DriverAlerts
  ├── distressedDriver: Driver
  ├── respondingDriver: Driver (nullable)
  ├── routeSession: ActiveRoute (nullable)
  ├── locationLat / locationLng (snapshot)
  ├── status: PENDING | RESPONDED | RESOLVED
  ├── triggeredAt, resolvedAt, resolvedBy
  └── nearbyDriverIds: JSON array

ChatMessages
  ├── alert: DriverAlert
  ├── sender: User
  ├── content: TEXT (encrypted — XSalsa20-Poly1305)
  ├── sentAt: DateTimeImmutable
  └── readBy: JSON array of user IDs

SpecialEventRoutes                               ← NEW
  ├── school: School
  ├── students: ManyToMany (special_event_route_student)
  ├── assignedDriver / assignedVehicle
  ├── eventType / routeMode / departureMode (enums)
  ├── status: DRAFT|PUBLISHED|IN_PROGRESS|COMPLETED|CANCELLED
  └── SpecialEventRouteStops                     ← NEW
        ├── student, address, stopOrder
        ├── isStudentReady, readyAt
        └── status: pending|approaching|arrived|skipped
```

### Mercure Topic Map

| Topic | Privacy | Published by | Subscribers |
|-------|---------|--------------|-------------|
| `/tracking/driver/{driverId}` | public | `MercurePublishHandler` | parents, admins |
| `/tracking/route/{routeId}` | public | `MercurePublishHandler`, `TripMercureSubscriber` | parents on that route |
| `/alerts/driver/{driverId}` | public | `DriverDistressHandler`, `DriverAlertController` | affected drivers |
| `/alerts/admin/{schoolId}` | public | `DriverDistressHandler` | school admins |
| `/chat/alert/{alertId}` | private | `ChatMessagePublishHandler` | alert participants only |
| `/payments/{paymentId}` | private | `PaymentEventSubscriber` | paying parent |
| `/api/users/{userId}/notifications` | private | `TripMercureSubscriber`, `PaymentEventSubscriber`, `RouteStopNotificationPublisher` | authenticated user (own topic only) |

### Multi-tenant Data Isolation

- **Doctrine Filter** — Automatically filters every query by school context
- **Event Subscriber** — Enables filter on each request based on authenticated user
- **Super Admin Override** — System administrators can access cross-school data

## 🔑 Driver Route Management Flag

By default, only `ROLE_SCHOOL_ADMIN` can create/delete `ActiveRoute` records and access
route-planning endpoints. Setting `DRIVER_ROUTE_MANAGEMENT_ENABLED=true` in the environment
grants drivers the same route management capabilities without any code changes.

### Covered Actions

| Endpoint / Entity | Default Guard | With Flag |
|---|---|---|
| `POST /api/active_routes` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `DELETE /api/active_routes/{id}` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `GET /api/absences/date/{date}` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `POST /api/absences/recalculate-pending` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `POST /api/geofencing/check-all` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `GET /api/tracking/location/driver/{id}/history` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `GET/POST /api/special-event-routes` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `GET/PATCH/DELETE /api/special-event-routes/{id}` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `POST /api/special-event-routes/{id}/publish` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `POST /api/special-event-routes/{id}/start-outbound` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `POST /api/special-event-routes/{id}/arrive-at-event` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `POST /api/special-event-routes/{id}/start-return` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `POST /api/special-event-routes/{id}/complete` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `PATCH /api/route-stops/{id}` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |
| `DELETE /api/route-stops/{id}` | `ROLE_SCHOOL_ADMIN` | + `ROLE_DRIVER` |

School CRUD, billing, audit, and dashboard endpoints remain admin-only regardless of the flag.

```bash
# .env.local
DRIVER_ROUTE_MANAGEMENT_ENABLED=true
```

The `RouteManagementVoter` (`src/Security/Voter/RouteManagementVoter.php`) implements this check via the custom `ROUTE_MANAGE` security attribute.

## 🚀 Features

### Phase 1: Identity & Access Management ✅

**Multi-tenant User Management**
- Role-based access control (RBAC) with hierarchical roles
- JWT-based authentication
- Automatic school context filtering
- Support for multiple user roles: Parent, Driver, School Admin, Super Admin

**Entities:** School, User, Student, Driver, Vehicle, Address

### Phase 2: Route Planning & Optimization ✅

**Route Management**
- Morning and afternoon route templates
- Route optimization with stop sequencing
- Google Maps integration for routing
- Estimated time and distance calculations
- Parent-initiated route stop workflow with driver confirmation

**Entities:** Route, RouteStop

**Parent-Driver Route Stop Workflow:**
1. Parents create stops via `POST /api/route-stops` (status: unconfirmed)
2. Drivers review via `GET /api/route-stops/unconfirmed`
3. Drivers confirm (`PATCH /api/route-stops/{id}/confirm`) or reject
4. Only `isActive=true AND isConfirmed=true` stops enter route optimization

### Phase 3: Real-time Tracking & Operations ✅

**Live GPS Tracking (now async — see Phase 7)**
- Async GPS ingestion via `/api/tracking/location` (rate-limited at 1 req / 3 s per driver)
- Real-time bus position via Mercure SSE
- Geofencing for automatic arrival detection (triggered per-update)
- Location history storage

**Attendance & Manifest**
- Student check-in/check-out workflow
- Timestamped records with GPS coordinates
- Absence reporting and automatic route recalculation

**Entities:** ActiveRoute, ActiveRouteStop, LocationUpdate, Attendance, Absence

### Phase 4: Dashboards & Portals ✅

- `GET /api/parent/dashboard` — child status, bus location, ETA, attendance
- `GET /api/school-admin/dashboard` — active routes, driver locations, alerts, metrics

### Phase 5: Notifications ✅

**Multi-provider:** Email, SMS, Push (FCM)

**Events:** BusArrivingEvent, StudentPickedUpEvent, StudentDroppedOffEvent, RouteStartedEvent, RouteCompletedEvent

**Entities:** NotificationPreference

### Phase 6: Analytics & Safety Audits ✅

**Route Archiving:** Background archiving of completed routes with performance metrics

**Performance APIs:** `/api/reports/performance`, `/efficiency`, `/top-performing`, `/comparative`

**Safety Audit:** `GET /api/safety/audit` — end-to-end check-in/check-out validation, anomaly detection, safety scoring (0–100)

**Entities:** ArchivedRoute

### Phase 7: Async GPS Tracking Pipeline ✅

**What changed from Phase 3:**
- GPS ingestion is now fully decoupled — the HTTP response returns immediately after persisting and caching; all side-effects are async
- **Rate limiter** — 1 request per 3 seconds per driver (keyed by driver ID, not IP)
- **Redis cache** — latest position stored with 15 s TTL; `GET /api/tracking/location/driver/{id}` reads Redis first, falls back to DB
- **Three async handlers** on the `async_tracking` RabbitMQ transport:
  - `GeofenceEvaluationHandler` — triggers geofencing check for the active route
  - `MercurePublishHandler` — publishes to `/tracking/driver/{id}` and `/tracking/route/{id}`
  - `ProximityEvaluationHandler` — placeholder for proximity business logic
- **GeoCalculatorService** — standalone Haversine service used by geofencing, distress proximity, and route optimization

**New Files:**
- `src/Service/GeoCalculatorService.php`
- `src/Service/DriverLocationCacheService.php`
- `src/Message/DriverLocationUpdatedMessage.php`
- `src/MessageHandler/GeofenceEvaluationHandler.php`
- `src/MessageHandler/MercurePublishHandler.php`
- `src/MessageHandler/ProximityEvaluationHandler.php`

### Phase 8: Driver Distress & Safety System ✅

**Manual SOS:** Any driver on an in-progress route can trigger a distress signal via `POST /api/routes/sessions/{id}/distress`.

**Automatic GPS Anomaly Detection:** The Symfony Scheduler fires `DetectGpsAnomalyMessage` every 60 seconds. For each in-progress route where no GPS data has been received for more than 2 minutes, a `DriverAlert` is automatically created and the distress pipeline is triggered.

**Proximity Alerts:** `DriverDistressHandler` reads all active drivers' Redis positions, runs Haversine filtering within `DISTRESS_PROXIMITY_KM` (default 5 km), and pushes Mercure alerts to each nearby driver and to the school admin topic.

**Alert Lifecycle:**
```
PENDING → (nearby driver responds) → RESPONDED → (anyone resolves) → RESOLVED
```

**Entities:** DriverAlert (`src/Entity/DriverAlert.php`)

**Enums:** `AlertStatus` (PENDING, RESPONDED, RESOLVED)

**New Files:**
- `src/Entity/DriverAlert.php`
- `src/Repository/DriverAlertRepository.php`
- `src/Message/DriverDistressMessage.php`
- `src/Message/DetectGpsAnomalyMessage.php`
- `src/MessageHandler/DriverDistressHandler.php`
- `src/MessageHandler/DetectGpsAnomalyHandler.php`
- `src/Controller/DistressController.php`
- `src/Controller/DriverAlertController.php`

### Phase 9: Ephemeral Emergency Chat ✅

Each `DriverAlert` has an attached chat thread that is live while the alert is PENDING or RESPONDED and becomes read-only on RESOLVED.

**Access control:** Distressed driver's user, responding driver's user, and school admins are participants. All others receive 403.

**Encryption:** Message content is encrypted at rest using the same `TokenEncryptor` (XSalsa20-Poly1305 via libsodium) used for Mercado Pago OAuth tokens.

**Real-time delivery:** `ChatMessagePublishHandler` decrypts the content and publishes to the private Mercure topic `/chat/alert/{alertId}` after each new message.

**Entities:** ChatMessage (`src/Entity/ChatMessage.php`)

**New Files:**
- `src/Entity/ChatMessage.php`
- `src/Repository/ChatMessageRepository.php`
- `src/Message/ChatMessageCreatedMessage.php`
- `src/MessageHandler/ChatMessagePublishHandler.php`
- `src/Controller/ChatController.php`

### Phase 10: Special Event Routes ✅

Manage field trips, sports events, and other out-of-school-day transport.

**Three Route Modes (`RouteMode`):**
- `FULL_DAY_TRIP` — outbound to event + return to home addresses
- `RETURN_TO_SCHOOL` — return from event to school only
- `ONE_WAY` — outbound only; auto-completes on arrival

**Two Departure Modes (`DepartureMode`, only for `FULL_DAY_TRIP`):**
- `GROUPED` — all students depart together on the return trip
- `INDIVIDUAL` — students are marked ready one by one; the route is re-sequenced dynamically

**Lifecycle:** DRAFT → PUBLISHED → IN_PROGRESS → COMPLETED (or CANCELLED)

**Auto-stop generation:** On `publish`, stops are created from enrolled students' parent addresses.

**Individual Departure Re-sequencing:** When a driver marks a student as ready (`POST /api/special-event-routes/{id}/students/{studentId}/ready`), a `StudentReadyForPickupMessage` is dispatched with a 30-second `DelayStamp` for debouncing. `StudentReadyForPickupHandler` acquires a distributed lock, re-sequences all pending ready stops via `RouteOptimizationService`, and publishes the updated route to the driver's Mercure topic.

**Entities:** SpecialEventRoute, SpecialEventRouteStop

**Enums:** `EventType`, `RouteMode`, `DepartureMode`, `SpecialEventRouteStatus`

**Key Files:**
- `src/Entity/SpecialEventRoute.php` — `#[ApiResource]` with `ROUTE_MANAGE` security
- `src/Entity/SpecialEventRouteStop.php`
- `src/Repository/SpecialEventRouteRepository.php`
- `src/Repository/SpecialEventRouteStopRepository.php`
- `src/State/SpecialEventRoute/SpecialEventRouteCollectionProvider.php`
- `src/State/SpecialEventRoute/SpecialEventRouteCreateProcessor.php`
- `src/State/SpecialEventRoute/SpecialEventRouteUpdateProcessor.php`
- `src/State/SpecialEventRoute/SpecialEventRouteDeleteProcessor.php`
- `src/State/SpecialEventRoute/PublishProcessor.php`
- `src/State/SpecialEventRoute/StartOutboundProcessor.php`
- `src/State/SpecialEventRoute/ArriveAtEventProcessor.php`
- `src/State/SpecialEventRoute/StartReturnProcessor.php`
- `src/State/SpecialEventRoute/CompleteProcessor.php`
- `src/State/SpecialEventRoute/StudentReadyProcessor.php`
- `src/Message/StudentReadyForPickupMessage.php`
- `src/MessageHandler/StudentReadyForPickupHandler.php`

### Phase 11: Driver Search (OpenSearch) ✅

**Use case:** Parents search for drivers by name, nickname, or identification number to attach their children to a driver's route. Results are scoped to the parent's school (multi-tenancy enforced at both OpenSearch and Doctrine layers).

**Architecture:**
- **OpenSearch index** (`{prefix}drivers`) with `edge_ngram` autocomplete analyzer and `asciifolding` for accent-insensitive search (García → garcia, Pérez → perez)
- **Async indexing pipeline:** Doctrine event listener (`DriverIndexListener`) dispatches `IndexDriverMessage` / `RemoveDriverFromIndexMessage` via Symfony Messenger → async handlers update OpenSearch. Listens to both `Driver` and `User` entity changes (firstName/lastName live on User)
- **Graceful fallback:** If OpenSearch is unavailable, the API falls back to a Doctrine `LIKE` query with prefix indexes for B-tree utilization
- **Rate limiting:** 30 requests per 10 seconds per user (sliding window)
- **Autocomplete-optimized:** Short queries (2–3 chars) use `match_phrase_prefix`; longer queries use `multi_match` with fuzziness. `_source` filtering and `track_total_hits: false` for performance

**Searchable fields:** `nickname`, `firstName`, `lastName`, `identificationNumber` (prefix match via `keyword` type)

**API endpoint:** `GET /api/drivers/search?q=query&page=1&itemsPerPage=10`
- Security: `ROLE_PARENT` or `ROLE_SCHOOL_ADMIN`
- Returns: `{ results: [...], total, page, itemsPerPage }`
- `Cache-Control: private, max-age=5` for autocomplete caching

**Console command:**
```bash
php bin/console app:opensearch:index-drivers [--force] [--batch-size=100] [--school=ID]
```

**Key Files:**
- `src/Service/OpenSearch/DriverSearchService.php` — search, index, delete, createIndex
- `src/Service/OpenSearch/DriverSearchHit.php` — immutable search result DTO
- `src/Service/OpenSearch/DriverSearchResult.php` — immutable result collection DTO
- `src/EventListener/DriverIndexListener.php` — Doctrine listener for Driver + User events
- `src/Message/IndexDriverMessage.php` / `RemoveDriverFromIndexMessage.php`
- `src/MessageHandler/IndexDriverHandler.php` / `RemoveDriverFromIndexHandler.php`
- `src/ApiResource/DriverSearchResult.php` — virtual API Platform resource
- `src/State/DriverSearch/DriverSearchProvider.php` — provider with OpenSearch + Doctrine fallback
- `src/Command/OpenSearchIndexDriversCommand.php` — bulk index hydration command

### Phase 12: Real-Time Trip Notifications (SSE) ✅

Replaces 15-second polling on the parent tracking screen with Mercure Server-Sent Events for all active trip lifecycle events.

**Events published to `/api/users/{parentId}/notifications`** (private):
- `bus_arriving` — bus approaching child's stop (estimated minutes)
- `bus_arrived` — bus arrived at child's stop
- `student_picked_up` — child picked up by driver
- `student_dropped_off` — child safely dropped off
- `route_started` — route has begun, all parents on route notified
- `route_completed` — route finished

**Events published to `/tracking/route/{activeRouteId}`** (public):
- `stop_status_changed` — stop transitions (approaching, arrived, picked_up, dropped_off)
- `route_started` / `route_completed` — route lifecycle

**Event Pipeline:**
```
GeofencingService → StopApproachingEvent/StopArrivedEvent
  └→ GeofencingBridgeSubscriber → BusArrivingEvent
       └→ TripMercureSubscriber → Mercure /api/users/{id}/notifications + /tracking/route/{id}
       └→ RouteNotificationSubscriber → email/SMS/push

AttendanceController → StudentPickedUpEvent/StudentDroppedOffEvent
  └→ TripMercureSubscriber → Mercure
  └→ RouteNotificationSubscriber → email/SMS/push

ActiveRoute PATCH (Doctrine) → ActiveRouteStatusListener
  └→ RouteStartedEvent/RouteCompletedEvent
       └→ TripMercureSubscriber → Mercure
       └→ RouteNotificationSubscriber → email/SMS/push
```

**Resilience:** All Mercure publishes are wrapped in try/catch — failures are logged but never break the main request. Event dispatch in `AttendanceController` is also non-fatal.

**Key Files:**
- `src/EventSubscriber/TripMercureSubscriber.php` — Mercure publisher for all trip events
- `src/EventSubscriber/GeofencingBridgeSubscriber.php` — bridges geofence → domain events
- `src/EventListener/ActiveRouteStatusListener.php` — Doctrine listener for route start/complete
- `src/Event/StopApproachingEvent.php`, `src/Event/StopArrivedEvent.php` — extracted from GeofencingService

### Phase 12b: Stop Link Request Notifications (SSE) ✅

Real-time Mercure notifications for the route-stop link request lifecycle — when a parent requests to add their child to a driver's route, and the driver confirms or rejects.

**Events published to `/api/users/{userId}/notifications`** (private):
- `route_stop_requested` → driver notified when parent creates an unconfirmed stop
- `route_stop_confirmed` → parent(s) notified when driver confirms the stop
- `route_stop_rejected` → parent(s) notified when driver rejects the stop

**Notification Pipeline:**
```
Parent creates RouteStop (unconfirmed) → Doctrine postPersist/postFlush
  └→ RouteStopCreatedListener → RouteStopNotificationPublisher.notifyDriverOfNewRequest()
       └→ Mercure /api/users/{driverUserId}/notifications

Driver PATCH /api/route-stops/{id}/confirm → RouteStopConfirmProcessor
  └→ RouteStopNotificationPublisher.notifyParentsOfConfirmation()
       └→ Mercure /api/users/{parentId}/notifications (each parent)

Driver PATCH /api/route-stops/{id}/reject → RouteStopRejectProcessor
  └→ RouteStopNotificationPublisher.notifyParentsOfRejection()
       └→ Mercure /api/users/{parentId}/notifications (each parent)
```

**Resilience:** All Mercure publishes are wrapped in try/catch — failures are logged but never break the main request.

**Key Files:**
- `src/Service/RouteStopNotificationPublisher.php` — Mercure publisher for all stop link events
- `src/EventListener/RouteStopCreatedListener.php` — Doctrine listener for new unconfirmed stops
- `src/State/RouteStop/RouteStopConfirmProcessor.php` — confirm endpoint with notification
- `src/State/RouteStop/RouteStopRejectProcessor.php` — reject endpoint with notification

## 📚 API Documentation

### Authentication

#### User Registration (Public)
```http
POST /api/users
Content-Type: application/json

{
  "email": "newuser@example.com",
  "password": "SecurePassword123!",
  "firstName": "John",
  "lastName": "Doe",
  "phoneNumber": "+1234567890",
  "roles": ["ROLE_PARENT"]
}
```

#### Login
```http
POST /api/login
Content-Type: application/json

{ "email": "user@example.com", "password": "password123" }
```

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200...",
  "refresh_token_expiration": 1751000000
}
```

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

#### Token Refresh

When the JWT expires (TTL: 2 hours), exchange the refresh token for a new JWT + refresh token pair without re-entering credentials. Refresh tokens are **single-use** — each successful refresh issues a new refresh token and invalidates the previous one (rotation). TTL: 30 days.

```http
POST /api/token/refresh
Content-Type: application/json

{ "refresh_token": "def50200..." }
```

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "ghi80300...",
  "refresh_token_expiration": 1751000000
}
```

| Code | Meaning |
|------|---------|
| `200` | New JWT + refresh token returned |
| `401` | Refresh token missing, expired, or already used |

#### Logout

Refresh tokens are automatically invalidated on logout for the `api_token_refresh` firewall. On the client side, remove both stored tokens.

### API Resources (RESTful)

#### Users
- `GET /api/users` — List users
- `POST /api/users` — Register (public)
- `PATCH /api/users/{id}` — Update
- `DELETE /api/users/{id}` — Delete

#### Students
- `GET /api/students` — List; scoped by role: school admin sees all, parent sees own children, driver sees students on their assigned routes
- `GET /api/students/{id}` — Accessible to: parents of the student, school admins, or drivers whose routes include a stop for that student
- `POST /api/students` — Create (parent)
- `PATCH /api/students/{id}` — Update (parent of student or school admin)
- `DELETE /api/students/{id}` — Delete (parent of student or school admin)

#### Routes
- `GET /api/routes` — List; scoped by role and school. Supports `?driver={id}` to list a driver's routes. School admin sees all (filterable by driver), driver sees own, parent sees students' stop routes or a specific driver's routes via `?driver=`
- `POST /api/routes` — Create (admin only)
- `PATCH /api/routes/{id}` — Update
- `DELETE /api/routes/{id}` — Delete (admin only)

#### Route Stops
- `GET /api/route-stops` — List; scoped by role: school admin sees all, driver sees stops on own routes, parent sees stops for own students
- `GET /api/route-stops/{id}` — Get
- `POST /api/route-stops` — Create (parent)
- `GET /api/route-stops/unconfirmed` — Pending review (driver)
- `PATCH /api/route-stops/{id}/confirm` — Confirm (driver)
- `PATCH /api/route-stops/{id}/reject` — Reject (driver)

#### Active Routes
- `GET /api/active_routes` — List; scoped by role: school admin sees all, driver sees own active routes, parent sees active routes with their students
- `POST /api/active_routes` — Create (`ROUTE_MANAGE` — admin, or driver if flag enabled)
- `PATCH /api/active_routes/{id}` — Update status (driver/admin)
- `DELETE /api/active_routes/{id}` — Cancel (`ROUTE_MANAGE`)

#### Attendance
- `POST /api/attendances` — Record check-in/check-out (driver/admin)
- `GET /api/attendances` — Get records

#### Notification Preferences
- `GET /api/notification_preferences/{id}` — Get
- `POST /api/notification_preferences` — Create
- `PATCH /api/notification_preferences/{id}` — Update

### GPS Tracking

#### Update Driver Location
```http
POST /api/tracking/location
Authorization: Bearer {driver-jwt}
Content-Type: application/json

{
  "latitude": -34.603722,
  "longitude": -58.381592,
  "speed": 45.5,
  "heading": 270.0,
  "accuracy": 5.0,
  "recorded_at": "2026-02-23T08:30:00+00:00"
}
```

**Response `201`:**
```json
{
  "success": true,
  "location_id": 1042,
  "has_active_route": true
}
```

**Rate limit:** 1 request per 3 seconds per driver. Excess returns `429 Too Many Requests`.

#### Batch Update
```http
POST /api/tracking/location/batch
Authorization: Bearer {driver-jwt}
Content-Type: application/json

{
  "locations": [
    { "latitude": -34.60, "longitude": -58.38, "recorded_at": "..." },
    { "latitude": -34.61, "longitude": -58.39, "recorded_at": "..." }
  ]
}
```

#### Get Latest Driver Position
```http
GET /api/tracking/location/driver/{driverId}
Authorization: Bearer {jwt}
```

Returns the Redis-cached position (< 15 s old) if available, otherwise the latest DB record.

```json
{
  "driver_id": 7,
  "latitude": -34.603722,
  "longitude": -58.381592,
  "speed": 45.5,
  "heading": 270.0,
  "source": "cache",
  "recorded_at": "2026-02-23T08:30:00+00:00"
}
```

#### Driver Location History
```http
GET /api/tracking/location/driver/{driverId}/history?date=2026-02-23&limit=100
Authorization: Bearer {admin-jwt}   (ROLE_SCHOOL_ADMIN or ROLE_DRIVER if flag enabled)
```

### Distress Signal

#### Trigger Manual Distress
```http
POST /api/routes/sessions/{id}/distress
Authorization: Bearer {driver-jwt}
```

The authenticated driver must own the in-progress route session.

**Response `202 Accepted`:**
```json
{ "alertId": "550e8400-e29b-41d4-a716-446655440000" }
```

**Error `409 Conflict`** if an active alert already exists for this driver:
```json
{
  "error": "An active distress alert already exists",
  "alertId": "existing-alert-uuid"
}
```

### Driver Alerts

#### Respond to an Alert (nearby driver)
```http
POST /api/driver-alerts/{alertId}/respond
Authorization: Bearer {driver-jwt}
```

Caller's driver ID must appear in the alert's `nearbyDriverIds` list (populated by `DriverDistressHandler`).

**Response `200`:**
```json
{
  "success": true,
  "alertId": "550e8400-e29b-41d4-a716-446655440000",
  "status": "RESPONDED"
}
```

#### Resolve an Alert
```http
POST /api/driver-alerts/{alertId}/resolve
Authorization: Bearer {driver-jwt}
```

Caller must be the distressed driver, the responding driver, or a school admin.

**Response `200`:**
```json
{
  "success": true,
  "alertId": "550e8400-e29b-41d4-a716-446655440000",
  "status": "RESOLVED"
}
```

### Emergency Chat

#### Post a Message
```http
POST /api/driver-alerts/{alertId}/messages
Authorization: Bearer {jwt}
Content-Type: application/json

{ "content": "I'm stopped on Route 9, flat tire, no danger." }
```

**Response `201`:** `{ "id": 88 }`

**Error `422`** if the alert is RESOLVED (chat is read-only).

**Access:** Distressed driver's user, responding driver's user, or school admin.

#### Get Messages (paginated)
```http
GET /api/driver-alerts/{alertId}/messages?page=1&limit=20
Authorization: Bearer {jwt}
```

**Response `200`:**
```json
{
  "alertId": "550e8400-e29b-41d4-a716-446655440000",
  "page": 1,
  "limit": 20,
  "count": 3,
  "messages": [
    {
      "id": 88,
      "sender": { "id": 7, "name": "Carlos Gómez" },
      "content": "I'm stopped on Route 9, flat tire, no danger.",
      "sentAt": "2026-02-23T08:31:00+00:00",
      "readBy": []
    }
  ]
}
```

Content is decrypted server-side before being returned.

### Special Event Routes

All endpoints require `ROLE_SCHOOL_ADMIN` unless noted.

#### Create
```http
POST /api/special-event-routes
Authorization: Bearer {admin-jwt}
Content-Type: application/json

{
  "school_id": 1,
  "name": "Museum Visit — Grade 5",
  "event_type": "MUSEUM_VISIT",
  "route_mode": "FULL_DAY_TRIP",
  "departure_mode": "GROUPED",
  "event_date": "2026-03-15",
  "outbound_departure_time": "2026-03-15T08:00:00+00:00",
  "return_departure_time": "2026-03-15T15:30:00+00:00"
}
```

**Response `201`:** `{ "id": 5, "status": "DRAFT" }`

**`departure_mode` is only valid when `route_mode` is `FULL_DAY_TRIP`.**

#### List (with filters)
```http
GET /api/special-event-routes?school_id=1&date=2026-03-15&status=PUBLISHED&route_mode=FULL_DAY_TRIP
Authorization: Bearer {admin-jwt}
```

#### Get / Update / Delete
```http
GET    /api/special-event-routes/{id}
PATCH  /api/special-event-routes/{id}    # only while status = DRAFT
DELETE /api/special-event-routes/{id}    # only while DRAFT or CANCELLED
```

#### Lifecycle Transitions

| Endpoint | From | To | Notes |
|---|---|---|---|
| `POST /api/special-event-routes/{id}/publish` | DRAFT | PUBLISHED | Validates constraints; auto-generates stops |
| `POST /api/special-event-routes/{id}/start-outbound` | PUBLISHED | IN_PROGRESS | |
| `POST /api/special-event-routes/{id}/arrive-at-event` | IN_PROGRESS | IN_PROGRESS | ONE_WAY → COMPLETED automatically |
| `POST /api/special-event-routes/{id}/start-return` | IN_PROGRESS | IN_PROGRESS | ONE_WAY returns 422; RETURN_TO_SCHOOL notifies parents |
| `POST /api/special-event-routes/{id}/complete` | IN_PROGRESS | COMPLETED | |

#### Mark Student as Ready (Individual Departure Mode)
```http
POST /api/special-event-routes/{id}/students/{studentId}/ready
Authorization: Bearer {driver-jwt}
```

Only valid when `route_mode=FULL_DAY_TRIP`, `departure_mode=INDIVIDUAL`, and `status=IN_PROGRESS`.

**Response `202 Accepted`:** `{ "success": true }`

The handler fires 30 seconds later (via `DelayStamp`), acquires a distributed lock to coalesce rapid events, and re-sequences all pending ready stops via `RouteOptimizationService`. The updated order is then published to the driver's Mercure topic.

### Custom Dashboard Endpoints

#### Parent Dashboard
```http
GET /api/parent/dashboard
Authorization: Bearer {parent-jwt}
```

```json
{
  "children": [
    {
      "studentId": 1,
      "firstName": "Ana",
      "lastName": "García",
      "currentStatus": "picked_up",
      "activeRouteId": 42,
      "busLocation": { "latitude": -34.603722, "longitude": -58.381592 },
      "estimatedArrival": "2026-02-23T08:45:00+00:00"
    }
  ],
  "activeRoutes": [],
  "todayAttendance": [],
  "upcomingRoutes": []
}
```

#### School Admin Dashboard
```http
GET /api/school-admin/dashboard
Authorization: Bearer {admin-jwt}
```

```json
{
  "statistics": {
    "totalStudents": 150, "totalDrivers": 8,
    "activeDrivers": 5, "activeRoutes": 5, "completedRoutes": 7
  },
  "activeRoutes": [],
  "driverStatuses": [],
  "recentAlerts": [],
  "todayMetrics": {}
}
```

### Geofencing

```http
POST /api/geofencing/check/{routeId}      # check a specific active route
POST /api/geofencing/check-all            # check all in-progress routes (ROUTE_MANAGE)
GET  /api/geofencing/distance-to-next/{routeId}
```

### Safety & Analytics

```http
GET /api/safety/audit
GET /api/reports/performance
GET /api/reports/efficiency
GET /api/reports/top-performing
GET /api/reports/comparative
```

### Driver Search

```http
GET /api/drivers/search?q=Carlos&page=1&itemsPerPage=10
Authorization: Bearer {parent-or-admin-jwt}
```

**Response `200`:**
```json
{
  "results": [
    {
      "driverId": 7,
      "nickname": "Carlitos",
      "firstName": "Carlos",
      "lastName": "García",
      "identificationNumber": "12345678",
      "score": 8.45
    }
  ],
  "total": 1,
  "page": 1,
  "itemsPerPage": 10
}
```

| Parameter | Default | Max | Notes |
|-----------|---------|-----|-------|
| `q` | — | 100 chars | Min 2 chars; shorter returns empty results |
| `page` | 1 | — | |
| `itemsPerPage` | 10 | 20 | |

Rate limited: 30 req / 10 sec per user. Excess returns `429 Too Many Requests`.

### Absences

```http
POST /api/absences                             # report absence (parent/admin)
GET  /api/absences/student/{studentId}
GET  /api/absences/date/{date}                 # ROUTE_MANAGE
POST /api/absences/recalculate-pending         # ROUTE_MANAGE
```

## 💳 Payment Integration (Mercado Pago)

### Overview

Marketplace + OAuth model: each driver authorises the app once via OAuth and every payment goes directly to that driver's Mercado Pago account. The platform can optionally retain a configurable marketplace fee.

### Architecture Features

- **Marketplace + OAuth** — per-driver payments; no intermediary holding funds
- **Driver-defined Rates** — four pricing models (flat, per-route, per-student, per-route-student); amount always calculated server-side from the driver's rate configuration
- **Idempotency** — Redis-backed idempotency keys (24-hour TTL) prevent duplicate charges
- **Async Webhook Processing** — RabbitMQ decouples webhook receipt from processing
- **Real-time Updates** — Mercure pushes private payment status events to the subscribing parent app
- **Two-token Mercure auth** — API JWT (RSA) and Mercure subscriber JWT (HMAC-SHA256) are separate
- **Rate Limiting** — 10 requests/minute per IP on payment endpoints
- **Retry Logic** — exponential backoff (1 s → 2 s → 4 s), max 3 retries, dead-letter on failure
- **Token Encryption** — driver OAuth tokens encrypted at rest with libsodium secretbox
- **Rate Snapshots** — each payment stores a JSON snapshot of the rate used at payment time for auditability

### Payment Flow

```
Driver (once)
  └── GET /oauth/mercadopago/connect → MP OAuth → encrypted tokens in DB

Driver (rate setup)
  └── POST /api/driver-rates (or POST /api/drivers/{id}/rates for bulk set)
        └── defines pricing model + rates (flat, per-route, per-student, per-route-student)

Parent
  └── POST /api/payments/create-preference
        ├── amount auto-calculated from driver's rate config
        └── returns { init_point, payment_id, amount, currency }

MP calls POST /api/webhooks/mercadopago
  └── validate → dispatch ProcessWebhookMessage → RabbitMQ → HTTP 200

Worker (ProcessWebhookMessageHandler)
  ├── fetch authoritative status from MP
  ├── persist PaymentTransaction
  └── PaymentApprovedEvent → Mercure /payments/{id}

Parent app
  └── GET /api/mercure/token?payment_id={id} → Mercure JWT
        └── EventSource(hub_url, Bearer mercureJwt)
```

### API Endpoints

#### Driver Rate Management
```http
GET    /api/driver-rates?driver={id}       # List driver's rates
POST   /api/driver-rates                   # Create a single rate (ROLE_DRIVER)
PATCH  /api/driver-rates/{id}              # Update a rate (owner only)
DELETE /api/driver-rates/{id}              # Delete a rate (owner only)
POST   /api/drivers/{id}/rates             # Bulk set all rates (atomically replaces existing)
```

Pricing models: `flat`, `per_route`, `per_student`, `per_route_student`.
- **flat** / **per_route**: requires `amount`; `perStudentAmount` must be null
- **per_student** / **per_route_student**: requires `perStudentAmount`; `amount` must be null
- **per_route** / **per_route_student**: requires `route`; other models must have `route` null

#### List Driver's Routes (for payment)
When a parent needs to select a route for per-route pricing, fetch the driver's routes:
```http
GET /api/routes?driver=42
```
Returns all routes assigned to the driver, scoped by the parent's school. The driver detail (`GET /api/drivers/{id}`) also includes route IRIs in the response.

#### Create Payment Preference

The payment amount is always calculated server-side from the driver's rate configuration. No `amount` or `currency` field is sent by the client.

```http
POST /api/payments/create-preference
Authorization: Bearer {api-jwt}
Content-Type: application/json

{
  "driverId": 42,
  "studentIds": [1, 2],
  "routeId": null,
  "description": "Transporte escolar — marzo 2026",
  "idempotencyKey": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response `201`:**
```json
{
  "paymentId": 123,
  "preferenceId": "123456-abc-def",
  "initPoint": "https://www.mercadopago.com/checkout/v1/redirect?pref_id=...",
  "status": "pending",
  "amount": "3500.00",
  "currency": "ARS"
}
```

The `amount` is calculated as:
- **flat**: `rate.amount`
- **per_route**: `rate(route).amount`
- **per_student**: `rate.perStudentAmount × studentCount`
- **per_route_student**: `rate(route).perStudentAmount × studentCount`

Payment detail includes a `rateSnapshot` JSON object recording the pricing model, amounts, route, and student count used at payment time.

#### Check Payment Status
```http
GET /api/payments/{id}/status
Authorization: Bearer {api-jwt}
```

#### Mercure Subscriber Token (exchange API JWT → Mercure JWT)
```http
# Subscribe to payment status updates
GET /api/mercure/token?payment_id={id}
Authorization: Bearer {api-jwt}

# Subscribe to user notifications
GET /api/mercure/token?user_id={id}
Authorization: Bearer {api-jwt}
```

Exactly one of `payment_id` or `user_id` must be provided. Users can only request tokens for their own resources (own payments, own user ID). Returns `{ token, hub_url, topics }`. Use `token` only with the Mercure hub, never for API calls.

#### Driver: Connect Mercado Pago
```http
GET /oauth/mercadopago/connect     → { redirect_url }
GET /oauth/mercadopago/status      → { connected, mp_account_id, expires_at }
```

#### Subscriptions
```http
POST  /api/subscriptions
PATCH /api/subscriptions/{id}/cancel
```

#### Admin
```http
POST /api/admin/payments/{id}/refund
GET  /api/admin/payments/reconciliation?from=2026-03-01&to=2026-03-31
```

## 🛠️ Command Line Tools

```bash
# Create a regular user
php bin/console app:create-user user@example.com password123 John Doe "555-1234" "12345678"

# Create a super admin
php bin/console app:create-user admin@example.com password123 Jane Admin "555-5678" "87654321" --super-admin

# Process subscriptions manually
php bin/console app:process-subscriptions

# Archive completed routes
php bin/console app:archive-routes --days=7

# Hydrate OpenSearch drivers index (bulk re-index)
php bin/console app:opensearch:index-drivers --force --batch-size=100

# Index drivers for a specific school only
php bin/console app:opensearch:index-drivers --school=1

# Sync payment status from Mercado Pago (useful for sandbox/test payments that don't trigger webhooks)
php bin/console app:payment:sync <payment-id> --provider-id=<mp-payment-id>

# Sync when provider ID is already stored on the payment
php bin/console app:payment:sync <payment-id>

# Dry run — preview without making changes
php bin/console app:payment:sync <payment-id> --dry-run

# Expire stale pending payments (also runs automatically every hour via Scheduler)
php bin/console app:payment:expire-stale
php bin/console app:payment:expire-stale --batch-size=200
```

## 🔧 Installation & Setup

### Prerequisites

- Docker & Docker Compose v2.10+
- Git

### Quick Start

```bash
git clone https://github.com/yourusername/zigzag-api.git
cd zigzag-api
cp .env .env.local
```

Edit `.env.local`:
```bash
# Database
DATABASE_URL="mysql://zigzag:ZigZagTech!2026@127.0.0.1:3306/zigzag?serverVersion=8.4&charset=utf8mb4"

# JWT
JWT_PASSPHRASE=your-secure-passphrase

# Google Maps
GOOGLE_MAPS_API_KEY=your-google-maps-api-key

# Notifications
MAIL_FROM_EMAIL=noreply@yourschool.com
FCM_SERVER_KEY=your-fcm-server-key
SMS_API_KEY=your-sms-api-key
SMS_API_URL=https://api.smsprovider.com/send

# RabbitMQ (three transports)
RABBITMQ_DSN=phpamqplib://guest:guest@rabbitmq:5672/%2f/webhooks
RABBITMQ_DSN_TRACKING=phpamqplib://guest:guest@rabbitmq:5672/%2f/tracking

# Mercure (internal URL uses Docker service name; public URL goes through Traefik)
MERCURE_URL=http://php:80/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure
MERCURE_JWT_SECRET="change-this-to-a-strong-secret"

# Traefik (prod only — ACME Let's Encrypt email)
ACME_EMAIL=admin@yourschool.com

# Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=TEST-your-platform-access-token
MERCADOPAGO_WEBHOOK_SECRET=your-webhook-secret
MERCADOPAGO_APP_ID=
MERCADOPAGO_APP_SECRET=
MERCADOPAGO_OAUTH_REDIRECT_URI=https://your-domain.com/oauth/mercadopago/callback
MERCADOPAGO_MARKETPLACE_FEE_PERCENT=0

# Token encryption (libsodium secretbox)
# Generate: php -r "echo base64_encode(random_bytes(32));"
TOKEN_ENCRYPTION_KEY=

# Driver Route Management Flag (default: off)
DRIVER_ROUTE_MANAGEMENT_ENABLED=false

# Distress proximity radius in km (default: 5 km)
DISTRESS_PROXIMITY_KM=5.0
```

```bash
# Start containers
docker compose --env-file .env.local up -d --wait

# Install dependencies
docker compose exec php composer install

# Generate JWT keys
docker compose exec php php bin/console lexik:jwt:generate-keypair

# Run migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

**API:** http://localhost | **Docs:** http://localhost/api/docs | **Traefik Dashboard:** http://127.0.0.1:8080
**RabbitMQ Management:** http://rabbitmq.localhost | **OpenSearch:** http://opensearch.localhost | **OpenSearch Dashboards:** http://dashboards.localhost

### Background Workers

Four Messenger workers must run in production (add a fifth for the scheduler):

```bash
# 1. General async transport (email, SMS, subscription billing, distress, chat)
docker compose exec php php bin/console messenger:consume async --time-limit=3600 -vv

# 2. Payment webhook processing (isolated, fast)
docker compose exec php php bin/console messenger:consume async_webhooks --time-limit=3600 -vv

# 3. GPS tracking pipeline (GeofenceEvaluation, MercurePublish, ProximityEvaluation)
docker compose exec php php bin/console messenger:consume async_tracking --time-limit=3600 -vv

# 4. Symfony Scheduler (anomaly detection every 60 s, subscription billing every 5 min)
docker compose exec php php bin/console messenger:consume scheduler_default --time-limit=3600 -vv
```

**Supervisord (production):**
```ini
[program:messenger_async]
command=php bin/console messenger:consume async --time-limit=3600
directory=/var/www/html
autostart=true ; autorestart=true ; numprocs=2

[program:messenger_webhooks]
command=php bin/console messenger:consume async_webhooks --time-limit=3600
directory=/var/www/html
autostart=true ; autorestart=true ; numprocs=2

[program:messenger_tracking]
command=php bin/console messenger:consume async_tracking --time-limit=3600
directory=/var/www/html
autostart=true ; autorestart=true ; numprocs=2

[program:scheduler_worker]
command=php bin/console messenger:consume scheduler_default --time-limit=3600
directory=/var/www/html
autostart=true ; autorestart=true ; numprocs=1
```

### Cron Jobs

```bash
# Archive routes older than 7 days — run daily at 2 AM
0 2 * * * cd /path/to/project && docker compose exec php php bin/console app:archive-routes --days=7
```

## 🧪 Code Quality

```bash
make test          # Run full test suite (73 tests)
make phpstan       # PHPStan static analysis at level 9
make rector-dry    # Preview Rector modernizations
make rector        # Apply Rector modernizations
make ecs-dry       # Preview ECS style fixes
make ecs           # Apply ECS style fixes
make quality       # All quality checks (CI mode, no fixes)
make fix           # Apply all automated fixes
```

PHPStan is configured at **level 9** (`phpstan.dist.neon`) with `reportUnmatchedIgnoredErrors: false`. All 73 tests and PHPStan must pass before merging.

## 📱 Mobile App Integration Guide (React Native)

### Setup

```bash
npm install axios @react-native-async-storage/async-storage react-native-sse
npm install @react-native-firebase/app @react-native-firebase/messaging
npm install react-native-maps @react-native-community/geolocation
npm install uuid
```

### API Client

```javascript
// api/client.js
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const apiClient = axios.create({ baseURL: 'https://your-api.com/api' });

apiClient.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('jwt_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
  failedQueue.forEach((prom) => (error ? prom.reject(error) : prom.resolve(token)));
  failedQueue = [];
};

apiClient.interceptors.response.use(
  (r) => r,
  async (err) => {
    const originalRequest = err.config;
    if (err.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`;
            return apiClient(originalRequest);
          })
          .catch(Promise.reject.bind(Promise));
      }

      originalRequest._retry = true;
      isRefreshing = true;

      const refreshToken = await AsyncStorage.getItem('refresh_token');
      if (!refreshToken) {
        isRefreshing = false;
        await AsyncStorage.multiRemove(['jwt_token', 'refresh_token']);
        return Promise.reject(err);
      }

      try {
        const { data } = await axios.post('https://your-api.com/api/token/refresh', {
          refresh_token: refreshToken,
        });
        await AsyncStorage.multiSet([
          ['jwt_token', data.token],
          ['refresh_token', data.refresh_token],
        ]);
        apiClient.defaults.headers.common.Authorization = `Bearer ${data.token}`;
        processQueue(null, data.token);
        originalRequest.headers.Authorization = `Bearer ${data.token}`;
        return apiClient(originalRequest);
      } catch (refreshError) {
        processQueue(refreshError, null);
        await AsyncStorage.multiRemove(['jwt_token', 'refresh_token']);
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }
    return Promise.reject(err);
  }
);

export default apiClient;
```

### Authentication

```javascript
// api/auth.js
import apiClient from './client';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const login = async (email, password) => {
  const { data } = await apiClient.post('/login', { email, password });
  await AsyncStorage.multiSet([
    ['jwt_token', data.token],
    ['refresh_token', data.refresh_token],
  ]);
  return data;
};

export const logout = () =>
  AsyncStorage.multiRemove(['jwt_token', 'refresh_token']);
```

### Real-time GPS Tracking (Driver App)

```javascript
// hooks/useLocationTracking.js
import { useEffect, useRef, useState } from 'react';
import Geolocation from '@react-native-community/geolocation';
import apiClient from '../api/client';

export const useLocationTracking = () => {
  const [tracking, setTracking] = useState(false);
  const watchId = useRef(null);

  const sendLocation = async ({ latitude, longitude, speed, heading }) => {
    try {
      await apiClient.post('/tracking/location', {
        latitude, longitude,
        speed: speed ?? null,
        heading: heading ?? null,
        recorded_at: new Date().toISOString(),
      });
    } catch (err) {
      // 429 = rate limited (1 req / 3 s); ignore silently or queue locally
      if (err.response?.status !== 429) console.error(err);
    }
  };

  const startTracking = () => {
    watchId.current = Geolocation.watchPosition(
      (pos) => sendLocation(pos.coords),
      console.error,
      { enableHighAccuracy: true, distanceFilter: 10, interval: 4000 }
    );
    setTracking(true);
  };

  const stopTracking = () => {
    if (watchId.current !== null) {
      Geolocation.clearWatch(watchId.current);
      watchId.current = null;
      setTracking(false);
    }
  };

  useEffect(() => () => stopTracking(), []);
  return { tracking, startTracking, stopTracking };
};
```

### Subscribe to Live Bus Location (Parent App)

```javascript
// hooks/useBusTracking.js
import { useEffect, useRef, useState } from 'react';
import { EventSource } from 'react-native-sse';

const HUB_URL = 'https://your-api.com/.well-known/mercure';

/**
 * Subscribes to /tracking/driver/{driverId} (public topic — no JWT needed).
 */
export const useBusTracking = (driverId) => {
  const [location, setLocation] = useState(null);
  const esRef = useRef(null);

  useEffect(() => {
    if (!driverId) return;

    const url = new URL(HUB_URL);
    url.searchParams.append('topic', `/tracking/driver/${driverId}`);

    const es = new EventSource(url.toString());
    es.addEventListener('message', (e) => setLocation(JSON.parse(e.data)));
    esRef.current = es;
    return () => es.close();
  }, [driverId]);

  return location;
};
```

### Distress Signal (Driver App)

```javascript
// api/distress.js
import apiClient from './client';

export const triggerDistress = (routeSessionId) =>
  apiClient.post(`/routes/sessions/${routeSessionId}/distress`);

export const respondToAlert = (alertId) =>
  apiClient.post(`/driver-alerts/${alertId}/respond`);

export const resolveAlert = (alertId) =>
  apiClient.post(`/driver-alerts/${alertId}/resolve`);
```

### Subscribe to Distress Alerts (Driver App)

```javascript
// hooks/useDistressAlerts.js
import { useEffect, useRef, useState } from 'react';
import { EventSource } from 'react-native-sse';

const HUB_URL = 'https://your-api.com/.well-known/mercure';

export const useDistressAlerts = (driverId) => {
  const [alert, setAlert] = useState(null);

  useEffect(() => {
    if (!driverId) return;

    const url = new URL(HUB_URL);
    url.searchParams.append('topic', `/alerts/driver/${driverId}`);

    const es = new EventSource(url.toString());
    es.addEventListener('message', (e) => setAlert(JSON.parse(e.data)));
    return () => es.close();
  }, [driverId]);

  return alert;
};
```

### Emergency Chat (Driver/Admin App)

```javascript
// api/chat.js
import apiClient from './client';

export const sendMessage = (alertId, content) =>
  apiClient.post(`/driver-alerts/${alertId}/messages`, { content });

export const getMessages = (alertId, page = 1, limit = 20) =>
  apiClient.get(`/driver-alerts/${alertId}/messages`, { params: { page, limit } });
```

### Subscribe to Emergency Chat (Driver/Admin App)

```javascript
// hooks/useChatUpdates.js — private Mercure topic, requires subscriber JWT
import { useEffect, useRef, useState } from 'react';
import { EventSource } from 'react-native-sse';
import apiClient from '../api/client';

const HUB_URL = 'https://your-api.com/.well-known/mercure';

export const useChatUpdates = (alertId) => {
  const [messages, setMessages] = useState([]);

  useEffect(() => {
    if (!alertId) return;

    // Exchange API JWT for a Mercure subscriber JWT that has /chat/alert/{id} scope
    apiClient.get(`/mercure/token`, { params: { alert_id: alertId } })
      .then(({ data }) => {
        const url = new URL(HUB_URL);
        url.searchParams.append('topic', `/chat/alert/${alertId}`);

        const es = new EventSource(url.toString(), {
          headers: { Authorization: `Bearer ${data.token}` },
        });
        es.addEventListener('message', (e) =>
          setMessages((prev) => [...prev, JSON.parse(e.data)])
        );
        return () => es.close();
      });
  }, [alertId]);

  return messages;
};
```

### Payment Integration (Parent App)

```javascript
// api/payment.js — amount is calculated server-side from driver's rate
import apiClient from './client';
import { v4 as uuidv4 } from 'uuid';
import { Linking } from 'react-native';

// Fetch the driver's routes (needed for per-route pricing to select routeId)
export const getDriverRoutes = (driverId) =>
  apiClient.get('/routes', { params: { driver: driverId } })
    .then((r) => r.data);

export const initiatePayment = async (driverId, studentIds, description, routeId = null) => {
  const { data } = await apiClient.post('/payments/create-preference', {
    driverId,
    studentIds,
    routeId,
    description,
    idempotencyKey: uuidv4(),
  });
  await Linking.openURL(data.initPoint);
  return data.paymentId;
};

// Exchange API JWT → short-lived Mercure JWT for a single payment topic
export const getMercurePaymentToken = (paymentId) =>
  apiClient.get('/mercure/token', { params: { payment_id: paymentId } })
    .then((r) => r.data);

// Exchange API JWT → short-lived Mercure JWT for user notifications
export const getMercureUserToken = (userId) =>
  apiClient.get('/mercure/token', { params: { user_id: userId } })
    .then((r) => r.data);
```

### Subscribe to User Notifications (Parent & Driver App)

All real-time events for a user (trip updates, stop link requests, payments) are delivered on a **single private topic**: `/api/users/{userId}/notifications`. The app connects once at login and routes events by the `event` field.

```javascript
// hooks/useUserNotifications.js — single SSE connection for all user events
import { useEffect, useRef, useCallback } from 'react';
import { EventSource } from 'react-native-sse';
import { getMercureUserToken } from '../api/payment';

const HUB_URL = 'https://your-api.com/.well-known/mercure';

/**
 * Subscribes to /api/users/{userId}/notifications (private topic).
 * Requires a Mercure subscriber JWT obtained via GET /api/mercure/token?user_id={id}.
 *
 * @param {number} userId - The authenticated user's ID
 * @param {object} handlers - Map of event type → callback function
 *
 * Example handlers:
 *   {
 *     bus_arriving:          (data) => showBusAlert(data),
 *     student_picked_up:     (data) => updateStudentStatus(data),
 *     route_stop_requested:  (data) => showNewStopRequest(data),
 *     route_stop_confirmed:  (data) => showConfirmation(data),
 *   }
 */
export const useUserNotifications = (userId, handlers) => {
  const esRef = useRef(null);
  const handlersRef = useRef(handlers);
  handlersRef.current = handlers;

  const connect = useCallback(async () => {
    if (!userId) return;

    try {
      const { token } = await getMercureUserToken(userId);

      const url = new URL(HUB_URL);
      url.searchParams.append('topic', `/api/users/${userId}/notifications`);

      const es = new EventSource(url.toString(), {
        headers: { Authorization: `Bearer ${token}` },
      });

      es.addEventListener('message', (e) => {
        const data = JSON.parse(e.data);
        const handler = handlersRef.current[data.event];
        if (handler) handler(data);
      });

      es.addEventListener('error', () => {
        // Reconnect after 5s on connection loss
        es.close();
        setTimeout(() => connect(), 5000);
      });

      esRef.current = es;
    } catch (err) {
      // Token fetch failed — retry after 10s
      setTimeout(() => connect(), 10000);
    }
  }, [userId]);

  useEffect(() => {
    connect();
    return () => esRef.current?.close();
  }, [connect]);
};
```

Usage in a screen component:

```javascript
// screens/ParentDashboard.js
import { useUserNotifications } from '../hooks/useUserNotifications';

const ParentDashboard = ({ user }) => {
  useUserNotifications(user.id, {
    // Trip events (active route)
    bus_arriving:       (d) => showAlert(`Bus arriving in ${d.estimatedMinutes} min for ${d.studentName}`),
    bus_arrived:        (d) => showAlert(`Bus arrived for ${d.studentName}`),
    student_picked_up:  (d) => updateStatus(d.studentId, 'picked_up'),
    student_dropped_off:(d) => updateStatus(d.studentId, 'dropped_off'),
    route_started:      (d) => showAlert(`Route started — driver: ${d.driverName}`),
    route_completed:    (d) => showAlert('Route completed'),

    // Stop link requests
    route_stop_confirmed: (d) => showAlert(`${d.studentName} confirmed on ${d.routeName} by ${d.driverName}`),
    route_stop_rejected:  (d) => showAlert(`${d.studentName} rejected from ${d.routeName}`),

    // Payments
    payment_approved:   (d) => showPaymentStatus(d, 'approved'),
    payment_rejected:   (d) => showPaymentStatus(d, 'rejected'),
  });
};
```

```javascript
// screens/DriverDashboard.js
import { useUserNotifications } from '../hooks/useUserNotifications';

const DriverDashboard = ({ user }) => {
  useUserNotifications(user.id, {
    // Stop link requests from parents
    route_stop_requested: (d) => showNewRequest(`${d.studentName} wants to join ${d.routeName}`),
  });
};
```

### Event Payload Reference

All events are JSON objects with an `event` field for routing. Timestamps are ISO 8601.

#### Trip Events → `/api/users/{parentId}/notifications` (private)

```jsonc
// bus_arriving — bus is approaching the child's stop
{
  "event": "bus_arriving",
  "routeId": 42,
  "stopId": 7,
  "studentId": 5,
  "studentName": "Maria Garcia",
  "estimatedMinutes": 3,
  "timestamp": "2026-03-27T08:15:00-03:00"
}

// bus_arrived — bus has arrived at the child's stop
{
  "event": "bus_arrived",
  "routeId": 42,
  "stopId": 7,
  "studentId": 5,
  "studentName": "Maria Garcia",
  "timestamp": "2026-03-27T08:18:00-03:00"
}

// student_picked_up
{
  "event": "student_picked_up",
  "routeId": 42,
  "stopId": 7,
  "studentId": 5,
  "studentName": "Maria Garcia",
  "pickedUpAt": "2026-03-27T08:19:30-03:00",
  "timestamp": "2026-03-27T08:19:30-03:00"
}

// student_dropped_off
{
  "event": "student_dropped_off",
  "routeId": 42,
  "stopId": 7,
  "studentId": 5,
  "studentName": "Maria Garcia",
  "droppedOffAt": "2026-03-27T08:45:00-03:00",
  "timestamp": "2026-03-27T08:45:00-03:00"
}

// route_started — all parents on the route receive this
{
  "event": "route_started",
  "routeId": 42,
  "driverName": "Carlos Lopez",
  "startedAt": "2026-03-27T07:30:00-03:00",
  "timestamp": "2026-03-27T07:30:00-03:00"
}

// route_completed — all parents on the route receive this
{
  "event": "route_completed",
  "routeId": 42,
  "completedAt": "2026-03-27T09:00:00-03:00",
  "timestamp": "2026-03-27T09:00:00-03:00"
}
```

#### Trip Events → `/tracking/route/{activeRouteId}` (public)

```jsonc
// stop_status_changed — status: "approaching" | "arrived" | "picked_up" | "dropped_off"
{
  "event": "stop_status_changed",
  "stopId": 7,
  "status": "approaching",
  "studentId": 5,
  "timestamp": "2026-03-27T08:15:00-03:00"
}

// route_started / route_completed
{
  "event": "route_started",
  "routeId": 42,
  "timestamp": "2026-03-27T07:30:00-03:00"
}
```

#### Stop Link Request Events → `/api/users/{userId}/notifications` (private)

```jsonc
// route_stop_requested — sent to the DRIVER when a parent requests a stop
{
  "event": "route_stop_requested",
  "routeStopId": 10,
  "routeId": 42,
  "routeName": "Morning Route - School A",
  "studentId": 5,
  "studentName": "Maria Garcia",
  "timestamp": "2026-03-27T14:00:00-03:00"
}

// route_stop_confirmed — sent to each PARENT of the student
{
  "event": "route_stop_confirmed",
  "routeStopId": 10,
  "routeId": 42,
  "routeName": "Morning Route - School A",
  "studentId": 5,
  "studentName": "Maria Garcia",
  "driverName": "Carlos Lopez",
  "timestamp": "2026-03-27T15:30:00-03:00"
}

// route_stop_rejected — sent to each PARENT of the student
{
  "event": "route_stop_rejected",
  "routeStopId": 10,
  "routeId": 42,
  "routeName": "Morning Route - School A",
  "studentId": 5,
  "studentName": "Maria Garcia",
  "driverName": "Carlos Lopez",
  "timestamp": "2026-03-27T15:30:00-03:00"
}
```

### Polling Removal Guide

The following polling endpoints can now be replaced with the SSE connection above:

| Old Polling Pattern | Replacement SSE Event | Notes |
|---|---|---|
| Poll `GET /api/active-route-stops` for stop status changes | `stop_status_changed` on `/tracking/route/{id}` | Public topic, no JWT needed |
| Poll `GET /api/route-stops?isConfirmed=false` for pending requests (driver) | `route_stop_requested` on `/api/users/{driverId}/notifications` | Private topic |
| Poll for stop confirmation status (parent) | `route_stop_confirmed` / `route_stop_rejected` on `/api/users/{parentId}/notifications` | Private topic |
| Poll `GET /api/payments/{id}/status` for payment result | `payment_approved` / `payment_rejected` on `/api/users/{userId}/notifications` | Private topic |

**Connection strategy:** Open one `EventSource` per authenticated user at app startup (via `useUserNotifications`). For public route tracking, open a second connection to `/tracking/route/{id}` only while viewing the live map (no JWT needed). Close both on logout.

## 🔒 Security Features

- JWT-based stateless authentication for all `/api/*` routes
- Custom `RouteManagementVoter` for runtime driver privilege elevation
- Role-based authorization with hierarchical permissions (ROLE_SCHOOL_ADMIN → ROLE_DRIVER, ROLE_PARENT)
- Multi-tenant Doctrine filter — automatic per-request school context isolation
- TLS termination via Traefik (Let's Encrypt) in prod; Caddy embedded for Mercure; libsodium secretbox for token and message encryption
- Webhook HMAC-SHA256 signature validation with replay-attack prevention
- CSRF-protected MP OAuth flow (Redis-backed single-use state tokens, 10-min TTL)
- Private Mercure topics for payments, user notifications, and emergency chat; subscribers require a valid JWT
- Rate limiting on GPS ingestion (per driver) and payment endpoints (per IP)

## 📊 Performance Considerations

- **Redis first** — GPS `getDriverLocation` reads Redis (< 15 s TTL) before hitting MySQL
- **Async fanout** — GPS side-effects (geofencing, Mercure, proximity) are fully non-blocking
- **Three RabbitMQ transports** — tracking, webhooks, and general async are independently scalable
- **OpenSearch** — full-text search offloaded to OpenSearch (accessed internally at `http://opensearch:9200`)
- **Database indexing** — all high-frequency query columns indexed; driver nickname search uses `start` strategy (`LIKE 'value%'`) for B-tree index utilization
- **Role-scoped collections** — Route, RouteStop, and ActiveRoute GetCollection endpoints use custom providers to return only data the authenticated user is authorized to see (driver isolation)
- **FrankenPHP worker mode** — application boots once, handles thousands of requests in-process
- **Pagination** — all collection endpoints are paginated (default 20, max 50 per page)

## 🚀 Deployment

ZigZag uses a **blue/green deployment strategy** on a single DigitalOcean Droplet for zero-downtime releases.

### How It Works

Two "slots" (blue and green) each run a complete set of application containers (php + workers). Stateful services (database, RabbitMQ, Redis, OpenSearch) are shared. Traefik routes traffic to the active slot via a dynamic file provider.

```
1. Determine inactive slot (if blue is active → deploy to green)
2. Build new prod image on the droplet
3. Start the inactive slot with the new image
4. Health-check the new slot's php service
5. Run database migrations
6. Switch Traefik routing (file provider, ~1s switchover)
7. Drain old slot (30s grace period) and stop it
8. If health check fails → automatic rollback (old slot stays active)
```

### Deploying

```bash
# Tag a release and push — CI runs quality + tests, then deploys
git tag v1.2.3
git push origin v1.2.3

# Or trigger manually from GitHub Actions UI (Actions → Deploy → Run workflow)
```

### Rollback

Deploy the previous version by creating a new tag pointing to the old commit:

```bash
git tag v1.2.4 v1.2.2    # Point new tag at the known-good commit
git push origin v1.2.4
```

Or use `workflow_dispatch` from the GitHub Actions UI to redeploy.

### Versioning Convention

Use [semver](https://semver.org/) tags: `v1.0.0`, `v1.0.1`, `v1.1.0`, `v2.0.0`.

### Initial Setup

See the implementation guide in `BLUE_GREEN_DEPLOY_PROMPT.md` (Steps 1-8) for:
- Droplet preparation (deploy user, directories, Docker)
- SSH key generation for GitHub Actions
- GitHub Secrets configuration (`SSH_PRIVATE_KEY`, `DROPLET_IP`, `SERVER_NAME`)
- Production environment file (`.env.prod` on the droplet)
- JWT key generation
- First bootstrap deployment

### Environment Variables

All production secrets live in `.env.prod` **on the droplet only** — never in GitHub Secrets or the repository. The deploy script runs `docker compose --env-file .env.prod` to inject them at runtime. See `env.prod.template` for the full list of required variables.

### Key Files

| File | Purpose |
|---|---|
| `compose.deploy.yaml` | Blue/green production compose overlay |
| `scripts/deploy.sh` | Deployment script (runs on droplet) |
| `.github/workflows/deploy.yaml` | GitHub Actions deploy workflow |
| `env.prod.template` | Production env template (committed, no secrets) |
| `traefik/dynamic/routing.yaml` | Traefik routing config (written by deploy script) |
| `.active-slot` | Tracks which slot is live (on droplet) |

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Run `make quality` (PHPStan + ECS + Rector) and `make test` — all must pass
4. Commit your changes
5. Open a Pull Request

## 📝 License

This project is proprietary software. All rights reserved.

## 👥 Support

For support, email support@zigzag.com or open an issue in the repository.

---

**Built with ❤️ for safer school transportation**
