# Campaign/Dungeon Runtime API Documentation

**Version**: 1.0  
**Last Updated**: 2026-02-17  
**Status**: Active Development

## Document Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-02-17 | Initial comprehensive documentation |
| 1.0.1 | 2026-02-17 | Added Quick Reference table, practical examples, debugging tools, and testing patterns |

## Quick Reference

| Endpoint | Method | Status | Description |
|----------|--------|--------|-------------|
| `/api/campaign/{campaign_id}/state` | GET | ✅ | Get campaign state |
| `/api/campaign/{campaign_id}/state` | POST | ⚠️ | Update campaign state (needs route) |
| `/api/dungeon/{dungeon_id}/state` | GET | ✅ | Get dungeon state |
| `/api/dungeon/{dungeon_id}/state` | POST | ✅ | Update dungeon state |
| `/api/dungeon/{dungeon_id}/room/{room_id}/state` | GET | ✅ | Get room state |
| `/api/dungeon/{dungeon_id}/room/{room_id}/state` | POST | ⚠️ | Update room state (needs route) |
| `/api/campaign/{campaign_id}/entity/spawn` | POST | ✅ | Spawn entity |
| `/api/campaign/{campaign_id}/entity/{instance_id}/move` | POST | ✅ | Move entity |
| `/api/campaign/{campaign_id}/entity/{instance_id}` | DELETE | ✅ | Despawn entity |
| `/api/campaign/{campaign_id}/entities` | GET | ✅ | List entities |

**Legend**: ✅ Fully configured | ⚠️ Controller exists, route needs configuration

## Table of Contents

1. [Quick Reference](#quick-reference)
2. [Overview](#overview)
3. [Authentication & Authorization](#authentication--authorization)
4. [Campaign State Endpoints](#campaign-state-endpoints)
5. [Dungeon State Endpoints](#dungeon-state-endpoints)
6. [Room State Endpoints](#room-state-endpoints)
7. [Entity Lifecycle Endpoints](#entity-lifecycle-endpoints)
8. [Entity Instance Model](#entity-instance-model)
9. [Visibility & Detection Rules](#visibility--detection-rules)
10. [Error Response Format](#error-response-format)
11. [Examples](#examples)
12. [Implementation Status](#implementation-status)
13. [Best Practices](#best-practices)
14. [Troubleshooting](#troubleshooting)
15. [Related Documentation](#related-documentation)

## Overview

This document describes the runtime APIs for managing game state, entities, and visibility rules in the Dungeon Crawler campaign system. These APIs enable real-time gameplay by providing state management, entity lifecycle control, and visibility/detection mechanics.

### Key Features

- **Stateful Campaign Management**: Track campaign progress, active locations, and player progression
- **Dungeon & Room State**: Maintain exploration state, cleared rooms, and current location
- **Entity Lifecycle**: Spawn, move, and despawn entities (NPCs, obstacles, traps, hazards)
- **Visibility & Detection**: Implement fog of war and hidden entity mechanics
- **Optimistic Locking**: Prevent concurrent update conflicts with version-based locking
- **JSON Responses**: All endpoints return consistent JSON structure with success/error handling

### Architecture

```
Campaign (Global State)
└── Dungeons (Instance State)
    └── Rooms (Local State)
        ├── Layout (Hexes)
        ├── Contents (Template)
        └── Entities (Runtime Instances)
```

### Use Cases

- **Web Client**: JavaScript/TypeScript frontend for browser-based gameplay
- **Mobile App**: Native mobile client with offline caching and sync
- **Game Masters**: Admin tools for managing campaigns and entities
- **Testing & Debug**: Developer tools for testing game mechanics

## Authentication & Authorization

All endpoints require:
- User authentication (Drupal session)
- Permission: `access dungeoncrawler characters`
- Campaign ownership validation (enforced via `CampaignAccessCheck`)

Non-owners receive **403 Forbidden** responses.

---

## Campaign State Endpoints

### GET `/api/campaign/{campaignId}/state`

**✓ Implementation Status**: Fully implemented (controller and route configured).

Retrieve current campaign state with optimistic locking version.

**Response:**
```json
{
  "success": true,
  "data": {
    "campaignId": "123",
    "state": {
      "created_by": 1,
      "started": true,
      "progress": [
        {"type": "dungeon_entered", "timestamp": 1234567890}
      ],
      "active_hex": "q0r0",
      "metadata": {}
    },
    "version": 42,
    "updatedAt": "2026-02-14T05:00:00+00:00"
  },
  "version": 42
}
```

**Error Responses:**
- `403` - Access denied to campaign
- `404` - Campaign not found

---

### POST `/api/campaign/{campaignId}/state`

**⚠️ Implementation Status**: Controller implemented (`CampaignStateController::setState`) but route not configured in `dungeoncrawler_content.routing.yml`. To enable this endpoint, add:
```yaml
dungeoncrawler_content.api.campaign_state_set:
  path: '/api/campaign/{campaign_id}/state'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\CampaignStateController::setState'
  methods: [POST]
  requirements:
    _permission: 'access dungeoncrawler characters'
    _campaign_access: 'TRUE'
    campaign_id: '\d+'
  options:
    _format: json
```

Update campaign state with optimistic locking.

**Request Body:**
```json
{
  "expectedVersion": 42,
  "state": {
    "created_by": 1,
    "started": true,
    "progress": [
      {"type": "dungeon_entered", "timestamp": 1234567890},
      {"type": "room_cleared", "timestamp": 1234567900}
    ],
    "active_hex": "q1r1"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "campaignId": "123",
    "state": { ... },
    "version": 43,
    "updatedAt": "2026-02-14T05:01:00+00:00"
  },
  "version": 43
}
```

**Error Responses:**
- `400` - Invalid JSON, missing state payload, or schema validation failure
- `403` - Access denied to campaign
- `409` - Version conflict (returns current version and state)

**Schema Validation:**

The state payload is validated against `campaign.schema.json`:

| Field | Type | Validation | Description |
|-------|------|------------|-------------|
| `created_by` | integer | Required, ≥ 1 | User ID who created the campaign |
| `started` | boolean | Required | Whether campaign has started |
| `progress` | array | Required | Array of event objects |
| `progress[].type` | string | Required | Event type identifier |
| `progress[].timestamp` | integer | Required | Unix timestamp |
| `active_hex` | string | Optional | Current hex coordinates (e.g., "q0r0") |
| `metadata` | object | Optional | Additional campaign metadata |

**Example Valid State:**
```json
{
  "created_by": 1,
  "started": true,
  "progress": [
    {"type": "dungeon_entered", "timestamp": 1234567890}
  ],
  "active_hex": "q0r0",
  "metadata": {}
}
```

---

## Dungeon State Endpoints

### GET `/api/dungeon/{dungeonId}/state?campaignId={campaignId}`

**✓ Implementation Status**: Fully implemented (controller and route configured).

Retrieve dungeon state for a campaign.

**Query Parameters:**
- `campaignId` (required): Campaign ID

**Response:**
```json
{
  "success": true,
  "data": {
    "dungeonId": "dungeon-001",
    "campaignId": 123,
    "name": "The Forgotten Catacombs",
    "description": "Ancient underground complex",
    "theme": "undead",
    "state": {
      "exploredRooms": ["room-1", "room-2"],
      "currentRoom": "room-2"
    },
    "version": 15,
    "updatedAt": "2026-02-14T05:00:00+00:00"
  },
  "version": 15
}
```

**Error Responses:**
- `400` - Missing campaignId
- `403` - Access denied to campaign
- `404` - Dungeon not found

---

### POST `/api/dungeon/{dungeonId}/state`

**✓ Implementation Status**: Fully implemented (controller and route configured).

Update dungeon state.

**Request Body:**
```json
{
  "campaignId": 123,
  "expectedVersion": 15,
  "state": {
    "exploredRooms": ["room-1", "room-2", "room-3"],
    "currentRoom": "room-3",
    "dungeonId": "dungeon-001"
  }
}
```

**Response:** Same format as GET.

**Error Responses:**
- `400` - Invalid JSON, missing fields, or dungeonId mismatch
- `403` - Access denied to campaign
- `409` - Version conflict

---

## Room State Endpoints

### GET `/api/dungeon/{dungeonId}/room/{roomId}/state?campaignId={campaignId}`

**✓ Implementation Status**: Fully implemented (controller and route configured).

Retrieve room state with visibility and detection filtering applied.

**Response:**
```json
{
  "success": true,
  "data": {
    "campaignId": 123,
    "roomId": "room-3",
    "room": {
      "roomId": "room-3",
      "name": "Guard Chamber",
      "description": "A dimly lit chamber with stone pillars",
      "environmentTags": ["dark", "stone"],
      "layout": {
        "hexes": [
          {"id": "hex-1", "q": 0, "r": 0, "terrain": "stone"}
        ]
      },
      "contents": {
        "objects": [],
        "entities": [
          {
            "instanceId": "goblin-1",
            "type": "npc",
            "characterId": 456,
            "state": {
              "hexId": "hex-1",
              "hp": 8,
              "detected": true
            }
          }
        ]
      }
    },
    "state": {
      "isCleared": false,
      "visibleHexIds": ["hex-1"],
      "dungeonId": "dungeon-001"
    },
    "version": 1234567890,
    "updatedAt": "2026-02-14T05:00:00+00:00"
  }
}
```

**Visibility Rules:**
- Only hexes in `state.visibleHexIds` are returned in `layout.hexes`
- Only entities in visible hexes are included
- Traps are hidden unless `state.detected` is true
- Entities with `hidden: true` are hidden unless `state.detected` is true
- Runtime entities from `dc_campaign_characters` are merged with template contents

**Error Responses:**
- `400` - Missing campaignId or dungeonId mismatch
- `403` - Access denied to campaign
- `404` - Room or room state not found

---

### POST `/api/dungeon/{dungeonId}/room/{roomId}/state`

**⚠️ Implementation Status**: Controller implemented (`RoomStateController::setState`) but route not configured in `dungeoncrawler_content.routing.yml`. To enable this endpoint, add:
```yaml
dungeoncrawler_content.api.room_state_set:
  path: '/api/dungeon/{dungeon_id}/room/{room_id}/state'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\RoomStateController::setState'
  methods: [POST]
  requirements:
    _permission: 'access dungeoncrawler characters'
    dungeon_id: '[A-Za-z0-9_-]+'
    room_id: '[A-Za-z0-9_-]+'
  options:
    _format: json
```

Update room state.

**Request Body:**
```json
{
  "campaignId": 123,
  "expectedVersion": 1234567890,
  "state": {
    "dungeonId": "dungeon-001",
    "roomId": "room-3",
    "isCleared": true,
    "visibleHexIds": ["hex-1", "hex-2"]
  }
}
```

**Response:** Same format as GET.

**Error Responses:**
- `400` - Invalid JSON, missing fields, or ID mismatch
- `403` - Access denied to campaign
- `404` - Room state not found
- `409` - Version conflict

---

## Entity Lifecycle Endpoints

### POST `/api/campaign/{campaignId}/entity/spawn`

**✓ Implementation Status**: Fully implemented (controller and route configured).

Spawn a new entity instance in the campaign.

**Request Body:**
```json
{
  "type": "npc",
  "instanceId": "goblin-scout-1",
  "characterId": 456,
  "locationType": "room",
  "locationRef": "room-3",
  "stateData": {
    "hexId": "hex-5",
    "hp": 8,
    "maxHp": 8,
    "detected": false,
    "hidden": true
  }
}
```

**Parameters:**
- `type` (required): Entity type - `npc`, `obstacle`, `trap`, `hazard`, or `pc`
- `instanceId` (required): Unique instance identifier (scoped to campaign)
- `characterId` (optional): Reference to character library ID (for pc/npc)
- `locationType` (required): Location type - `room`, `dungeon`, or `tavern`
- `locationRef` (required): Location reference (e.g., room ID)
- `stateData` (optional): Entity-specific runtime state

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 789,
    "campaignId": 123,
    "type": "npc",
    "instanceId": "goblin-scout-1",
    "characterId": 456,
    "locationType": "room",
    "locationRef": "room-3",
    "stateData": { ... }
  }
}
```

**Error Responses:**
- `400` - Missing required fields, invalid type, or instanceId already exists
- `403` - Access denied to campaign

---

### POST `/api/campaign/{campaignId}/entity/{instanceId}/move`

**✓ Implementation Status**: Fully implemented (controller and route configured).

Move an entity to a new location.

**Request Body:**
```json
{
  "locationType": "room",
  "locationRef": "room-4"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 789,
    "campaignId": 123,
    "type": "npc",
    "instanceId": "goblin-scout-1",
    "characterId": 456,
    "locationType": "room",
    "locationRef": "room-4",
    "stateData": { ... }
  }
}
```

**Error Responses:**
- `400` - Invalid JSON or missing fields
- `403` - Access denied to campaign
- `404` - Entity not found

---

### DELETE `/api/campaign/{campaignId}/entity/{instanceId}`

**✓ Implementation Status**: Fully implemented (controller and route configured).

Despawn (remove) an entity from the campaign.

**Response:**
```json
{
  "success": true,
  "message": "Entity despawned successfully"
}
```

**Error Responses:**
- `403` - Access denied to campaign
- `404` - Entity not found

---

### GET `/api/campaign/{campaignId}/entities`

**✓ Implementation Status**: Fully implemented (controller and route configured).

List entities in a campaign with optional filtering.

**Query Parameters (all optional):**
- `locationType`: Filter by location type
- `locationRef`: Filter by location reference
- `type`: Filter by entity type

**Example:** `/api/campaign/123/entities?locationType=room&locationRef=room-3`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 789,
      "campaignId": 123,
      "type": "npc",
      "instanceId": "goblin-scout-1",
      "characterId": 456,
      "locationType": "room",
      "locationRef": "room-3",
      "stateData": { ... }
    }
  ],
  "count": 1
}
```

**Error Responses:**
- `403` - Access denied to campaign

---

## Entity Instance Model

Entity instances are stored in `dc_campaign_characters` table with the following structure:

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `campaign_id` | int | Campaign ID |
| `character_id` | int | Library character ID (0 for non-character entities) |
| `instance_id` | string | Unique instance identifier (scoped to campaign) |
| `type` | string | Entity type: `pc`, `npc`, `obstacle`, `trap`, `hazard` |
| `location_type` | string | Current location type: `room`, `dungeon`, `tavern` |
| `location_ref` | string | Location reference (e.g., room ID) |
| `state_data` | JSON | Entity-specific runtime state |
| `created` | timestamp | Creation timestamp |
| `updated` | timestamp | Last update timestamp |

**Entity Types:**
- **pc**: Player character instance
- **npc**: Non-player character (creature, NPC)
- **obstacle**: Physical obstacle (furniture, pillar, etc.)
- **trap**: Trap (hidden by default)
- **hazard**: Environmental hazard

**State Data Examples:**

NPC State:
```json
{
  "hexId": "hex-5",
  "hp": 8,
  "maxHp": 8,
  "conditions": [],
  "initiative": 15,
  "detected": true,
  "hidden": false
}
```

Trap State:
```json
{
  "hexId": "hex-10",
  "detected": false,
  "disarmed": false,
  "triggerType": "pressure_plate",
  "damage": "2d6 piercing"
}
```

---

## Visibility & Detection Rules

### Hex Visibility (Fog of War)
- Room state contains `visibleHexIds` array
- Only hexes in this array are returned in `layout.hexes`
- Entities/objects in non-visible hexes are filtered out

### Entity Detection
- **Traps**: Hidden by default unless `state.detected === true`
- **Hidden Entities**: Entities with `hidden: true` are hidden unless `state.detected === true`
- **NPCs/Obstacles**: Visible if in a visible hex (unless explicitly hidden)

### Example Workflow

1. **Room Entry**: Client requests room state - server returns only visible hexes and detected entities
2. **Perception Check**: Client makes perception check for hidden entities/traps
3. **Update Detection**: If successful, client updates room state to mark entities as detected
4. **State Propagation**: Server returns updated room state with newly detected entities visible

**Note**: Detection state is currently managed in the room state's `visibleHexIds` and entity `detected` flags. A dedicated `detectedEntities` array in room state can track which entities have been discovered.

---

## Error Response Format

All error responses follow this consistent format:

```json
{
  "success": false,
  "error": "Human-readable error message",
  "validation_errors": ["Specific validation failure 1", "..."] // Optional
}
```

### HTTP Status Codes

| Code | Meaning | Common Causes | Example Response |
|------|---------|---------------|------------------|
| `400` | Bad Request | Invalid JSON, missing required fields, schema validation failure | `{"success": false, "error": "Missing state payload"}` |
| `403` | Forbidden | Access denied, not campaign owner | `{"success": false, "error": "Access denied to campaign"}` |
| `404` | Not Found | Resource doesn't exist | `{"success": false, "error": "Campaign not found"}` |
| `409` | Conflict | Version mismatch (optimistic locking) | `{"success": false, "error": "Version conflict", "currentVersion": 43}` |
| `500` | Internal Server Error | Unexpected server error | `{"success": false, "error": "Internal server error"}` |

### Version Conflict Response (409)

When a version conflict occurs, the response includes current state:

```json
{
  "success": false,
  "error": "Version conflict",
  "currentVersion": 43,
  "data": {
    "campaignId": "123",
    "state": { /* current state */ },
    "version": 43
  }
}
```

### Validation Error Response (400)

Schema validation failures include detailed error messages:

```json
{
  "success": false,
  "error": "Invalid state payload",
  "validation_errors": [
    "Field 'created_by' is required",
    "Field 'started' must be boolean",
    "Field 'progress' must be array"
  ]
}
```

---

## Examples

### Quick Start: Basic API Usage

#### 1. Check Campaign State
```bash
curl -X GET "https://example.com/api/campaign/123/state" \
  -H "Cookie: SESS..." \
  -H "Accept: application/json"
```

#### 2. Get Dungeon Information
```bash
curl -X GET "https://example.com/api/dungeon/dungeon-001/state?campaignId=123" \
  -H "Cookie: SESS..." \
  -H "Accept: application/json"
```

#### 3. Fetch Room State with Visibility
```bash
curl -X GET "https://example.com/api/dungeon/dungeon-001/room/room-3/state?campaignId=123" \
  -H "Cookie: SESS..." \
  -H "Accept: application/json"
```

### Complete Workflow: Spawning and Moving an NPC

1. **Spawn Goblin in Room 3:**
```bash
POST /api/campaign/123/entity/spawn
{
  "type": "npc",
  "instanceId": "goblin-scout-1",
  "characterId": 456,
  "locationType": "room",
  "locationRef": "room-3",
  "stateData": {
    "hexId": "hex-5",
    "hp": 8,
    "maxHp": 8,
    "detected": false,
    "hidden": true
  }
}
```

2. **Player Succeeds Perception Check, Mark Detected:**

To update entity detection state, update the room state to include the entity in a `detectedEntities` array:

```bash
POST /api/dungeon/dungeon-001/room/room-3/state
{
  "campaignId": 123,
  "expectedVersion": 1234567890,
  "state": {
    "dungeonId": "dungeon-001",
    "roomId": "room-3",
    "isCleared": false,
    "visibleHexIds": ["hex-1", "hex-5"],
    "detectedEntities": ["goblin-scout-1"]
  }
}
```

**Note**: When room state is retrieved, entities in `detectedEntities` will have their `detected` flag set to `true` in the response, making them visible even if they have `hidden: true`.

3. **Goblin Moves to Room 4:**
```bash
POST /api/campaign/123/entity/goblin-scout-1/move
{
  "locationType": "room",
  "locationRef": "room-4"
}
```

4. **Goblin is Defeated, Despawn:**
```bash
DELETE /api/campaign/123/entity/goblin-scout-1
```

---

## Implementation Status

### Fully Implemented Endpoints ✓

**Campaign State**
- ✅ `GET /api/campaign/{campaign_id}/state` - Get campaign state with versioning

**Dungeon State**
- ✅ `GET /api/dungeon/{dungeon_id}/state` - Get dungeon state
- ✅ `POST /api/dungeon/{dungeon_id}/state` - Update dungeon state

**Room State**
- ✅ `GET /api/dungeon/{dungeon_id}/room/{room_id}/state` - Get room state with visibility filtering

**Entity Lifecycle**
- ✅ `POST /api/campaign/{campaign_id}/entity/spawn` - Spawn new entity
- ✅ `POST /api/campaign/{campaign_id}/entity/{instance_id}/move` - Move entity
- ✅ `DELETE /api/campaign/{campaign_id}/entity/{instance_id}` - Despawn entity
- ✅ `GET /api/campaign/{campaign_id}/entities` - List entities with filters

### Pending Route Configuration ⚠️

These endpoints have controllers implemented but are **not configured in routing**:
- ⚠️ `POST /api/campaign/{campaign_id}/state` - Campaign state update (controller: `CampaignStateController::setState`)
- ⚠️ `POST /api/dungeon/{dungeon_id}/room/{room_id}/state` - Room state update (controller: `RoomStateController::setState`)

**Action Required**: Add route definitions to `dungeoncrawler_content.routing.yml` to enable these endpoints.

### Parameter Naming Convention

The codebase uses **snake_case** for URL parameters in routing:
- Route parameters: `campaign_id`, `dungeon_id`, `room_id`, `character_id`, `instance_id`
- Query/body parameters: `campaignId` (camelCase for JSON consistency)

**Example**:
```
Route: /api/campaign/{campaign_id}/state
Query: ?campaignId=123
Body: {"campaignId": 123, "state": {...}}
```

---

## Notes

- All timestamps are Unix timestamps (seconds since epoch)
- All JSON responses use camelCase for consistency with frontend
- Campaign state is versioned using optimistic locking
- Entity instances are scoped to campaigns and can be reused across sessions
- Contents data in rooms serves as templates; runtime entities override/extend it

---

## Best Practices

### Client Implementation Guidelines

#### 1. Always Check Response Structure
```javascript
async function apiRequest(url, options) {
  const response = await fetch(url, options);
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.error || 'Request failed');
  }
  
  return data.data;
}
```

#### 2. Handle Version Conflicts Gracefully
```javascript
async function updateCampaignState(campaignId, newState, expectedVersion) {
  try {
    return await apiRequest(`/api/campaign/${campaignId}/state`, {
      method: 'POST',
      body: JSON.stringify({ expectedVersion, state: newState }),
      headers: { 'Content-Type': 'application/json' }
    });
  } catch (error) {
    if (error.status === 409) {
      // Fetch current state and retry
      const current = await getCampaignState(campaignId);
      const merged = mergeStates(current.state, newState);
      return updateCampaignState(campaignId, merged, current.version);
    }
    throw error;
  }
}
```

#### 3. Validate Before Sending
```javascript
function validateCampaignState(state) {
  const required = ['created_by', 'started', 'progress'];
  for (const field of required) {
    if (!(field in state)) {
      throw new Error(`Missing required field: ${field}`);
    }
  }
  
  if (!Array.isArray(state.progress)) {
    throw new Error('progress must be an array');
  }
  
  return true;
}
```

### Optimistic Locking
- Always include `expectedVersion` when updating state to prevent race conditions
- Handle `409 Conflict` responses by re-fetching current state and retrying
- Use version numbers from GET responses in subsequent POST requests

### Error Handling
- Check `success` field in all responses before processing data
- Log `validation_errors` array for debugging invalid payloads
- Implement exponential backoff for retry logic on `409` errors
- Handle `403 Forbidden` by redirecting to authentication or showing access denied message

### Performance
- Cache campaign state locally and only re-fetch on version conflicts
- Batch entity operations when spawning multiple entities (use Promise.all for parallel operations)
- Use query filters on entity list endpoint to reduce response size
- Only fetch room state when player enters a new room
- Implement request debouncing for rapid state updates (e.g., during drag operations)

### Rate Limiting & Throttling
While not currently enforced, clients should implement:
- **Debounce rapid updates**: Wait 100-300ms after last change before sending state updates
- **Batch operations**: Group multiple entity spawns into a single operation when possible
- **Cache responses**: Store GET responses with version numbers to avoid redundant fetches
- **Exponential backoff**: On 409 conflicts, implement backoff: 100ms, 200ms, 400ms, etc.

### Data Persistence & Caching

#### Recommended Client-Side Storage

```javascript
// Store campaign state in localStorage with version tracking
class CampaignStateCache {
  constructor(campaignId) {
    this.campaignId = campaignId;
    this.key = `campaign_${campaignId}_state`;
  }
  
  save(state, version) {
    const cached = {
      state,
      version,
      timestamp: Date.now(),
      expiresAt: Date.now() + (5 * 60 * 1000)  // 5 minutes
    };
    localStorage.setItem(this.key, JSON.stringify(cached));
  }
  
  load() {
    const cached = localStorage.getItem(this.key);
    if (!cached) return null;
    
    const data = JSON.parse(cached);
    if (Date.now() > data.expiresAt) {
      this.clear();
      return null;
    }
    
    return data;
  }
  
  clear() {
    localStorage.removeItem(this.key);
  }
}
```

#### Server-Side Caching Behavior

- **Campaign State**: Cached in database, version incremented on each update
- **Room State**: Calculated on-demand, visibility filtering applied per request
- **Entity Lists**: Fresh query each time, filterable for performance
- **No HTTP Caching**: All API responses have `Cache-Control: no-cache` to ensure freshness

### Security
- Never expose internal IDs or system details in client-side errors
- Validate all user inputs before making API calls
- Use CSRF tokens for all POST/DELETE operations
- Sanitize entity state data to prevent code injection

### Common Integration Patterns

#### Pattern 1: Room Entry Flow
When a player enters a new room:
```javascript
// 1. Fetch room state (includes visibility filtering)
const roomState = await getRoomState(dungeonId, roomId, campaignId);

// 2. Render visible hexes and detected entities
renderHexMap(roomState.room.layout.hexes);
renderEntities(roomState.room.contents.entities.filter(e => e.state.detected));

// 3. Allow perception checks for hidden entities
const hiddenEntities = roomState.room.contents.entities.filter(e => e.hidden && !e.state.detected);
```

#### Pattern 2: Combat Initialization
Setting up a combat encounter:
```javascript
// 1. Spawn NPCs in the room
for (const npc of encounterNPCs) {
  await spawnEntity(campaignId, {
    type: 'npc',
    instanceId: `${npc.type}-${npc.index}`,
    characterId: npc.characterId,
    locationType: 'room',
    locationRef: roomId,
    stateData: { hexId: npc.startingHex, hp: npc.maxHp, detected: false }
  });
}

// 2. Update room visibility to include combat area
await updateRoomState(dungeonId, roomId, {
  campaignId,
  expectedVersion: roomState.version,
  state: {
    ...roomState.state,
    visibleHexIds: [...roomState.state.visibleHexIds, ...combatHexes]
  }
});
```

#### Pattern 3: Entity Movement Tracking
Moving entities between locations:
```javascript
// 1. Move entity to new location
await moveEntity(campaignId, entityInstanceId, {
  locationType: 'room',
  locationRef: newRoomId
});

// 2. Update old room state (remove from tracking)
await updateRoomState(dungeonId, oldRoomId, {
  campaignId,
  expectedVersion: oldRoomVersion,
  state: { ...oldRoomState, entityCount: oldRoomState.entityCount - 1 }
});

// 3. Update new room state (add to tracking)
await updateRoomState(dungeonId, newRoomId, {
  campaignId,
  expectedVersion: newRoomVersion,
  state: { ...newRoomState, entityCount: newRoomState.entityCount + 1 }
});
```

---

## Troubleshooting

### Debugging Tools

#### Enable Detailed Error Logging
```javascript
// Add to your API client
const DEBUG = true;

function logApiCall(method, url, body, response) {
  if (!DEBUG) return;
  
  console.group(`API ${method} ${url}`);
  console.log('Request:', body);
  console.log('Response:', response);
  console.log('Status:', response.success ? 'SUCCESS' : 'FAILED');
  console.groupEnd();
}
```

#### Validate Response Structure
```javascript
function validateApiResponse(response) {
  if (typeof response !== 'object') {
    throw new Error('Invalid response: not an object');
  }
  
  if (!('success' in response)) {
    throw new Error('Invalid response: missing success field');
  }
  
  if (!response.success && !response.error) {
    throw new Error('Invalid error response: missing error field');
  }
  
  if (response.success && !response.data) {
    throw new Error('Invalid success response: missing data field');
  }
  
  return true;
}
```

### Common Issues

### Issue: "Access denied to campaign" (403)
**Cause**: User is not the campaign owner  
**Solution**: Verify campaign ownership via `/api/campaign/{campaign_id}/state` or redirect to campaign list

### Issue: "Version conflict" (409)
**Cause**: Another client updated state between GET and POST  
**Solution**: Re-fetch current state, merge changes, and retry with new version

### Issue: "Missing route" error for POST endpoints
**Cause**: Route not configured in `dungeoncrawler_content.routing.yml`  
**Solution**: See [Implementation Status](#implementation-status) section for routing configuration

### Issue: Entity not visible in room state
**Possible Causes**:
1. Entity hex not in `visibleHexIds` array
2. Entity has `hidden: true` and not in `detectedEntities` array
3. Entity despawned or moved to different location

**Solution**: Check room state's `visibleHexIds` and `detectedEntities`, verify entity location via entities list endpoint

### Issue: Schema validation failure
**Cause**: State payload doesn't match schema requirements  
**Solution**: Check `validation_errors` array in response, verify required fields and data types

### Testing API Endpoints

#### Using cURL
```bash
# Test GET endpoint with session cookie
curl -X GET "https://example.com/api/campaign/123/state" \
  -H "Cookie: SESS123abc..." \
  -H "Accept: application/json" \
  -v

# Test POST endpoint with JSON body
curl -X POST "https://example.com/api/campaign/123/entity/spawn" \
  -H "Cookie: SESS123abc..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "type": "npc",
    "instanceId": "test-goblin-1",
    "characterId": 456,
    "locationType": "room",
    "locationRef": "room-1",
    "stateData": {"hexId": "hex-1", "hp": 8}
  }' \
  -v
```

#### Using Browser DevTools
```javascript
// Open browser console on authenticated page and run:
async function testApi() {
  const response = await fetch('/api/campaign/123/state', {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  console.log('Campaign State:', data);
  return data;
}

testApi();
```

#### Automated Testing Pattern
```javascript
// Integration test example
describe('Campaign API', () => {
  it('should get campaign state', async () => {
    const response = await fetch(`/api/campaign/${campaignId}/state`, {
      credentials: 'include'
    });
    
    expect(response.status).toBe(200);
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toHaveProperty('campaignId');
    expect(data.data).toHaveProperty('version');
  });
  
  it('should handle version conflicts', async () => {
    // Get current state
    const current = await getCampaignState(campaignId);
    
    // Try to update with wrong version
    const response = await fetch(`/api/campaign/${campaignId}/state`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        expectedVersion: current.version - 1,  // Wrong version
        state: current.state
      })
    });
    
    expect(response.status).toBe(409);
    
    const data = await response.json();
    expect(data.success).toBe(false);
    expect(data.currentVersion).toBe(current.version);
  });
});
```

---

## Related Documentation

- **Module README**: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/README.md`
- **Routing Configuration**: `dungeoncrawler_content.routing.yml`
- **Controller Source**: `src/Controller/` directory
- **Service Layer**: `src/Service/` directory
- **Hex Map Architecture**: `HEXMAP_ARCHITECTURE.md`
