# Combat API Endpoints

**Part of**: [Issue #4: Combat & Encounter System Design](./issue-4-combat-encounter-system-design.md)  
**Status**: Design Document  
**Last Updated**: 2026-02-12

## Overview

This document specifies the REST API endpoints for the combat encounter system. The API supports real-time combat management, action execution, and state synchronization using RESTful principles with WebSocket support for live updates.

## API Architecture

```
┌──────────────────────────────────────────────────────┐
│              API Gateway Layer                        │
└──────────────────────────────────────────────────────┘
         │                              │
         │ REST API                     │ WebSocket
         ▼                              ▼
┌────────────────────┐      ┌────────────────────────┐
│  HTTP Endpoints    │      │  WebSocket Server      │
│  (CRUD + Actions)  │      │  (Real-time Updates)   │
└────────┬───────────┘      └──────────┬─────────────┘
         │                              │
         └──────────────┬───────────────┘
                        │
         ┌──────────────▼────────────────┐
         │      Combat Controller         │
         │   (Request Handling/Auth)      │
         └──────────────┬────────────────┘
                        │
         ┌──────────────▼────────────────┐
         │    Combat Engine Service       │
         └───────────────────────────────┘
```

## Base URL

```
Production:  https://api.forseti.life/v1
Development: http://localhost:3000/api/v1
```

## Authentication

All endpoints require authentication via Bearer token:

```http
Authorization: Bearer {access_token}
```

### Permissions

- **Player**: Can view combats they participate in, take actions with their characters
- **GM**: Full control over combats in their campaigns
- **Admin**: Full system access

---

## Endpoint Categories

1. [Encounter Management](#1-encounter-management)
2. [Turn Management](#2-turn-management)
3. [Action Execution](#3-action-execution)
4. [Condition Management](#4-condition-management)
5. [HP Management](#5-hp-management)
6. [Initiative Management](#6-initiative-management)
7. [Participant Management](#7-participant-management)
8. [Combat State](#8-combat-state)
9. [Combat Log](#9-combat-log)
10. [WebSocket Events](#10-websocket-events)

---

## 1. Encounter Management

### List Encounters

Get all encounters for a campaign.

```http
GET /campaigns/{campaign_id}/encounters
```

**Query Parameters**:
- `status` (optional): Filter by status (`active`, `paused`, `concluded`, `archived`)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Results per page (default: 20, max: 100)

**Response**: `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "campaign_id": 5,
      "encounter_name": "Goblin Ambush",
      "status": "active",
      "current_round": 3,
      "participant_count": 6,
      "started_at": "2026-02-12T10:30:00Z",
      "duration_seconds": 450
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 15
  }
}
```

### Get Encounter

Get detailed encounter information.

```http
GET /encounters/{encounter_id}
```

**Response**: `200 OK`
```json
{
  "id": 1,
  "campaign_id": 5,
  "encounter_name": "Goblin Ambush",
  "status": "active",
  "current_round": 3,
  "current_turn_participant_id": 12,
  "difficulty": "moderate",
  "started_at": "2026-02-12T10:30:00Z",
  "settings": {
    "use_grid": true,
    "grid_size": 5
  },
  "statistics": {
    "total_actions": 45,
    "total_damage": 87,
    "total_healing": 23
  }
}
```

### Create Encounter

Create a new combat encounter.

```http
POST /campaigns/{campaign_id}/encounters
```

**Request Body**:
```json
{
  "encounter_name": "Goblin Ambush",
  "difficulty": "moderate",
  "participants": [
    {
      "type": "character",
      "character_id": 10
    },
    {
      "type": "monster",
      "monster_id": 5,
      "count": 3
    }
  ],
  "settings": {
    "use_grid": true,
    "grid_size": 5
  }
}
```

**Response**: `201 Created`
```json
{
  "id": 25,
  "status": "setup",
  "message": "Encounter created successfully"
}
```

### Start Encounter

Begin combat (roll initiative and transition to active).

```http
POST /encounters/{encounter_id}/start
```

**Request Body**: (optional)
```json
{
  "custom_initiatives": [
    {
      "participant_id": 10,
      "initiative_total": 18
    }
  ]
}
```

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "status": "active",
  "current_round": 1,
  "initiative_order": [
    {
      "participant_id": 12,
      "name": "Valeros",
      "initiative": 18,
      "is_current_turn": true
    },
    {
      "participant_id": 15,
      "name": "Goblin Warrior 1",
      "initiative": 15,
      "is_current_turn": false
    }
  ]
}
```

### Pause Encounter

Pause an active combat.

```http
POST /encounters/{encounter_id}/pause
```

**Request Body**:
```json
{
  "reason": "Taking a break"
}
```

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "status": "paused",
  "message": "Encounter paused"
}
```

### Resume Encounter

Resume a paused combat.

```http
POST /encounters/{encounter_id}/resume
```

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "status": "active",
  "current_round": 3,
  "current_turn": {
    "participant_id": 14,
    "name": "Seoni"
  }
}
```

### End Encounter

Conclude combat and award XP.

```http
POST /encounters/{encounter_id}/end
```

**Request Body**:
```json
{
  "outcome": "victory",
  "victory_condition": "All enemies defeated",
  "xp_modifier": 1.0
}
```

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "status": "concluded",
  "outcome": "victory",
  "total_xp_awarded": 320,
  "xp_per_character": {
    "10": 80,
    "11": 80,
    "12": 80,
    "13": 80
  },
  "summary": {
    "rounds": 8,
    "total_damage": 245,
    "total_healing": 67,
    "duration_seconds": 1200
  }
}
```

### Delete Encounter

Delete an encounter (setup only).

```http
DELETE /encounters/{encounter_id}
```

**Response**: `204 No Content`

---

## 2. Turn Management

### Get Current Turn

Get information about whose turn it is.

```http
GET /encounters/{encounter_id}/turn
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "name": "Valeros",
  "actions_remaining": 2,
  "reaction_available": true,
  "current_map_penalty": -5,
  "active_conditions": [
    {
      "condition_type": "frightened",
      "value": 1
    }
  ]
}
```

### Start Turn

Begin a participant's turn (GM only).

```http
POST /encounters/{encounter_id}/participants/{participant_id}/turn/start
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "turn_state": "awaiting_action",
  "actions_remaining": 3,
  "reaction_available": true,
  "start_of_turn_effects": [
    "Frightened reduced to 0 (removed)"
  ]
}
```

### End Turn

End a participant's turn.

```http
POST /encounters/{encounter_id}/participants/{participant_id}/turn/end
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "turn_ended": true,
  "end_of_turn_effects": [
    "Persistent fire damage: 5",
    "HP: 45 → 40"
  ],
  "next_turn": {
    "participant_id": 15,
    "name": "Goblin Warrior 1"
  }
}
```

### Delay Turn

Delay current participant's turn.

```http
POST /encounters/{encounter_id}/participants/{participant_id}/delay
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "is_delaying": true,
  "original_initiative": 18,
  "message": "Participant delayed. Can resume at any lower initiative."
}
```

### Resume from Delay

Resume delayed turn at new initiative.

```http
POST /encounters/{encounter_id}/participants/{participant_id}/resume-delay
```

**Request Body**:
```json
{
  "new_initiative": 14
}
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "new_initiative": 14,
  "is_delaying": false,
  "turn_state": "awaiting_action"
}
```

---

## 3. Action Execution

### Execute Action

Execute any combat action.

```http
POST /encounters/{encounter_id}/actions
```

**Request Body** (Strike example):
```json
{
  "participant_id": 12,
  "action_type": "strike",
  "target_id": 15,
  "weapon_id": 42,
  "action_data": {
    "attack_number": 1
  }
}
```

**Response**: `200 OK`
```json
{
  "action_id": 1523,
  "action_type": "strike",
  "success": true,
  "result": {
    "attack_roll": 18,
    "attack_total": 25,
    "target_ac": 17,
    "degree": "success",
    "damage_dealt": 12,
    "target_hp_before": 23,
    "target_hp_after": 11
  },
  "reactions_triggered": [],
  "participant_state": {
    "actions_remaining": 2,
    "current_map_penalty": -5,
    "attacks_this_turn": 1
  }
}
```

### Stride Action

Move a participant.

```http
POST /encounters/{encounter_id}/actions/stride
```

**Request Body**:
```json
{
  "participant_id": 12,
  "distance": 25,
  "path": [
    {"x": 5, "y": 5},
    {"x": 10, "y": 5}
  ]
}
```

**Response**: `200 OK`
```json
{
  "action_id": 1524,
  "action_type": "stride",
  "success": true,
  "distance_moved": 25,
  "new_position": {"x": 10, "y": 5},
  "reactions_triggered": [],
  "participant_state": {
    "actions_remaining": 2
  }
}
```

### Cast Spell Action

Cast a spell.

```http
POST /encounters/{encounter_id}/actions/cast-spell
```

**Request Body**:
```json
{
  "participant_id": 12,
  "spell_id": 100,
  "spell_level": 2,
  "targets": [15, 16, 17],
  "metadata": {
    "spell_attack": true,
    "heightened": false
  }
}
```

**Response**: `200 OK`
```json
{
  "action_id": 1525,
  "action_type": "cast_spell",
  "success": true,
  "spell_name": "Magic Missile",
  "spell_slot_used": true,
  "results": [
    {
      "target_id": 15,
      "damage": 8,
      "hp_before": 23,
      "hp_after": 15
    },
    {
      "target_id": 16,
      "damage": 7,
      "hp_before": 30,
      "hp_after": 23
    }
  ],
  "participant_state": {
    "actions_remaining": 0,
    "spell_slots_remaining": {
      "2": 2
    }
  }
}
```

### Ready Action

Ready an action with trigger.

```http
POST /encounters/{encounter_id}/actions/ready
```

**Request Body**:
```json
{
  "participant_id": 12,
  "readied_action": {
    "action_type": "strike",
    "weapon_id": 42
  },
  "trigger": "When goblin moves within reach"
}
```

**Response**: `200 OK`
```json
{
  "action_id": 1526,
  "action_type": "ready",
  "success": true,
  "readied_action": {
    "action_type": "strike",
    "trigger": "When goblin moves within reach"
  },
  "participant_state": {
    "actions_remaining": 1,
    "is_readying": true
  }
}
```

### Execute Reaction

Use a reaction.

```http
POST /encounters/{encounter_id}/reactions
```

**Request Body**:
```json
{
  "participant_id": 12,
  "reaction_type": "attack_of_opportunity",
  "trigger_action_id": 1520,
  "target_id": 15
}
```

**Response**: `200 OK`
```json
{
  "reaction_id": 250,
  "reaction_type": "attack_of_opportunity",
  "success": true,
  "result": {
    "attack_roll": 15,
    "attack_total": 22,
    "target_ac": 17,
    "degree": "success",
    "damage_dealt": 10
  },
  "participant_state": {
    "reaction_available": false
  }
}
```

---

## 4. Condition Management

### Apply Condition

Add a condition to a participant.

```http
POST /encounters/{encounter_id}/participants/{participant_id}/conditions
```

**Request Body**:
```json
{
  "condition_type": "frightened",
  "value": 2,
  "duration_type": "turns",
  "duration_remaining": null,
  "source_description": "Demoralize action"
}
```

**Response**: `201 Created`
```json
{
  "condition_id": 550,
  "condition_type": "frightened",
  "value": 2,
  "applied_at_round": 3,
  "effects": [
    "-2 to all checks and DCs"
  ]
}
```

### Remove Condition

Remove a condition from a participant.

```http
DELETE /encounters/{encounter_id}/participants/{participant_id}/conditions/{condition_id}
```

**Response**: `200 OK`
```json
{
  "condition_id": 550,
  "removed": true,
  "message": "Frightened condition removed"
}
```

### List Conditions

Get all active conditions for a participant.

```http
GET /encounters/{encounter_id}/participants/{participant_id}/conditions
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "conditions": [
    {
      "condition_id": 551,
      "condition_type": "flat_footed",
      "value": null,
      "source": "Flanking",
      "effects": ["-2 AC"]
    },
    {
      "condition_id": 552,
      "condition_type": "persistent_damage",
      "value": 5,
      "source": "Fire",
      "effects": ["5 fire damage at end of turn", "DC 15 flat check to end"]
    }
  ]
}
```

---

## 5. HP Management

### Update HP

Modify participant's HP (damage or healing).

```http
PATCH /encounters/{encounter_id}/participants/{participant_id}/hp
```

**Request Body**:
```json
{
  "change_type": "damage",
  "amount": 15,
  "damage_type": "slashing",
  "source": "Goblin Warrior's longsword"
}
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "hp_before": 45,
  "hp_after": 30,
  "temp_hp_used": 0,
  "conditions_applied": [],
  "message": "Took 15 slashing damage"
}
```

### Apply Temporary HP

Grant temporary HP.

```http
POST /encounters/{encounter_id}/participants/{participant_id}/temp-hp
```

**Request Body**:
```json
{
  "amount": 10,
  "source": "False Life spell"
}
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "temp_hp_before": 0,
  "temp_hp_after": 10,
  "message": "Gained 10 temporary HP"
}
```

---

## 6. Initiative Management

### Get Initiative Order

Get sorted initiative order.

```http
GET /encounters/{encounter_id}/initiative
```

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "current_round": 3,
  "initiative_order": [
    {
      "participant_id": 12,
      "name": "Valeros",
      "team": "pc",
      "initiative": 18,
      "is_current_turn": true,
      "hp": "30/45",
      "conditions": ["flat_footed"]
    },
    {
      "participant_id": 15,
      "name": "Goblin Warrior 1",
      "team": "enemy",
      "initiative": 15,
      "is_current_turn": false,
      "hp": "11/23",
      "conditions": []
    }
  ]
}
```

### Reroll Initiative

Reroll initiative for specific participants (GM only).

```http
POST /encounters/{encounter_id}/initiative/reroll
```

**Request Body**:
```json
{
  "participant_ids": [15, 16],
  "reason": "Entering combat mid-encounter"
}
```

**Response**: `200 OK`
```json
{
  "rerolled": [
    {
      "participant_id": 15,
      "old_initiative": 15,
      "new_initiative": 12
    },
    {
      "participant_id": 16,
      "old_initiative": 14,
      "new_initiative": 18
    }
  ],
  "new_initiative_order": [...]
}
```

---

## 7. Participant Management

### Add Participant

Add a participant to ongoing combat (GM only).

```http
POST /encounters/{encounter_id}/participants
```

**Request Body**:
```json
{
  "type": "monster",
  "monster_id": 8,
  "display_name": "Goblin Reinforcement",
  "position": {"x": 15, "y": 10},
  "roll_initiative": true
}
```

**Response**: `201 Created`
```json
{
  "participant_id": 20,
  "name": "Goblin Reinforcement",
  "initiative": 14,
  "added_at_round": 3,
  "message": "Participant added to combat"
}
```

### Remove Participant

Remove a participant from combat (GM only).

```http
DELETE /encounters/{encounter_id}/participants/{participant_id}
```

**Query Parameters**:
- `reason` (optional): Reason for removal (`fled`, `removed`, `surrendered`)

**Response**: `200 OK`
```json
{
  "participant_id": 20,
  "removed": true,
  "reason": "fled",
  "message": "Participant removed from combat"
}
```

### Update Participant

Update participant stats or position (GM only).

```http
PATCH /encounters/{encounter_id}/participants/{participant_id}
```

**Request Body**:
```json
{
  "position": {"x": 10, "y": 8},
  "max_hp": 30
}
```

**Response**: `200 OK`
```json
{
  "participant_id": 12,
  "updated_fields": ["position", "max_hp"],
  "message": "Participant updated"
}
```

---

## 8. Combat State

### Get Combat State

Get complete current combat state.

```http
GET /encounters/{encounter_id}/state
```

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "status": "active",
  "current_round": 3,
  "current_turn": {
    "participant_id": 12,
    "name": "Valeros",
    "actions_remaining": 2,
    "reaction_available": true
  },
  "participants": [
    {
      "participant_id": 12,
      "name": "Valeros",
      "team": "pc",
      "initiative": 18,
      "hp": {
        "current": 30,
        "max": 45,
        "temp": 0
      },
      "position": {"x": 10, "y": 5},
      "conditions": ["flat_footed"],
      "is_active": true
    }
  ],
  "last_updated": "2026-02-12T10:45:23Z"
}
```

### Poll for Updates

Long-polling endpoint for state updates.

```http
GET /encounters/{encounter_id}/poll
```

**Query Parameters**:
- `since` (required): Timestamp of last known state
- `timeout` (optional): Seconds to wait for changes (default: 30, max: 60)

**Response**: `200 OK` (if changes) or `304 Not Modified` (no changes)
```json
{
  "changes": [
    {
      "type": "action_taken",
      "timestamp": "2026-02-12T10:45:25Z",
      "data": {...}
    },
    {
      "type": "turn_ended",
      "timestamp": "2026-02-12T10:45:26Z",
      "data": {...}
    }
  ],
  "current_state": {...}
}
```

---

## 9. Combat Log

### Get Combat Log

Get action log for encounter.

```http
GET /encounters/{encounter_id}/log
```

**Query Parameters**:
- `round` (optional): Filter by specific round
- `participant_id` (optional): Filter by participant
- `action_type` (optional): Filter by action type
- `page` (optional): Page number
- `per_page` (optional): Results per page (default: 50, max: 200)

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "log_entries": [
    {
      "action_id": 1520,
      "round_number": 3,
      "turn_sequence": 25,
      "participant_name": "Valeros",
      "action_type": "strike",
      "target_name": "Goblin Warrior 1",
      "result": "Hit for 12 damage",
      "timestamp": "2026-02-12T10:45:20Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 50,
    "total": 156
  }
}
```

### Get Combat Statistics

Get aggregate statistics for encounter.

```http
GET /encounters/{encounter_id}/statistics
```

**Response**: `200 OK`
```json
{
  "encounter_id": 25,
  "rounds_elapsed": 3,
  "total_actions": 45,
  "actions_by_type": {
    "strike": 22,
    "stride": 15,
    "cast_spell": 5,
    "other": 3
  },
  "damage_statistics": {
    "total_damage": 245,
    "damage_by_type": {
      "slashing": 120,
      "fire": 80,
      "piercing": 45
    },
    "top_damage_dealer": {
      "participant_id": 12,
      "name": "Valeros",
      "damage": 87
    }
  },
  "healing_statistics": {
    "total_healing": 67,
    "top_healer": {
      "participant_id": 14,
      "name": "Kyra",
      "healing": 45
    }
  }
}
```

---

## 10. WebSocket Events

### Connection

Connect to WebSocket for real-time updates.

```javascript
const ws = new WebSocket('wss://api.forseti.life/v1/ws/encounters/25');

ws.onopen = () => {
  // Send authentication
  ws.send(JSON.stringify({
    type: 'auth',
    token: 'bearer_token_here'
  }));
};
```

### Event Types

**Combat State Change**
```json
{
  "event": "state_changed",
  "encounter_id": 25,
  "timestamp": "2026-02-12T10:45:23Z",
  "data": {
    "status": "active",
    "current_round": 3,
    "current_turn_participant_id": 12
  }
}
```

**Turn Started**
```json
{
  "event": "turn_started",
  "encounter_id": 25,
  "timestamp": "2026-02-12T10:45:23Z",
  "data": {
    "participant_id": 12,
    "name": "Valeros",
    "actions_remaining": 3
  }
}
```

**Action Taken**
```json
{
  "event": "action_taken",
  "encounter_id": 25,
  "timestamp": "2026-02-12T10:45:25Z",
  "data": {
    "action_id": 1520,
    "participant_id": 12,
    "action_type": "strike",
    "target_id": 15,
    "result": {...}
  }
}
```

**HP Changed**
```json
{
  "event": "hp_changed",
  "encounter_id": 25,
  "timestamp": "2026-02-12T10:45:26Z",
  "data": {
    "participant_id": 15,
    "hp_before": 23,
    "hp_after": 11,
    "change_type": "damage",
    "amount": 12
  }
}
```

**Condition Applied**
```json
{
  "event": "condition_applied",
  "encounter_id": 25,
  "timestamp": "2026-02-12T10:45:27Z",
  "data": {
    "participant_id": 15,
    "condition_type": "frightened",
    "value": 2,
    "source": "Demoralize"
  }
}
```

**Reaction Triggered**
```json
{
  "event": "reaction_triggered",
  "encounter_id": 25,
  "timestamp": "2026-02-12T10:45:28Z",
  "data": {
    "participant_id": 12,
    "reaction_type": "attack_of_opportunity",
    "trigger_action_id": 1520
  }
}
```

**Participant Defeated**
```json
{
  "event": "participant_defeated",
  "encounter_id": 25,
  "timestamp": "2026-02-12T10:45:30Z",
  "data": {
    "participant_id": 15,
    "name": "Goblin Warrior 1",
    "defeat_reason": "hp_zero"
  }
}
```

---

## Error Responses

### Standard Error Format

```json
{
  "error": {
    "code": "INVALID_ACTION",
    "message": "Not enough actions remaining",
    "details": {
      "actions_required": 2,
      "actions_available": 1
    },
    "timestamp": "2026-02-12T10:45:23Z"
  }
}
```

### HTTP Status Codes

- `200 OK`: Success
- `201 Created`: Resource created
- `204 No Content`: Success with no response body
- `304 Not Modified`: No changes (polling)
- `400 Bad Request`: Invalid request data
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `409 Conflict`: State conflict (e.g., not participant's turn)
- `422 Unprocessable Entity`: Validation failed
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error
- `503 Service Unavailable`: Service temporarily unavailable

### Common Error Codes

- `AUTH_REQUIRED`: Authentication required
- `PERMISSION_DENIED`: Insufficient permissions
- `ENCOUNTER_NOT_FOUND`: Encounter doesn't exist
- `PARTICIPANT_NOT_FOUND`: Participant doesn't exist
- `INVALID_STATE`: Combat in wrong state for action
- `NOT_YOUR_TURN`: Action attempted out of turn
- `INVALID_ACTION`: Action validation failed
- `INSUFFICIENT_ACTIONS`: Not enough actions remaining
- `INSUFFICIENT_RESOURCES`: Not enough spell slots/resources
- `INVALID_TARGET`: Target invalid or out of range
- `CONDITION_PREVENTS_ACTION`: Condition blocks action
- `RATE_LIMIT_EXCEEDED`: Too many requests

---

## Rate Limiting

- **Authenticated Users**: 100 requests/minute per user
- **GM Actions**: 200 requests/minute per user
- **WebSocket Messages**: 50 messages/second per connection

Rate limit headers included in responses:
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1644667200
```

---

## Versioning

API uses URL-based versioning:
- Current version: `/v1/`
- Breaking changes increment major version
- Backward-compatible changes within same version
- Deprecated endpoints receive 12-month notice

---

## Summary

The Combat API provides:

- ✅ **Complete Combat Control**: All combat operations via REST
- ✅ **Real-time Updates**: WebSocket for live state synchronization
- ✅ **RESTful Design**: Standard HTTP methods and status codes
- ✅ **Comprehensive Error Handling**: Clear error messages
- ✅ **Scalable**: Rate limiting and efficient polling
- ✅ **Secure**: Authentication and authorization on all endpoints
- ✅ **Well-Documented**: Complete request/response examples

**Related Documents**:
- [Combat Engine Service](./combat-engine-service.md)
- [Combat State Machine](./combat-state-machine.md)
- [Combat Database Schema](./combat-database-schema.md)
- [Combat UI Design](./combat-ui-design.md)
