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
