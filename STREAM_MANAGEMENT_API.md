# Stream Management API Documentation

## Overview

This API provides comprehensive stream management functionality for streamers with subscription-based duration limits. Streamers can schedule, manage, and track their streams while respecting their subscription plan's daily streaming hour limits.

## Key Features

- **Subscription-Based Duration Limits**: Streamers can only schedule streams within their subscription plan's daily hour limits
- **Stream Lifecycle Management**: Schedule, start, end, and cancel streams
- **Conflict Detection**: Prevents overlapping streams (30-minute buffer)
- **Real-time Statistics**: Track daily usage and remaining streaming time
- **Authorization**: All endpoints require Sanctum authentication

## Subscription Plans

The system includes three subscription plans:

| Plan | Daily Hours | Price | Features |
|------|-------------|-------|----------|
| Basic | 2 hours | $9.99 | Basic analytics, Standard support |
| Pro | 5 hours | $19.99 | Advanced analytics, Priority support, Custom overlays |
| Premium | 12 hours | $39.99 | Premium analytics, 24/7 support, Custom branding |

## API Endpoints

### Authentication Required
All endpoints require `Authorization: Bearer {token}` header.

### 1. Get Streams
```http
GET /api/streamer/streams
```

**Response:**
```json
{
  "streams": {
    "data": [...],
    "pagination": {...}
  },
  "daily_limit_hours": 5,
  "has_active_subscription": true
}
```

### 2. Add Stream
```http
POST /api/streamer/streams
```

**Request Body:**
```json
{
  "title": "Gaming Session",
  "description": "Playing the latest games",
  "scheduled_start": "2024-01-15T20:00:00Z",
  "estimated_duration": 120
}
```

**Success Response (201):**
```json
{
  "message": "Stream scheduled successfully",
  "stream": {...},
  "remaining_daily_hours": 3
}
```

**Error Response (422) - Exceeds Limit:**
```json
{
  "message": "Adding this stream would exceed your daily streaming limit",
  "daily_limit_hours": 5,
  "remaining_hours": 1.5,
  "requested_hours": 2
}
```

**Error Response (422) - No Subscription:**
```json
{
  "message": "You need an active subscription to schedule streams"
}
```

### 3. Update Stream
```http
PUT /api/streamer/streams/{streamId}
```

**Request Body:**
```json
{
  "title": "Updated Stream Title",
  "estimated_duration": 180,
  "scheduled_start": "2024-01-15T21:00:00Z"
}
```

**Success Response (200):**
```json
{
  "message": "Stream updated successfully",
  "stream": {...}
}
```

### 4. Delete Stream
```http
DELETE /api/streamer/streams/{streamId}
```

**Success Response (200):**
```json
{
  "message": "Stream deleted successfully"
}
```

### 5. Start Stream
```http
POST /api/streamer/streams/{streamId}/start
```

**Success Response (200):**
```json
{
  "message": "Stream started successfully",
  "stream": {...}
}
```

**Error Response (422):**
```json
{
  "message": "You are already streaming. End your current stream first."
}
```

### 6. End Stream
```http
POST /api/streamer/streams/{streamId}/end
```

**Success Response (200):**
```json
{
  "message": "Stream ended successfully",
  "stream": {...}
}
```

### 7. Get Streaming Statistics
```http
GET /api/streamer/streaming-stats?date=2024-01-15
```

**Single Date Response:**
```json
{
  "date": "2024-01-15",
  "daily_limit_hours": 5,
  "used_hours": 3.5,
  "remaining_hours": 1.5,
  "streams": [...]
}
```

**Date Range Request:**
```http
GET /api/streamer/streaming-stats?start_date=2024-01-01&end_date=2024-01-31
```

**Date Range Response:**
```json
{
  "start_date": "2024-01-01",
  "end_date": "2024-01-31",
  "total_streams": 25,
  "total_hours": 87.5,
  "streams_by_status": {
    "completed": 20,
    "scheduled": 3,
    "cancelled": 2
  },
  "daily_limit_hours": 5,
  "has_active_subscription": true
}
```

## Stream Status Flow

1. **scheduled** → Stream is planned for the future
2. **live** → Stream is currently active
3. **completed** → Stream has ended normally
4. **cancelled** → Stream was cancelled before starting

## Business Rules

### Duration Limits
- Streamers can only schedule streams within their subscription's daily hour limit
- The system calculates total duration for each calendar day
- Limits are enforced when adding or updating streams

### Stream Scheduling
- Streams can be started up to 15 minutes before scheduled time
- 30-minute buffer prevents overlapping streams
- Only one stream can be live at a time per streamer

### Subscription Requirements
- Active subscription required to schedule streams
- Expired subscriptions prevent new stream creation
- Existing scheduled streams remain when subscription expires

## Error Codes

| Code | Description |
|------|-------------|
| 401 | Unauthorized - Invalid or missing token |
| 403 | Forbidden - Not a streamer account |
| 404 | Not Found - Stream or streamer not found |
| 422 | Validation Error - Invalid data or business rule violation |

## Testing

Run the comprehensive test suite:
```bash
php artisan test --filter=StreamManagementTest
```

The test suite covers:
- Adding streams within limits
- Preventing streams that exceed limits
- Subscription requirement enforcement
- Multiple stream scheduling
- Stream updates with limit validation
- Stream lifecycle (start/end)
- Statistics calculation

## Database Schema

### Key Tables
- `streamers` - Streamer profiles with current stream tracking
- `subscription_plans` - Available subscription tiers
- `streamer_subscriptions` - Active/expired subscriptions
- `planned_streams` - Scheduled streams with status tracking

### Key Relationships
- Streamer → User (belongs to)
- Streamer → Subscriptions (has many)
- Streamer → Planned Streams (has many)
- Streamer → Current Stream (belongs to, nullable)