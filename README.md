# ZigZag School Transportation Management System

A comprehensive, real-time school transportation management system designed to streamline and secure student transport operations. Built with modern PHP/Symfony, featuring multi-tenancy, real-time GPS tracking, route optimization, and comprehensive safety features.

## 🎯 Overview

ZigZag provides schools, parents, and drivers with a complete solution for managing school bus operations. The system ensures student safety through real-time tracking, automated notifications, and comprehensive check-in/check-out mechanisms.

### Key Highlights

- **Multi-tenant Architecture**: Support multiple schools with complete data isolation
- **Real-time GPS Tracking**: Live bus location updates every 5-10 seconds
- **Route Optimization**: Intelligent route planning with Google Maps integration
- **Safety First**: Comprehensive check-in/check-out logging and safety audits
- **Multi-channel Notifications**: Push, SMS, and Email alerts
- **Performance Analytics**: Detailed metrics and reporting for operational insights

## 🏗️ Technology Stack

### Backend Framework
- **PHP 8.3+** - Modern PHP with type safety and performance improvements
- **Symfony 7.3** - Enterprise-grade PHP framework
- **API Platform 4** - REST and GraphQL API development framework
- **Doctrine ORM** - Database abstraction and entity management

### Database & Caching
- **MySQL 8.4** - Primary relational database
- **Redis 8.4** - Caching and session management

### Message Queue
- **RabbitMQ 4.2** - Asynchronous task processing and event handling

### External Services
- **Google Maps APIs**:
  - Places API - Address validation and geocoding
  - Routes API - Route calculation and optimization
  - Distance Matrix API - Travel time and distance calculations
- **Firebase Cloud Messaging (FCM)** - Push notifications
- **SMS Provider** - SMS notifications (configurable)
- **Symfony Mailer** - Email notifications

### Authentication & Security
- **JWT (LexikJWTAuthenticationBundle)** - Stateless authentication
- **RBAC** - Role-based access control with hierarchical permissions
- **Multi-tenant Filtering** - Automatic school-based data isolation

### Development Tools
- **Docker & Docker Compose** - Containerized development environment
- **FrankenPHP** - High-performance PHP server
- **Caddy** - Automatic HTTPS and HTTP/3 support

## 📐 Architecture

### System Architecture

```
┌─────────────────┐
│  React Native   │
│  Mobile Apps    │
│  (iOS/Android)  │
└────────┬────────┘
         │ HTTPS/REST
         │
┌────────▼─────────────────────────────────────────┐
│              API Gateway (Symfony)                │
│  ┌─────────────────────────────────────────────┐ │
│  │  JWT Authentication & Authorization         │ │
│  │  Multi-tenant Context Filtering             │ │
│  └─────────────────────────────────────────────┘ │
│                                                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │  User    │  │  Route   │  │  Safety  │      │
│  │  Service │  │  Service │  │  Service │      │
│  └──────────┘  └──────────┘  └──────────┘      │
│                                                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │Location  │  │Notification│ │Analytics │      │
│  │Service   │  │  Service  │  │ Service  │      │
│  └──────────┘  └──────────┘  └──────────┘      │
└───────────────────┬───────────────────────────────┘
                    │
    ┌───────────────┼───────────────┐
    │               │               │
┌───▼────┐    ┌────▼────┐    ┌────▼─────┐
│ MySQL  │    │  Redis  │    │ RabbitMQ │
│   DB   │    │  Cache  │    │  Queue   │
└────────┘    └─────────┘    └──────────┘
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
          └── Attendance Records

Relationships:
- School → Address (one-to-one, School owns)
- User → Address (one-to-one, User owns)
- Student ↔ User (many-to-many, Student owns)
- User ↔ Driver (one-to-one, Driver owns)
- Driver ↔ Vehicle (one-to-one, Driver owns)
```

### Multi-tenant Data Isolation

The system implements automatic multi-tenant filtering through:
- **Doctrine Filter**: Automatically filters queries by school context
- **Event Subscriber**: Enables filter on every request based on authenticated user
- **Super Admin Override**: System administrators can access data across all schools

## 🚀 Features

### Phase 1: Identity & Access Management ✅

**Multi-tenant User Management**
- Role-based access control (RBAC) with hierarchical roles
- JWT-based authentication
- Automatic school context filtering
- Support for multiple user roles: Parent, Driver, School Admin, Super Admin

**Entities Implemented:**
- School (one-to-one relationship with Address)
- User (with roles: ROLE_USER, ROLE_PARENT, ROLE_DRIVER, ROLE_SCHOOL_ADMIN, ROLE_SUPER_ADMIN; one-to-one relationship with Address)
- Student (many-to-many relationship with User as parents)
- Driver (one-to-one relationship with User)
- Vehicle (one-to-one relationship with Driver)
- Address (geocoded locations, shared by User and School)

### Phase 2: Route Planning & Optimization ✅

**Route Management**
- Morning and afternoon route templates
- Route optimization with stop sequencing
- Google Maps integration for routing
- Estimated time and distance calculations
- Parent-initiated route stop creation workflow
- Driver confirmation/rejection system for route stops
- Automatic filtering of confirmed stops in optimization

**Entities Implemented:**
- Route (templates with school association)
- RouteStop (individual stops with estimated arrival times and confirmation status)

**Parent-Driver Route Stop Workflow:**
1. Parents create route stops for their students via `/api/route-stops`
2. System validates parent-student relationship and school associations
3. Route stops are created with `isConfirmed=false` by default
4. Drivers view unconfirmed stops via `/api/route-stops/unconfirmed`
5. Drivers can confirm (`/api/route-stops/{id}/confirm`) or reject (`/api/route-stops/{id}/reject`) stops
6. Only stops with `isActive=true` AND `isConfirmed=true` are included in route optimization

### Phase 3: Real-time Tracking & Operations ✅

**Live GPS Tracking**
- Driver location updates (5-10 second intervals)
- Real-time bus location on routes
- Geofencing for automatic arrival detection
- Location history storage

**Attendance & Manifest Management**
- Student check-in/check-out workflow
- Timestamped attendance records with GPS coordinates
- Absence reporting and route recalculation
- Digital manifest for drivers

**Entities Implemented:**
- ActiveRoute (daily route instances)
- ActiveRouteStop (real-time stop status tracking)
- LocationUpdate (GPS tracking data)
- Attendance (check-in/check-out records)
- Absence (student absence management)

### Phase 4: Dashboards & Portals ✅

**Parent Dashboard API** (`/api/parent/dashboard`)
- Real-time child status and location
- Active route information with bus location
- Estimated arrival times
- Today's attendance records
- Upcoming route schedule

**School Admin Dashboard API** (`/api/school-admin/dashboard`)
- School-wide statistics and metrics
- Active route monitoring with progress tracking
- Driver status and locations
- Automated alerts for delays and issues
- Today's operational metrics

### Phase 5: Notifications ✅

**Multi-provider Notification System**
- Email notifications (HTML formatted)
- SMS notifications (configurable provider)
- Push notifications (Firebase Cloud Messaging)
- User-defined notification preferences

**Event-driven Notifications**
- Bus arriving at stop
- Student picked up
- Student dropped off
- Route started
- Route delays
- Route cancellations

**Entities Implemented:**
- NotificationPreference (per-user notification settings)

**Events:**
- BusArrivingEvent
- StudentPickedUpEvent
- StudentDroppedOffEvent
- RouteStartedEvent
- RouteCompletedEvent

### Phase 6: Analytics & Safety Audits ✅

**Route Archiving & History**
- Automatic archiving of completed routes
- Performance metrics calculation
- Historical data retention
- Background job processing (`app:archive-routes`)

**Performance Analytics APIs**
- `/api/reports/performance` - Comprehensive performance reports
- `/api/reports/efficiency` - Distance and time efficiency metrics
- `/api/reports/top-performing` - Best performing routes
- `/api/reports/comparative` - Period-over-period comparisons

**Safety Audit System**
- `/api/safety/audit` - End-to-end safety verification
- Check-in/check-out validation
- Orphaned record detection
- Missing checkout identification
- Duplicate record detection
- Time anomaly detection
- Overall safety scoring (0-100)

**Entities Implemented:**
- ArchivedRoute (historical route data with metrics)

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

**Response:**
```json
{
  "@context": "/api/contexts/User",
  "@id": "/api/users/1",
  "@type": "User",
  "id": 1,
  "email": "newuser@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "phoneNumber": "+1234567890",
  "roles": ["ROLE_PARENT", "ROLE_USER"]
}
```

**Note:** This endpoint is publicly accessible and does not require authentication. After registration, users can login to obtain a JWT token.

#### Login
```http
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200..."
}
```

**Use the token in subsequent requests:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### API Resources (RESTful)

All API Platform resources support standard REST operations:

#### Users
- `GET /api/users` - List users (requires authentication)
- `GET /api/users/{id}` - Get user details (requires authentication)
- `POST /api/users` - Create user (publicly accessible for registration)
- `PATCH /api/users/{id}` - Update user (requires authentication)
- `DELETE /api/users/{id}` - Delete user (requires authentication)

#### Students
- `GET /api/students` - List students (filtered by school)
- `GET /api/students/{id}` - Get student details
- `POST /api/students` - Create student (admin only)
- `PATCH /api/students/{id}` - Update student
- `DELETE /api/students/{id}` - Delete student (admin only)

#### Routes
- `GET /api/routes` - List routes (filtered by school)
- `GET /api/routes/{id}` - Get route details
- `POST /api/routes` - Create route (admin only)
- `PATCH /api/routes/{id}` - Update route
- `DELETE /api/routes/{id}` - Delete route (admin only)

#### Route Stops
- `POST /api/route-stops` - Create route stop (parent/user only)
- `GET /api/route-stops/unconfirmed` - List unconfirmed stops for driver's routes (driver only)
- `PATCH /api/route-stops/{id}/confirm` - Confirm a route stop (driver only)
- `PATCH /api/route-stops/{id}/reject` - Reject/deactivate a route stop (driver only)

#### Active Routes
- `GET /api/active_routes` - List active routes
- `GET /api/active_routes/{id}` - Get active route details
- `POST /api/active_routes` - Create active route (admin only)
- `PATCH /api/active_routes/{id}` - Update route status (driver/admin)
- `DELETE /api/active_routes/{id}` - Cancel route (admin only)

#### Location Updates
- `POST /api/location_updates` - Submit GPS location (driver only)
- `GET /api/location_updates` - Get location history

#### Attendance
- `POST /api/attendances` - Record check-in/check-out (driver/admin)
- `GET /api/attendances` - Get attendance records
- `GET /api/attendances/{id}` - Get specific record

#### Notification Preferences
- `GET /api/notification_preferences/{id}` - Get user preferences
- `POST /api/notification_preferences` - Create preferences
- `PATCH /api/notification_preferences/{id}` - Update preferences

### Custom Endpoints

#### Parent Dashboard
```http
GET /api/parent/dashboard
Authorization: Bearer {token}
```

**Response:**
```json
{
  "children": [
    {
      "studentId": 1,
      "firstName": "John",
      "lastName": "Doe",
      "currentStatus": "picked_up",
      "activeRouteId": 42,
      "routeStatus": "in_progress",
      "busLocation": {
        "latitude": 40.7128,
        "longitude": -74.0060
      },
      "estimatedArrival": "2026-01-19T08:45:00+00:00",
      "lastUpdate": "2026-01-19T08:30:00+00:00"
    }
  ],
  "activeRoutes": [...],
  "todayAttendance": [...],
  "upcomingRoutes": [...]
}
```

#### School Admin Dashboard
```http
GET /api/school-admin/dashboard
Authorization: Bearer {token}
```

**Response:**
```json
{
  "statistics": {
    "totalStudents": 150,
    "totalDrivers": 8,
    "activeDrivers": 5,
    "totalRoutesToday": 12,
    "activeRoutes": 5,
    "completedRoutes": 7
  },
  "activeRoutes": [...],
  "driverStatuses": [...],
  "recentAlerts": [...],
  "todayMetrics": {...}
}
```

#### Route Stop Creation (Parent)
```http
POST /api/route-stops
Authorization: Bearer {token}
Content-Type: application/json

{
  "route_id": 1,
  "student_id": 5,
  "address_id": 12,
  "stop_order": 3,
  "geofence_radius": 50,
  "notes": "Please wait at the corner"
}
```

**Response:**
```json
{
  "success": true,
  "route_stop_id": 42,
  "message": "Route stop created successfully. Waiting for driver confirmation."
}
```

#### List Unconfirmed Route Stops (Driver)
```http
GET /api/route-stops/unconfirmed
Authorization: Bearer {token}
```

**Response:**
```json
{
  "unconfirmed_stops": [
    {
      "id": 42,
      "route_id": 1,
      "route_name": "Morning Route A",
      "student_id": 5,
      "student_name": "John Doe",
      "address": {
        "id": 12,
        "street": "123 Main St",
        "latitude": "40.7128",
        "longitude": "-74.0060"
      },
      "notes": "Please wait at the corner",
      "created_at": "2026-02-09 10:30:00"
    }
  ],
  "total": 1
}
```

#### Confirm Route Stop (Driver)
```http
PATCH /api/route-stops/42/confirm
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Route stop confirmed successfully",
  "route_stop_id": 42
}
```

#### Reject Route Stop (Driver)
```http
PATCH /api/route-stops/42/reject
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Route stop rejected successfully",
  "route_stop_id": 42
}
```

## 🛠️ Command Line Tools

### Create User Command

Create users directly from the command line:

```bash
# Create a regular user
php bin/console app:create-user user@example.com password123 John Doe "555-1234" "12345678"

# Create a super admin user
php bin/console app:create-user admin@example.com password123 Jane Admin "555-5678" "87654321" --super-admin

# Using short option
php bin/console app:create-user admin@example.com password123 Jane Admin "555-5678" "87654321" -s
```

**Command arguments:**
- `email` - User email address (required)
- `password` - User password (required)
- `firstName` - User first name (required)
- `lastName` - User last name (required)
- `phoneNumber` - User phone number (required)
- `identificationNumber` - 8-10 digit identification number (required)

**Options:**
- `--super-admin` or `-s` - Create user with ROLE_SUPER_ADMIN

## 🔧 Installation & Setup

### Prerequisites

- Docker & Docker Compose (v2.10+)
- Git

### Quick Start

1. **Clone the repository:**
```bash
git clone https://github.com/yourusername/zigzag-api.git
cd zigzag-api
```

2. **Configure environment variables:**
```bash
cp .env .env.local
```

Edit `.env.local` and configure:
```bash
# Database (already configured for Docker)
DATABASE_URL="mysql://zigzag:ZigZagTech!2026@127.0.0.1:3306/zigzag?serverVersion=8.4&charset=utf8mb4"

# JWT Authentication
JWT_PASSPHRASE=your-secure-passphrase

# Google Maps API
GOOGLE_MAPS_API_KEY=your-google-maps-api-key

# Notifications
MAIL_FROM_EMAIL=noreply@yourschool.com
MAIL_FROM_NAME="Your School Transportation"
SMS_API_KEY=your-sms-api-key
SMS_API_URL=https://api.smsprovider.com/send
FCM_SERVER_KEY=your-fcm-server-key
```

3. **Start Docker containers:**

For development:
```bash
make up dev
```

For production:
```bash
make up prod
```

Or using docker compose directly:
```bash
docker compose --env-file .env.local up -d --wait
```

4. **Install dependencies:**
```bash
docker compose exec php composer install
```

5. **Generate JWT keys:**
```bash
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

6. **Run database migrations:**
```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

7. **Access the application:**
- API: https://localhost
- API Documentation: https://localhost/api/docs

### Background Jobs

Set up a cron job to archive old routes:
```bash
# Archive routes older than 7 days, run daily at 2 AM
0 2 * * * cd /path/to/project && docker compose exec php php bin/console app:archive-routes --days=7
```

## 📱 Mobile App Integration Guide (React Native)

### Setup

1. **Install dependencies:**
```bash
npm install axios @react-native-async-storage/async-storage
# For push notifications
npm install @react-native-firebase/app @react-native-firebase/messaging
# For maps
npm install react-native-maps
# For geolocation
npm install @react-native-community/geolocation
```

2. **Create API client:**

```javascript
// api/client.js
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const API_BASE_URL = 'https://your-api-domain.com/api';

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add authentication interceptor
apiClient.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('jwt_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle token expiration
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await AsyncStorage.removeItem('jwt_token');
      // Navigate to login screen
    }
    return Promise.reject(error);
  }
);

export default apiClient;
```

### Authentication

```javascript
// api/auth.js
import apiClient from './client';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const register = async (email, password, firstName, lastName, phoneNumber) => {
  const response = await apiClient.post('/users', {
    email,
    password,
    firstName,
    lastName,
    phoneNumber,
    roles: ['ROLE_PARENT'],
  });
  return response.data;
};

export const login = async (email, password) => {
  const response = await apiClient.post('/login', { email, password });
  const { token } = response.data;
  await AsyncStorage.setItem('jwt_token', token);
  return response.data;
};

export const logout = async () => {
  await AsyncStorage.removeItem('jwt_token');
};
```

### Parent Dashboard Integration

```javascript
// api/parent.js
import apiClient from './client';

export const getParentDashboard = async () => {
  const response = await apiClient.get('/parent/dashboard');
  return response.data;
};

// Example component
import React, { useEffect, useState } from 'react';
import { View, Text, ActivityIndicator } from 'react-native';
import { getParentDashboard } from '../api/parent';

const ParentDashboardScreen = () => {
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDashboard();
    const interval = setInterval(loadDashboard, 30000); // Refresh every 30s
    return () => clearInterval(interval);
  }, []);

  const loadDashboard = async () => {
    try {
      const data = await getParentDashboard();
      setDashboard(data);
    } catch (error) {
      console.error('Failed to load dashboard:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <ActivityIndicator size="large" />;

  return (
    <View>
      {dashboard.children.map((child) => (
        <View key={child.studentId}>
          <Text>{child.firstName} {child.lastName}</Text>
          <Text>Status: {child.currentStatus}</Text>
          {child.busLocation && (
            <Text>
              Bus at: {child.busLocation.latitude}, {child.busLocation.longitude}
            </Text>
          )}
        </View>
      ))}
    </View>
  );
};
```

### Real-time Location Tracking (Driver App)

```javascript
// hooks/useLocationTracking.js
import { useEffect, useState } from 'react';
import Geolocation from '@react-native-community/geolocation';
import apiClient from '../api/client';

export const useLocationTracking = (activeRouteId, driverId) => {
  const [tracking, setTracking] = useState(false);
  const [watchId, setWatchId] = useState(null);

  const sendLocation = async (coords) => {
    try {
      await apiClient.post('/location_updates', {
        driver: `/api/drivers/${driverId}`,
        activeRoute: `/api/active_routes/${activeRouteId}`,
        latitude: coords.latitude.toString(),
        longitude: coords.longitude.toString(),
        speed: coords.speed?.toString(),
        heading: coords.heading?.toString(),
        accuracy: coords.accuracy?.toString(),
        timestamp: new Date().toISOString(),
      });
    } catch (error) {
      console.error('Failed to send location:', error);
    }
  };

  const startTracking = () => {
    const id = Geolocation.watchPosition(
      (position) => sendLocation(position.coords),
      (error) => console.error(error),
      {
        enableHighAccuracy: true,
        distanceFilter: 10,
        interval: 5000,
        fastestInterval: 5000,
      }
    );
    setWatchId(id);
    setTracking(true);
  };

  const stopTracking = () => {
    if (watchId !== null) {
      Geolocation.clearWatch(watchId);
      setWatchId(null);
      setTracking(false);
    }
  };

  useEffect(() => {
    return () => {
      if (watchId !== null) Geolocation.clearWatch(watchId);
    };
  }, [watchId]);

  return { tracking, startTracking, stopTracking };
};
```

### Map Integration

```javascript
// components/BusMap.js
import React from 'react';
import MapView, { Marker } from 'react-native-maps';

const BusMap = ({ busLocation, stops }) => {
  return (
    <MapView
      style={{ flex: 1 }}
      region={{
        latitude: busLocation.latitude,
        longitude: busLocation.longitude,
        latitudeDelta: 0.05,
        longitudeDelta: 0.05,
      }}
    >
      <Marker
        coordinate={busLocation}
        title="School Bus"
        pinColor="blue"
      />
      {stops.map((stop, index) => (
        <Marker
          key={index}
          coordinate={{
            latitude: parseFloat(stop.address.latitude),
            longitude: parseFloat(stop.address.longitude),
          }}
          title={`Stop ${stop.stopOrder}`}
          pinColor={stop.status === 'completed' ? 'green' : 'red'}
        />
      ))}
    </MapView>
  );
};

export default BusMap;
```

### Push Notifications

```javascript
// messaging/firebase.js
import messaging from '@react-native-firebase/messaging';

export const requestUserPermission = async () => {
  const authStatus = await messaging().requestPermission();
  if (authStatus === messaging.AuthorizationStatus.AUTHORIZED) {
    const token = await messaging().getToken();
    // Send token to backend to store with user profile
    return token;
  }
};

export const setupNotificationListeners = () => {
  messaging().onMessage(async (remoteMessage) => {
    console.log('Notification received:', remoteMessage);
  });

  messaging().setBackgroundMessageHandler(async (remoteMessage) => {
    console.log('Background notification:', remoteMessage);
  });
};
```

## 🔒 Security Features

- JWT-based stateless authentication
- Role-based authorization with hierarchical permissions
- Multi-tenant data isolation (automatic school filtering)
- HTTPS/TLS encryption for all communications
- Input validation and sanitization
- SQL injection protection via Doctrine ORM
- CORS configuration for mobile apps

## 📊 Performance Considerations

- **Database Indexing**: All queries optimized with proper indexes
- **Caching**: Redis caching for frequently accessed data
- **Pagination**: All collection endpoints support pagination
- **Async Processing**: Heavy operations via RabbitMQ
- **Connection Pooling**: Efficient database connection management
- **Worker Mode**: FrankenPHP worker mode for blazing-fast performance

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is proprietary software. All rights reserved.

## 👥 Support

For support, email support@zigzag.com or open an issue in the repository.

---

**Built with ❤️ for safer school transportation**
