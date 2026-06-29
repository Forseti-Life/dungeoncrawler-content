# Quest System API Documentation

**API Version**: 1.0  
**Base URL**: `/api/campaign/{campaign_id}`  
**Content-Type**: `application/json`  
**Authentication**: Drupal permission `access dungeoncrawler characters`  

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication & Authorization](#authentication--authorization)
3. [Request/Response Format](#requestresponse-format)
4. [Error Handling](#error-handling)
5. [Endpoints](#endpoints)
   - [Quest Generation](#quest-generation)
   - [Quest Tracking](#quest-tracking)
   - [Quest Rewards](#quest-rewards)
6. [Examples](#examples)
7. [Data Models](#data-models)

---

## Overview

The Quest System API provides endpoints for:
- **Generating** quests from templates with context-based customization
- **Tracking** quest progress through multi-phase objectives
- **Completing** quests and recording outcomes
- **Claiming** rewards (XP, gold, items, reputation)
- **Querying** quest availability and status

### Key Concepts

**Template**: Reusable quest definition (stored in `dungeoncrawler_content_quest_templates`)
- Fixed structure, variable content
- Specifies objective types and reward formulas
- Licensed for reuse across campaigns

**Quest Instance**: Campaign-specific quest generated from a template
- Concrete objectives with specific targets
- Scaled rewards based on party level
- Single-use per character/party

**Phase**: Sequential objective grouping within a quest
- Quest progresses through phases
- All objectives in phase must be complete before advancement
- Enables story progression and branching

**Objective**: Atomic task within a phase
- Types: kill, collect, explore, escort, navigate, interact
- Progress tracked independently
- Completion triggers event

---

## Authentication & Authorization

### Permission Requirements

All endpoints require the `access dungeoncrawler characters` permission:

```php
// Permission check (automatic via routing)
_permission: 'access dungeoncrawler characters'
```

### Campaign Access Control

All endpoints enforce campaign-level isolation:

```
_campaign_access: 'TRUE'
```

This prevents cross-campaign quest access even with valid credentials.

### Session Requirements

- User must have active session
- Campaign must exist and be accessible to user
- Character must exist within campaign (for character-specific endpoints)

---

## Request/Response Format

### Standard Request Headers

```http
POST /api/campaign/1/quests/generate HTTP/1.1
Content-Type: application/json
Accept: application/json
```

### Standard Response Format

**Success Response (2xx)**:
```json
{
  "success": true,
  "message": "Operation completed",
  "data": { /* operation-specific data */ }
}
```

**Error Response (4xx/5xx)**:
```json
{
  "success": false,
  "error": "Descriptive error message",
  "code": "error_code"
}
```

### Common Response Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request successful |
| 201 | Created - Resource created |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Authentication required |
| 403 | Forbidden - Permission denied |
| 404 | Not Found - Resource not found |
| 500 | Server Error - Internal error |

---

## Error Handling

### Error Response Format

```json
{
  "success": false,
  "error": "Missing required field: template_id",
  "http_code": 400
}
```

### Common Errors

| Error | Status | Cause |
|-------|--------|-------|
| Invalid request body | 400 | Malformed JSON |
| Missing required field | 400 | Field not provided |
| Invalid template_id | 400 | Template doesn't exist |
| Quest not found | 404 | Quest ID invalid |
| Quest already completed | 400 | Cannot restart completed quest |
| Rewards already claimed | 400 | Character already claimed rewards |
| Internal server error | 500 | Server-side exception |

### Error Recovery

If request fails:
1. Check response status code
2. Read error message
3. Fix request or retry with different parameters
4. Check server logs if 5xx error

---

## Endpoints

### Quest Generation

#### Generate Quest from Template

Generate a single quest from a template with context-based customization.

**Endpoint**:
```
POST /api/campaign/{campaign_id}/quests/generate
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `template_id` (body, string): Template name (e.g., "clear_goblin_den")
- `context` (body, object, optional): Generation context

**Context Options**:
```json
{
  "party_level": 3,                    // Integer, required
  "difficulty": "moderate",            // "trivial" | "low" | "moderate" | "severe" | "extreme"
  "location_id": "tavern_001",         // String, optional
  "location_tags": ["urban", "tavern"], // Array, optional
  "npc": "Barkeep Thorgrim",           // String, optional (if template requires)
  "target": "Goblin Chief",             // String, optional (if template requires)
  "environment": "cave"                 // String, optional
}
```

**Request Example**:
```bash
curl -X POST "/api/campaign/1/quests/generate" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": "clear_goblin_den",
    "context": {
      "party_level": 3,
      "difficulty": "moderate"
    }
  }'
```

**Response Example**:
```json
{
  "success": true,
  "quest": {
    "quest_id": "goblin_den_19283",
    "name": "Clear the Goblin Den",
    "description": "The goblin den has been operating for weeks. Clear it out.",
    "quest_type": "bounty",
    "objectives": [
      {
        "objective_id": "explore_cave",
        "type": "explore",
        "description": "Locate the goblin den entrance",
        "progress": 0,
        "target": 1,
        "completed": false
      },
      {
        "objective_id": "kill_enemies",
        "type": "kill",
        "description": "Defeat the goblins",
        "progress": 0,
        "target": 6,
        "completed": false
      },
      {
        "objective_id": "kill_boss",
        "type": "kill",
        "description": "Defeat the goblin chief",
        "progress": 0,
        "target": 1,
        "completed": false
      }
    ],
    "rewards": {
      "xp": 200,
      "gold": 20,
      "items": [
        {
          "item_id": "gold_pouch",
          "name": "Small Gold Pouch",
          "quantity": 1
        }
      ],
      "reputation": {
        "militia": 15
      }
    },
    "status": "available"
  }
}
```

#### Generate Quests for Location

Generate multiple quests suitable for a specific location.

**Endpoint**:
```
POST /api/campaign/{campaign_id}/quests/generate-for-location
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `location_id` (body, string): Location identifier
- `location_tags` (body, array): Location characteristics
- `context` (body, object): Generation context
- `count` (body, integer, optional): Number of quests to generate (default: 3, max: 5)

**Request Example**:
```bash
curl -X POST "/api/campaign/1/quests/generate-for-location" \
  -H "Content-Type: application/json" \
  -d '{
    "location_id": "tavern_001",
    "location_tags": ["urban", "tavern", "social"],
    "context": {"party_level": 2},
    "count": 3
  }'
```

**Response Example**:
```json
{
  "success": true,
  "quests": [
    {
      "quest_id": "rescue_merchant_001",
      "name": "Rescue the Merchant",
      "description": "A merchant has been kidnapped nearby",
      "type": "rescue"
    },
    {
      "quest_id": "deliver_package_001",
      "name": "Deliver the Package",
      "description": "Transport goods to the next town",
      "type": "delivery"
    },
    {
      "quest_id": "gather_supplies_001",
      "name": "Gather Supplies",
      "description": "Collect rare herbs from the forest",
      "type": "side_quest"
    }
  ],
  "count": 3
}
```

---

### Quest Tracking

#### List Available Quests

Retrieve all available quests for a campaign (quest board).

**Endpoint**:
```
GET /api/campaign/{campaign_id}/quests/available
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID

**Request Example**:
```bash
curl -X GET "/api/campaign/1/quests/available"
```

**Response Example**:
```json
{
  "success": true,
  "quests": [
    {
      "quest_id": "clear_goblin_den_001",
      "quest_name": "Clear the Goblin Den",
      "quest_description": "Clear the dangerous goblin den",
      "quest_type": "bounty",
      "quest_level": 2,
      "generated_objectives": [
        {"objective_id": "explore_cave", "type": "explore", "target": 1},
        {"objective_id": "kill_enemies", "type": "kill", "target": 6}
      ],
      "generated_rewards": {
        "xp": 200,
        "gold": 20
      }
    }
  ],
  "count": 1
}
```

#### Start a Quest

Begin tracking a specific quest for a character/party.

**Endpoint**:
```
POST /api/campaign/{campaign_id}/quests/{quest_id}/start
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `quest_id` (path, string): Quest identifier
- `character_id` (body, string): Character ID (if character-specific)
- `party_id` (body, string): Party ID (if party-wide)
- `entity_type` (body, string, optional): "character" or "party" (default: "party")

**Request Example**:
```bash
curl -X POST "/api/campaign/1/quests/goblin_den_001/start" \
  -H "Content-Type: application/json" \
  -d '{
    "party_id": "party_001",
    "entity_type": "party"
  }'
```

**Response Example**:
```json
{
  "success": true,
  "message": "Quest started successfully",
  "quest_id": "goblin_den_001"
}
```

#### Update Quest Progress

Update progress on an objective within a quest.

**Endpoint**:
```
PUT /api/campaign/{campaign_id}/quests/{quest_id}/progress
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `quest_id` (path, string): Quest identifier
- `objective_id` (body, string): Objective identifier
- `action` (body, string): "increment" | "set" | "complete"
- `entity_id` (body, string): Character/party ID
- `amount` (body, integer, optional): Amount to increment (default: 1)

**Action Types**:
- `increment`: Add amount to current progress
- `set`: Set progress to exact amount
- `complete`: Mark objective as complete

**Request Example - Increment Kills**:
```bash
curl -X PUT "/api/campaign/1/quests/goblin_den_001/progress" \
  -H "Content-Type: application/json" \
  -d '{
    "objective_id": "kill_enemies",
    "action": "increment",
    "entity_id": "party_001",
    "amount": 3
  }'
```

**Response Example**:
```json
{
  "success": true,
  "objective_state": {
    "objective_id": "kill_enemies",
    "progress": 3,
    "target": 6,
    "completed": false,
    "phase": 1
  }
}
```

#### Complete a Quest

Mark a quest as completed and record the outcome.

**Endpoint**:
```
POST /api/campaign/{campaign_id}/quests/{quest_id}/complete
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `quest_id` (path, string): Quest identifier
- `entity_id` (body, string): Character/party ID
- `outcome` (body, string, optional): "success" | "failure" | "abandoned" (default: "success")
- `notes` (body, string, optional): Completion notes

**Outcome Types**:
- `success`: Quest completed with all objectives met
- `failure`: Quest failed (objectives not met)
- `abandoned`: Party abandoned the quest

**Request Example**:
```bash
curl -X POST "/api/campaign/1/quests/goblin_den_001/complete" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_id": "party_001",
    "outcome": "success",
    "notes": "Successfully cleared all goblins and defeated the chief"
  }'
```

**Response Example**:
```json
{
  "success": true,
  "message": "Quest completed",
  "quest_id": "goblin_den_001",
  "outcome": "success"
}
```

#### Get Quest Journal

Retrieve character's active and completed quests.

**Endpoint**:
```
GET /api/campaign/{campaign_id}/character/{character_id}/quest-journal
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `character_id` (path, string): Character identifier

**Query Parameters** (optional):
- `status` (string): "active" | "completed" | "abandoned" (filter by status)
- `type` (string): Filter by quest type

**Request Example**:
```bash
curl -X GET "/api/campaign/1/character/char_001/quest-journal"
```

**Response Example**:
```json
{
  "success": true,
  "quests": [
    {
      "quest_id": "goblin_den_001",
      "campaign_id": 1,
      "entity_id": "party_001",
      "objective_states": [
        {"objective_id": "explore_cave", "progress": 1, "target": 1, "completed": true},
        {"objective_id": "kill_enemies", "progress": 6, "target": 6, "completed": true},
        {"objective_id": "kill_boss", "progress": 1, "target": 1, "completed": true}
      ],
      "current_phase": 2,
      "status": "completed",
      "started_at": 1708348800,
      "completed_at": 1708375200
    }
  ],
  "count": 1
}
```

---

### Quest Rewards

#### Get Reward Summary

Preview rewards before claiming them.

**Endpoint**:
```
GET /api/campaign/{campaign_id}/quests/{quest_id}/rewards
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `quest_id` (path, string): Quest identifier

**Request Example**:
```bash
curl -X GET "/api/campaign/1/quests/goblin_den_001/rewards"
```

**Response Example**:
```json
{
  "success": true,
  "quest_id": "goblin_den_001",
  "quest_name": "Clear the Goblin Den",
  "rewards": {
    "xp": 200,
    "gold": 20,
    "items": [
      {
        "item_id": "gold_pouch",
        "name": "Small Gold Pouch",
        "rarity": "common",
        "quantity": 1
      }
    ],
    "reputation": {
      "militia": 15
    }
  }
}
```

#### Claim Quest Rewards

Claim rewards for a completed quest. Each character can claim once per quest.

**Endpoint**:
```
POST /api/campaign/{campaign_id}/quests/{quest_id}/rewards/claim
```

**Parameters**:
- `campaign_id` (path, integer): Campaign ID
- `quest_id` (path, string): Quest identifier
- `character_id` (body, string): Character ID claiming rewards

**Validation**:
- Quest must be completed
- Rewards not already claimed by this character
- Character must be in campaign

**Request Example**:
```bash
curl -X POST "/api/campaign/1/quests/goblin_den_001/rewards/claim" \
  -H "Content-Type: application/json" \
  -d '{
    "character_id": "char_001"
  }'
```

**Response Example**:
```json
{
  "success": true,
  "message": "Rewards claimed successfully",
  "rewards": {
    "xp_granted": 200,
    "gold_granted": 20,
    "items_granted": [
      {
        "item_id": "gold_pouch",
        "name": "Small Gold Pouch",
        "quantity": 1
      }
    ],
    "reputation_granted": {
      "militia": 15
    }
  }
}
```

**Error Case - Already Claimed**:
```json
{
  "success": false,
  "error": "Rewards already claimed for this character",
  "http_code": 400
}
```

---

## Examples

### Complete Quest Workflow

This example shows a complete flow from quest generation to reward claiming:

**1. Generate Quest**
```bash
curl -X POST "http://localhost:8888/api/campaign/1/quests/generate" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": "clear_goblin_den",
    "context": {"party_level": 2, "difficulty": "moderate"}
  }'
# Response: quest_id = "goblin_den_abc123"
```

**2. List Available Quests**
```bash
curl -X GET "http://localhost:8888/api/campaign/1/quests/available"
# Shows goblin_den_abc123 in the list
```

**3. Start Quest**
```bash
curl -X POST "http://localhost:8888/api/campaign/1/quests/goblin_den_abc123/start" \
  -H "Content-Type: application/json" \
  -d '{"party_id": "party_001"}'
```

**4. Progress Updates (Game Events)**
```bash
# Enemy killed
curl -X PUT "http://localhost:8888/api/campaign/1/quests/goblin_den_abc123/progress" \
  -H "Content-Type: application/json" \
  -d '{
    "objective_id": "kill_enemies",
    "action": "increment",
    "entity_id": "party_001",
    "amount": 1
  }'

# Repeated 6 times for all enemies...

# Boss defeated
curl -X PUT "http://localhost:8888/api/campaign/1/quests/goblin_den_abc123/progress" \
  -H "Content-Type: application/json" \
  -d '{
    "objective_id": "kill_boss",
    "action": "complete",
    "entity_id": "party_001"
  }'
```

**5. Complete Quest**
```bash
curl -X POST "http://localhost:8888/api/campaign/1/quests/goblin_den_abc123/complete" \
  -H "Content-Type: application/json" \
  -d '{"entity_id": "party_001", "outcome": "success"}'
```

**6. Preview Rewards**
```bash
curl -X GET "http://localhost:8888/api/campaign/1/quests/goblin_den_abc123/rewards"
# Shows total XP, gold, items, reputation
```

**7. Claim Rewards**
```bash
curl -X POST "http://localhost:8888/api/campaign/1/quests/goblin_den_abc123/rewards/claim" \
  -H "Content-Type: application/json" \
  -d '{"character_id": "char_001"}'
# XP, gold, items, reputation added to character
```

### Error Handling Example

```bash
# Attempt to claim rewards twice
curl -X POST "http://localhost:8888/api/campaign/1/quests/goblin_den_abc123/rewards/claim" \
  -H "Content-Type: application/json" \
  -d '{"character_id": "char_001"}'

# First call succeeds
# {"success": true, ...}

# Second call fails
# {"success": false, "error": "Rewards already claimed for this character", "http_code": 400}
```

---

## Data Models

### Quest Object

```json
{
  "quest_id": "clear_goblin_den_001",
  "campaign_id": 1,
  "source_template_id": "clear_goblin_den",
  "quest_name": "Clear the Goblin Den",
  "quest_description": "A dangerous goblin den has been terrorizing the region.",
  "quest_type": "bounty",
  "quest_level": 2,
  "generated_objectives": [
    {
      "objective_id": "explore_cave",
      "type": "explore",
      "description": "Locate the goblin den",
      "target": 1,
      "progress": 0,
      "completed": false
    }
  ],
  "generated_rewards": {
    "xp": 200,
    "gold": 20,
    "items": [{"item_id": "gold_pouch", "quantity": 1}],
    "reputation": {"militia": 15}
  },
  "status": "available",
  "created_at": 1708348800,
  "expires_at": null
}
```

### Objective Object

```json
{
  "objective_id": "kill_enemies",
  "type": "kill",
  "description": "Defeat the goblin scouts",
  "phase": 1,
  "target": 6,
  "progress": 3,
  "completed": false,
  "reward_xp": 50
}
```

### Reward Object

```json
{
  "xp": 200,
  "gold": 20,
  "items": [
    {
      "item_id": "gold_pouch",
      "name": "Small Gold Pouch",
      "rarity": "common",
      "quantity": 1,
      "loot_table": "common_rewards"
    }
  ],
  "reputation": {
    "militia": 15,
    "scholars_guild": 5
  },
  "story_unlocks": ["goblin_cave_cleared"]
}
```

### Quest Progress Object

```json
{
  "quest_id": "goblin_den_001",
  "entity_id": "party_001",
  "entity_type": "party",
  "current_phase": 1,
  "status": "active",
  "objective_states": [
    {
      "objective_id": "explore_cave",
      "progress": 1,
      "target": 1,
      "completed": true
    },
    {
      "objective_id": "kill_enemies",
      "progress": 3,
      "target": 6,
      "completed": false
    }
  ],
  "started_at": 1708348800,
  "completed_at": null
}
```

---

## Status Codes Reference

| Code | Name | Meaning |
|------|------|---------|
| 200 | OK | Request successful |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid input or missing required field |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 409 | Conflict | Resource state conflict (e.g., already claimed) |
| 500 | Server Error | Internal server error |

---

## Rate Limiting (Future)

Currently no rate limiting implemented. Production deployments should consider:
- Request rate limits per user
- Concurrent quest limit per party
- Reward claim cooldown

---

## Support & Debugging

### Enable Debug Logging

```bash
# View recent quest-related log entries
./vendor/bin/drush watchdog:show --filter="quest" --count=50
```

### Common Issues

**Q: Getting 403 Forbidden**
A: Check user permissions. Ensure user has `access dungeoncrawler characters` permission.

**Q: Quest templates not found**
A: Ensure templates are loaded via `drush dcq-load-templates`

**Q: Rewards claiming fails**
A: Check quest is marked as "completed" in database.

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-02-19 | Initial API release |

---

**Last Updated**: February 19, 2026  
**API Stability**: Stable (Production Ready)
