# Dungeon & Room Generator API Quick Reference

**Phase 1 Implementation**: 2026-02-18

## Quick Start

All endpoints require:
- Valid `campaign_id` in URL
- User must have `access dungeoncrawler characters` permission
- User must have campaign access (owner or admin)
- Response format is always JSON

---

## Dungeon Generation Endpoints

### Generate Complete Dungeon

**POST** `/api/campaign/{campaign_id}/dungeons/generate`

Generate a new multi-level dungeon at world coordinates.

**Request**:
```json
{
  "location_x": 100,
  "location_y": 200,
  "party_level": 5,
  "party_size": 4,
  "party_composition": {
    "fighter": 1,
    "wizard": 1,
    "cleric": 1,
    "rogue": 1
  },
  "theme": null
}
```

**Response** (201 Created):
```json
{
  "dungeon_id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "The Goblin Warren",
  "theme": "goblin_warrens",
  "depth": 3,
  "location_x": 100,
  "location_y": 200,
  "created_at": "2026-02-18T10:00:00Z",
  "levels": [
    { "level_id": "uuid", "depth": 1, ... },
    { "level_id": "uuid", "depth": 2, ... },
    { "level_id": "uuid", "depth": 3, ... }
  ]
}
```

**Errors**:
- `400` — Missing required fields (location_x, location_y, party_level, party_size)
- `403` — No permission to generate dungeons
- `404` — Campaign not found
- `409` — Dungeon already exists at this location
- `422` — Validation failed (invalid party_level range, etc.)

**Parameters**:
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `location_x` | int | Yes | World X coordinate |
| `location_y` | int | Yes | World Y coordinate |
| `party_level` | int | Yes | Average party level (1-20) |
| `party_size` | int | Yes | Number of party members (1-20) |
| `party_composition` | object | Yes | Class breakdown: {class: count, ...} |
| `theme` | string | No | Override theme (null = auto-select) |

---

### Get Dungeon (All Levels)

**GET** `/api/campaign/{campaign_id}/dungeons/{dungeon_id}`

Retrieve complete dungeon with all levels.

**Response** (200 OK):
```json
{
  "dungeon_id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "The Goblin Warren",
  "theme": "goblin_warrens",
  "depth": 3,
  "location_x": 100,
  "location_y": 200,
  "created_at": "2026-02-18T10:00:00Z",
  "levels": [...]
}
```

**Errors**:
- `404` — Dungeon not found or access denied

---

### Get Single Dungeon Level

**GET** `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}`

Retrieve a specific level within dungeon.

**Parameters**:
| Field | Type | Description |
|-------|------|-------------|
| `depth` | int | Level number (1-based) |

**Response** (200 OK):
```json
{
  "level_id": "uuid",
  "depth": 1,
  "theme": "goblin_warrens",
  "name": "The Warren Entrance",
  "flavor_text": "You descend into darkness...",
  "hex_map": { ... },
  "rooms": [ ... ],
  "entities": [ ... ],
  "generation_rules": { ... }
}
```

**Errors**:
- `404` — Level not found

---

### Extend Dungeon (Add New Level)

**POST** `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels`

Generate and add a new level to existing dungeon (when party goes deeper).

**Request**:
```json
{
  "party_level": 6,
  "party_composition": {
    "fighter": 1,
    "wizard": 1,
    "cleric": 1,
    "rogue": 1
  }
}
```

**Response** (201 Created):
```json
{
  "level_id": "uuid",
  "depth": 4,
  "theme": "goblin_warrens",
  "name": "The Deep Warren",
  ...
}
```

**Errors**:
- `404` — Dungeon not found
- `409` — Level at this depth already exists
- `422` — Validation failed

---

## Room Generation Endpoints

### Generate Room in Level

**POST** `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}/rooms`

Generate a new room within a dungeon level.

**Request**:
```json
{
  "level_id": "uuid",
  "party_level": 5,
  "room_size": "medium",
  "room_type": "chamber",
  "terrain_type": "stone"
}
```

**Response** (201 Created):
```json
{
  "room_id": "uuid",
  "name": "The Fungal Pantry",
  "description": "As you enter, the air grows thick with spores...",
  "gm_notes": "Creatures dormant until disturbed",
  "hexes": [
    {
      "q": 0, "r": 0,
      "terrain": "stone",
      "elevation_ft": 0,
      "objects": []
    },
    ...
  ],
  "lighting": { ... },
  "entities": [ ... ],
  "state": {
    "explored": false,
    "cleared": false
  }
}
```

**Errors**:
- `400` — Missing required fields
- `404` — Campaign/dungeon/level not found
- `422` — Validation failed

**Parameters**:
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `level_id` | string | Yes | Level UUID |
| `party_level` | int | Yes | Party level (1-20) |
| `room_size` | string | Yes | 'small', 'medium', or 'large' |
| `room_type` | string | Yes | 'chamber', 'corridor', 'library', etc. |
| `terrain_type` | string | Yes | 'stone', 'sand', 'crystal', 'lava', etc. |

**Room Sizes**:
- `small` — 1-2 hexes, 3-6 hex perimeter
- `medium` — 4-8 hexes, 8-12 hex perimeter
- `large` — 10-20 hexes, 16-25 hex perimeter

**Room Types**:
- `chamber` — Large open area for encounters
- `corridor` — Narrow passage (2-3 hexes wide)
- `library` — Knowledge/research location
- `treasury` — Treasure room
- `barracks` — Military quarters
- `shrine` — Religious/magical location
- `laboratory` — Alchemical/research
- `throne_room` — Important location
- `storage` — Supplies/equipment
- `prison` — Jail cells

---

### Get Room Details

**GET** `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}`

Retrieve existing room details.

**Response** (200 OK):
```json
{
  "room_id": "uuid",
  "name": "The Fungal Pantry",
  ...complete room.schema.json...
}
```

**Errors**:
- `404` — Room not found or access denied

---

### Regenerate Room (Admin Only)

**POST** `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}/regenerate`

Force regenerate a room (overwrites existing content).

**Requires**: `administer dungeoncrawler content` permission

**Request**:
```json
{
  "confirm": true
}
```

**Response** (200 OK):
```json
{
  "status": "regenerated",
  "room_id": "uuid",
  ...new room.schema.json...
}
```

**Errors**:
- `403` — User is not admin
- `404` — Room not found

---

## HTTP Status Codes

| Code | Meaning | When |
|------|---------|------|
| `200` | OK | Successful GET request |
| `201` | Created | Successful POST generating new content |
| `400` | Bad Request | Invalid input parameters |
| `403` | Forbidden | Permission denied or access denied |
| `404` | Not Found | Campaign/dungeon/room doesn't exist |
| `409` | Conflict | Dungeon already exists at location |
| `422` | Unprocessable | Schema validation failed |
| `500` | Server Error | Unexpected error (check logs) |

---

## Common Request/Response Patterns

### Authentication

Include in all requests:
- Valid user session (via Drupal login)
- User must have `access dungeoncrawler characters` permission
- User must have access to campaign (owner or admin)

### Error Response Format

```json
{
  "error": "Campaign not found",
  "code": 404
}
```

### Dungeon Theme Auto-Selection

When `theme` is null, theme is selected based on world coordinates:

| Region | Possible Themes |
|--------|-----------------|
| Northern Mountains | dragon_lair, crystal_caves, lava_forge |
| Forest | beast_den, spider_nests, ancient_ruins |
| Underdark | undead_crypts, demonic_sanctum, elemental_nexus |
| Coast | flooded_depths, abandoned_mine |
| Plains | goblin_warrens, fungal_caverns, eldritch_library |

### Entity Types in Rooms

Rooms can contain:
- **Creatures** (enemies, NPCs)
- **Items** (weapons, armor, loot)
- **Traps** (mechanical/magical)
- **Hazards** (environmental dangers)
- **Obstacles** (walls, pillars, chasms)

---

## Example Workflows

### Workflow 1: Generate Complete Dungeon

```bash
# 1. Generate dungeon
curl -X POST /api/campaign/1/dungeons/generate \
  -H "Content-Type: application/json" \
  -d '{
    "location_x": 100,
    "location_y": 200,
    "party_level": 5,
    "party_size": 4,
    "party_composition": {"fighter":1, "wizard":1, "cleric":1, "rogue":1}
  }'

# Response: dungeon_id = "7a8c9d0e"

# 2. Get all levels
curl -X GET /api/campaign/1/dungeons/7a8c9d0e

# 3. Get specific level
curl -X GET /api/campaign/1/dungeons/7a8c9d0e/levels/1
```

### Workflow 2: Explore Dungeon Level

```bash
# 1. Enter level (already generated)
curl -X GET /api/campaign/1/dungeons/7a8c9d0e/levels/1

# Get first room in response

# 2. Get room details
curl -X GET /api/campaign/1/dungeons/7a8c9d0e/rooms/{room_id}

# 3. When party goes deeper
curl -X POST /api/campaign/1/dungeons/7a8c9d0e/levels \
  -d '{"party_level": 6, "party_composition": {...}}'
```

---

## Response Data Structures

### Room (room.schema.json)

```json
{
  "room_id": "uuid",
  "name": "string",
  "description": "string",
  "hexes": [
    {
      "q": int, "r": int,
      "terrain": "string",
      "elevation_ft": number,
      "objects": []
    }
  ],
  "entities": [...entity_instance.schema.json...],
  "lighting": {
    "illumination": "bright|dim|darkness",
    "light_sources": [...]
  },
  "state": {
    "explored": boolean,
    "cleared": boolean
  }
}
```

### Level (dungeon_level.schema.json)

```json
{
  "level_id": "uuid",
  "depth": int,
  "theme": "string",
  "name": "string",
  "hex_map": {...},
  "rooms": [...room.schema.json...],
  "entities": [...entity_instance.schema.json...],
  "generation_rules": {...}
}
```

### Entity Instance (entity_instance.schema.json)

```json
{
  "entity_id": "uuid",
  "entity_type": "creature|item|obstacle|trap|hazard",
  "entity_ref": "reference_id",
  "placement": {
    "hex": {"q": int, "r": int},
    "direction": "north|northeast|southeast|south|southwest|northwest"
  },
  "state": {
    "active": boolean,
    "hidden": boolean,
    "hit_points": int,
    "conditions": [...]
  }
}
```

---

## Debugging

### Check Service Status

```bash
# Verify services are registered
cd sites/dungeoncrawler/web
php -r "require 'autoload.php'; \$services = get_defined_vars(); echo 'Services loaded';"
```

### View Request/Response

Enable Drupal logging:
```
admin/config/development/logging
```

Check `/var/log/apache2/forseti_error.log` for:
- Generation errors
- Validation failures
- Database issues

### Test Endpoint

Use curl or Postman:
```bash
curl -v -X POST http://forseti.life/api/campaign/1/dungeons/generate \
  -H "Content-Type: application/json" \
  -d '{...}'
```

---

## Permissions Reference

| Permission | Default Roles | Purpose |
|-----------|---|---------|
| `access dungeoncrawler characters` | Authenticated | Required for all endpoints |
| `generate dungeons` | Authenticated | generate_dungeon endpoint |
| `generate rooms` | Authenticated | generate_room endpoint |
| `administer dungeoncrawler content` | Admin | regenerate_room, admin functions |

---

## Performance Considerations

- Dungeon generation is **synchronous** (returns when complete)
- Typical run times:
  - Single room: 100-500ms
  - Single level (5 rooms): 500-2000ms
  - Complete dungeon (3 levels): 2-5 seconds
- AI description generation adds 1-3 seconds per room
- Database inserts are batched for performance

---

## Next Phase API Additions

Phase 2+ will add:
- `/api/campaign/{id}/dungeons/{id}/regenerate` — Force dungeon regen
- `/api/campaign/{id}/world-dungeons` — List world dungeons at coordinates
- `/api/campaign/{id}/dungeons/{id}/preview` — Preview without saving
- Streaming responses for large dungeons

---

## Support

For questions/issues:
1. Check [ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md](ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md)
2. Review API_DOCUMENTATION.md for full details
3. Check Drupal logs: `admin/reports/dblog`
4. Test manually with curl or Postman
