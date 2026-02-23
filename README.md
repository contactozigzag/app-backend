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
- **Payment Integration**: Mercado Pago Marketplace model with real-time SSE status updates
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
- **JWT (LexikJWTAuthenticationBundle)** — Stateless API authentication
- **Custom Security Voter** — `RouteManagementVoter` for runtime driver privilege elevation
- **RBAC** — Hierarchical role-based access control
- **Multi-tenant Filtering** — Automatic school-based Doctrine filter
- **libsodium secretbox** — Driver OAuth token encryption and chat message encryption

### Dev & Quality Tools
- **Docker & Docker Compose** — Containerized development
- **FrankenPHP** — High-performance PHP server (worker mode)
- **Caddy** — Automatic HTTPS / HTTP/3
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
         │ HTTPS/REST + SSE (Mercure)
         │
┌────────▼─────────────────────────────────────────┐
│              API Gateway (Symfony 8)              │
│  ┌─────────────────────────────────────────────┐ │
│  │  JWT Authentication & Authorization         │ │
│  │  Multi-tenant Context Filtering             │ │
│  │  RouteManagementVoter (runtime flag)        │ │
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
┌───▼────┐    ┌────▼────┐    ┌─────▼────┐
│ MySQL  │    │  Redis  │    │ RabbitMQ │
│   DB   │    │  Cache  │    │  Queues  │
└────────┘    └─────────┘    └──────────┘
                    │
             ┌──────▼──────┐
             │   Mercure   │
             │   SSE Hub   │
             └─────────────┘
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
| `/tracking/route/{routeId}` | public | `MercurePublishHandler` | parents on that route |
| `/alerts/driver/{driverId}` | public | `DriverDistressHandler`, `DriverAlertController` | affected drivers |
| `/alerts/admin/{schoolId}` | public | `DriverDistressHandler` | school admins |
| `/chat/alert/{alertId}` | private | `ChatMessagePublishHandler` | alert participants only |
| `/payments/{paymentId}` | private | `PaymentEventSubscriber` | paying parent |

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

**New Files:**
- `src/Entity/SpecialEventRoute.php`
- `src/Entity/SpecialEventRouteStop.php`
- `src/Repository/SpecialEventRouteRepository.php`
- `src/Repository/SpecialEventRouteStopRepository.php`
- `src/Controller/SpecialEventRouteController.php`
- `src/Message/StudentReadyForPickupMessage.php`
- `src/MessageHandler/StudentReadyForPickupHandler.php`

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
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGc...", "refresh_token": "def50200..." }
```

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### API Resources (RESTful)

#### Users
- `GET /api/users` — List users
- `POST /api/users` — Register (public)
- `PATCH /api/users/{id}` — Update
- `DELETE /api/users/{id}` — Delete

#### Students
- `GET /api/students` — List (filtered by school)
- `POST /api/students` — Create (admin only)
- `PATCH /api/students/{id}` — Update
- `DELETE /api/students/{id}` — Delete (admin only)

#### Routes
- `GET /api/routes` — List (filtered by school)
- `POST /api/routes` — Create (admin only)
- `PATCH /api/routes/{id}` — Update
- `DELETE /api/routes/{id}` — Delete (admin only)

#### Route Stops
- `POST /api/route-stops` — Create (parent)
- `GET /api/route-stops/unconfirmed` — Pending review (driver)
- `PATCH /api/route-stops/{id}/confirm` — Confirm (driver)
- `PATCH /api/route-stops/{id}/reject` — Reject (driver)

#### Active Routes
- `GET /api/active_routes` — List
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
- **Idempotency** — Redis-backed idempotency keys (24-hour TTL) prevent duplicate charges
- **Async Webhook Processing** — RabbitMQ decouples webhook receipt from processing
- **Real-time Updates** — Mercure pushes private payment status events to the subscribing parent app
- **Two-token Mercure auth** — API JWT (RSA) and Mercure subscriber JWT (HMAC-SHA256) are separate
- **Rate Limiting** — 10 requests/minute per IP on payment endpoints
- **Retry Logic** — exponential backoff (1 s → 2 s → 4 s), max 3 retries, dead-letter on failure
- **Token Encryption** — driver OAuth tokens encrypted at rest with libsodium secretbox

### Payment Flow

```
Driver (once)
  └── GET /oauth/mercadopago/connect → MP OAuth → encrypted tokens in DB

Parent
  └── POST /api/payments/create-preference
        └── returns { init_point, payment_id }

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

#### Create Payment Preference
```http
POST /api/payments/create-preference
Authorization: Bearer {api-jwt}
Content-Type: application/json

{
  "driver_id": 42,
  "student_ids": [1, 2],
  "amount": 3500.00,
  "description": "Transporte escolar — marzo 2026",
  "currency": "ARS",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response `201`:**
```json
{
  "payment_id": 123,
  "preference_id": "123456-abc-def",
  "init_point": "https://www.mercadopago.com/checkout/v1/redirect?pref_id=...",
  "status": "pending",
  "amount": "3500.00",
  "currency": "ARS"
}
```

#### Check Payment Status
```http
GET /api/payments/{id}/status
Authorization: Bearer {api-jwt}
```

#### Mercure Subscriber Token (exchange API JWT → Mercure JWT)
```http
GET /api/mercure/token?payment_id={id}
Authorization: Bearer {api-jwt}
```

Returns `{ token, hub_url, topics }`. Use `token` only with the Mercure hub, never for API calls.

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

# Mercure
MERCURE_URL=https://your-domain.com/.well-known/mercure
MERCURE_PUBLIC_URL=https://your-domain.com/.well-known/mercure
MERCURE_JWT_SECRET="change-this-to-a-strong-secret"

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

**API:** https://localhost | **Docs:** https://localhost/api/docs

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

apiClient.interceptors.response.use(
  (r) => r,
  async (err) => {
    if (err.response?.status === 401) await AsyncStorage.removeItem('jwt_token');
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
  await AsyncStorage.setItem('jwt_token', data.token);
  return data;
};

export const logout = () => AsyncStorage.removeItem('jwt_token');
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
// api/payment.js
import apiClient from './client';
import { v4 as uuidv4 } from 'uuid';
import { Linking } from 'react-native';

export const initiatePayment = async (driverId, studentIds, amount, description) => {
  const { data } = await apiClient.post('/payments/create-preference', {
    driver_id: driverId,
    student_ids: studentIds,
    amount, description,
    currency: 'ARS',
    idempotency_key: uuidv4(),
  });
  await Linking.openURL(data.init_point);
  return data.payment_id;
};

// Exchange API JWT → short-lived Mercure JWT for a single payment topic
export const getMercureToken = (paymentId) =>
  apiClient.get('/mercure/token', { params: { payment_id: paymentId } })
    .then((r) => r.data);
```

## 🔒 Security Features

- JWT-based stateless authentication for all `/api/*` routes
- Custom `RouteManagementVoter` for runtime driver privilege elevation
- Role-based authorization with hierarchical permissions (ROLE_SCHOOL_ADMIN → ROLE_DRIVER, ROLE_PARENT)
- Multi-tenant Doctrine filter — automatic per-request school context isolation
- HTTPS/TLS via Caddy; libsodium secretbox for token and message encryption
- Webhook HMAC-SHA256 signature validation with replay-attack prevention
- CSRF-protected MP OAuth flow (Redis-backed single-use state tokens, 10-min TTL)
- Private Mercure topics for payments and emergency chat; subscribers require a valid JWT
- Rate limiting on GPS ingestion (per driver) and payment endpoints (per IP)

## 📊 Performance Considerations

- **Redis first** — GPS `getDriverLocation` reads Redis (< 15 s TTL) before hitting MySQL
- **Async fanout** — GPS side-effects (geofencing, Mercure, proximity) are fully non-blocking
- **Three RabbitMQ transports** — tracking, webhooks, and general async are independently scalable
- **Database indexing** — all high-frequency query columns indexed
- **FrankenPHP worker mode** — application boots once, handles thousands of requests in-process
- **Pagination** — all collection endpoints are paginated (default 20, max 50 per page)

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
