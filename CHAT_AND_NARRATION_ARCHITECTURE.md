# Chat, Session & Narration Architecture

**Last updated:** 2026-03-05  
**Status:** Implemented and tested (369 tests, 0 failures)

---

## Overview

The dungeoncrawler module implements a **dual narration pipeline** that produces two
complementary narrative outputs for every game action:

1. **AiGmService** — Trigger-based one-shot narration (room entry, encounter start/end, round start, phase transition). Returns a single text block in the `narration` response field.
2. **NarrationEngine** — Batch-buffered, perception-filtered, per-character narration delivered via the `session_narration` response field.

Both run in parallel on every `GameCoordinatorService.processAction()` call, preserving
backward compatibility while enabling the new perception-aware narrative system.

See also [GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md](GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md) for the authoritative dialogue -> canonical action -> phase transition -> encounter lifecycle.
See also [DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md](DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md) for the planned shift from prompt-driven GM action selection to deterministic room-chat orchestration.

---

## System Diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│                    GameCoordinatorService                            │
│                     processAction()                                  │
│                                                                      │
│  ┌─────────────┐   ┌─────────────────┐   ┌───────────────────┐     │
│  │ Exploration  │   │   Encounter     │   │    Downtime       │     │
│  │ PhaseHandler │   │  PhaseHandler   │   │  PhaseHandler     │     │
│  └──────┬───┬──┘   └───┬────┬────────┘   └──────────────────┘      │
│         │   │           │    │                                       │
│    ┌────┘   └────┐ ┌───┘    └───┐                                   │
│    ▼             ▼ ▼            ▼                                    │
│  AiGmService  NarrationEngine                                       │
│  (one-shot)   (batch buffer)                                        │
│    │               │                                                │
│    ▼               ▼                                                │
│  response:       response:                                          │
│  'narration'     'session_narration'                                │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Chat Session Hierarchy

`ChatSessionManager` maintains a **tree of chat sessions** per campaign.
Each session is a logical conversation channel with its own message history.

```
Campaign Root
├── System Log              (mechanical events, dice rolls)
├── Party Chat              (cross-room party channel)
├── Dungeon Root
│   ├── Room: Great Hall    (objective "God view" — everything that happens)
│   │   ├── Character: Torgar Ironforge   (perception-filtered narrative)
│   │   ├── Character: Elara Moonshade    (perception-filtered narrative)
│   │   └── Encounter: Round 1-5         (combat-scoped narrative)
│   └── Room: Dark Corridor
│       └── Character: Torgar Ironforge
├── Whisper Channel         (private PC-to-PC)
├── Spell Channel           (e.g., Message cantrip)
└── GM Private              (hidden GM notes, secret checks)
```

### Session Types

| Type | Scope | Feed-up | Purpose |
|------|-------|---------|---------|
| `campaign` | Campaign root | — | Top-level container |
| `dungeon` | Per dungeon | → campaign | Dungeon-level aggregate |
| `room` | Per room | → dungeon → campaign | Objective reality (GM God view) |
| `character_narrative` | Per character per room | — | Perception-filtered personal narrative |
| `encounter` | Per encounter | → room | Combat-scoped events |
| `party` | Campaign-wide | — | Cross-room party chat |
| `whisper` | Between 2 PCs | — | Private messaging |
| `spell` | Spell effect | — | Magical communication (Message, Sending) |
| `gm_private` | GM only | — | Secret checks, hidden notes |
| `system_log` | Campaign-wide | — | Dice rolls, mechanical events |

### Session Keys (Deterministic)

Sessions use deterministic composite keys for idempotent creation:

```
campaign:{campaign_id}
dungeon:{campaign_id}:{dungeon_id}
room:{campaign_id}:{dungeon_id}:{room_id}
character_narrative:{campaign_id}:{dungeon_id}:{room_id}:{character_id}
encounter:{campaign_id}:{encounter_id}
party:{campaign_id}
system_log:{campaign_id}
gm_private:{campaign_id}
whisper:{campaign_id}:{char_a_id}:{char_b_id}
spell:{campaign_id}:{spell_name}:{caster_id}
```

### Feed-Up Rules

When a message is posted to a child session with `feed_up = TRUE`:
- Room messages feed up to dungeon session, then to campaign session
- This gives the dungeon and campaign sessions an aggregate timeline of all activity

### Room Chat Turn-Harness Logging

Room chat now carries a second, troubleshooting-oriented log layer for actor sequencing:

- **Visible room-chat system lines** announce:
  - `Turn order: Player -> Narrator -> ...`
  - `Next speaker: ...`
- **Structured database audit rows** are written to `dc_room_turn_logs` for each room-chat harness pass.

`dc_room_turn_logs` is intended for debugging room conversation ordering bugs. Each row stores:

| Field group | Purpose |
|---|---|
| campaign / dungeon / room | locate the exact room turn |
| `turn_key`, `sequence_index` | correlate one room-turn pass and its ordered sub-events |
| `event_type` | `turn_order`, `next_speaker`, `speaker_completed` |
| actor metadata | which NPC was scheduled or completed |
| `payload_json` | grounded troubleshooting context without relying only on free-text chat |

These internal turn-log chat lines are marked with `internal_log = TRUE` and are excluded from prompt-transcript assembly so GM/NPC/player-automation prompts continue to see only the actual conversation.

Operators can inspect these rows through the admin route:

- `/admin/content/dungeoncrawler/room-turn-logs`

Room chat API responses also expose `turn_log_key`, which matches `dc_room_turn_logs.turn_key` and the server-side turn-order logger entries for the same harness pass.

---

## Chat Channel Manager

`ChatChannelManager` manages the **active channel set** per player per room.

### Channel Types

| Channel | Always Active | Limit | Description |
|---------|--------------|-------|-------------|
| `room` | Yes | 1 | Current room (always the base channel) |
| `party` | Yes | 1 | Party-wide persistent chat |
| `whisper` | No | 2 max non-room | Private PC-to-PC |
| `spell` | No | 2 max non-room | Spell-based communication |
| `gm_private` | No | 1 | GM secret channel |
| `system_log` | No | 1 | Mechanical event feed |

**Max 4 non-room channels** can be active simultaneously.

### PF2e Spell-to-Channel Mapping

Certain spells create communication channels:
- `Message` → whisper-range spell channel
- `Sending` → cross-distance spell channel
- `Telepathy` → persistent mental link channel

---

## NarrationEngine Pipeline

### Event Flow

```
Game Action (strike, move, search, room transition, etc.)
    │
    ▼
PhaseHandler.queueNarrationEvent()
    │
    ▼
NarrationEngine.queueRoomEvent(campaign_id, dungeon_id, room_id, event, present_characters)
    │
    ├── Is MECHANICAL_EVENT_TYPES? ──► System Log (no narration)
    │       (dice_roll, skill_check_result, damage_applied,
    │        condition_applied, condition_removed, initiative_set)
    │
    ├── Is IMMEDIATE_NARRATION_TYPES? ──► Immediate GenAI per-character narration
    │       (dialogue, speech, shout, npc_speech)
    │
    └── Other events ──► Add to buffer
            │
            └── Buffer ≥ 8 events? ──► flushNarration() → per-character scene beats
```

### Event Format

```php
$event = [
  'type'           => 'action',       // action|dialogue|stealth_movement|...
  'speaker'        => 'Torgar',       // display name
  'speaker_type'   => 'player',       // player|npc|gm|system
  'speaker_ref'    => 'char_101',     // entity ID
  'content'        => 'Torgar strikes the goblin with his warhammer.',
  'language'       => 'Common',       // for speech events
  'volume'         => 'normal',       // normal|whisper|shout
  'perception_dc'  => NULL,           // DC to notice (for stealth/hidden)
  'mechanical_data' => [...],         // dice, damage, conditions
  'visibility'     => 'public',       // public|gm_only
];
```

### Present Characters Format

```php
$present_characters = [
  [
    'character_id' => 101,
    'name'         => 'Torgar Ironforge',
    'perception'   => 5,           // Perception modifier
    'languages'    => ['Common', 'Dwarvish'],
    'senses'       => ['darkvision'],
    'conditions'   => [],          // deafened, blinded, unconscious...
    'position'     => ['q' => 3, 'r' => 2],
  ],
  // ... more characters
];
```

Use `NarrationEngine::buildPresentCharacters($dungeon_data, $room_id)` to extract this
from dungeon data.

### Perception Filtering

The NarrationEngine determines what each character perceived:
- **Can they hear it?** (distance, deafened condition, walls)
- **Can they see it?** (darkvision, invisible, stealth vs Perception DC)
- **Are they conscious?** (unconscious, sleeping conditions)
- **Do they understand it?** (languages known, for speech events)
- **Secret checks** (Perception, Recall Knowledge — GM rolls, player doesn't know DC)

### Perception-Gated Event Types

These events require a Perception check (event `perception_dc` vs character `perception`):
- `stealth_movement`
- `hidden_action`
- `trap_trigger`
- `secret_door`
- `whispered_speech`
- `pickpocket`

---

## Wired Game Actions

### Exploration Phase → NarrationEngine

| Action | Event Type | Notes |
|--------|-----------|-------|
| `interact` | `action` | "Torgar interacts with ..." |
| `search` | `skill_check_result` + `action` | Mechanical roll + discoveries |
| `transition` (room entry) | `action` | "The party enters ..." (room_id override) |
| `cast_spell` | `action` | "Torgar casts Light." |
| Phase enter | `action` | "Exploration begins." |
| Encounter trigger | `action` | "$reason" (during room transition) |

### Encounter Phase → NarrationEngine

| Action | Event Type | Notes |
|--------|-----------|-------|
| Round start | `action` | "Round N begins." (from end_turn) |
| Encounter start (onEnter) | `action` | "Combat begins!" |
| Encounter end (onExit) | `action` | "The encounter ends after N rounds." |

### GameCoordinatorService → NarrationEngine

| Trigger | Event Type | Notes |
|---------|-----------|-------|
| Phase transition | `action` | "Phase transitions from X to Y." |
| Post-action flush | — | `flushNarration()` called after every `processAction()` |

---

## GC Response Shape

```php
$response = [
  'success'           => TRUE,
  'game_state'        => [...],          // Client-safe phase/turn/round state
  'result'            => [...],          // Action-specific result data
  'mutations'         => [...],          // Entity state changes
  'events'            => [...],          // Logged game events
  'phase_transition'  => [...] | NULL,   // If phase changed
  'narration'         => '...' | NULL,   // AiGmService one-shot narration (legacy)
  'session_narration' => [...] | NULL,   // NarrationEngine per-character scene beats (NEW)
  'available_actions' => ['move', ...],  // Legal actions for current phase
  'state_version'     => 42,            // Optimistic concurrency version
  'error'             => NULL,
];
```

The `session_narration` field contains per-character scene beats from the NarrationEngine
buffer flush. Each beat is keyed by character_id and contains the filtered narrative
that character perceived.

---

## Database Tables

### dc_chat_sessions (update hook 10024)

| Column | Type | Description |
|--------|------|-------------|
| `id` | serial | Primary key |
| `campaign_id` | int | Campaign ID |
| `session_key` | varchar(255) | Deterministic composite key |
| `session_type` | varchar(64) | Type (room, character_narrative, encounter, etc.) |
| `parent_id` | int | Parent session ID (tree hierarchy) |
| `dungeon_id` | varchar(128) | Dungeon context |
| `room_id` | varchar(128) | Room context |
| `label` | varchar(255) | Display label |
| `metadata` | text | JSON metadata |
| `created` | int | Created timestamp |
| `changed` | int | Last modified timestamp |

**Index:** `session_key` (unique), `campaign_id`, `parent_id`

### dc_chat_messages (update hook 10024)

| Column | Type | Description |
|--------|------|-------------|
| `id` | serial | Primary key |
| `session_id` | int | Parent session ID (FK → dc_chat_sessions) |
| `campaign_id` | int | Campaign ID |
| `speaker` | varchar(255) | Display name |
| `speaker_type` | varchar(64) | player, npc, gm, system |
| `speaker_ref` | varchar(128) | Entity/character ID reference |
| `content` | text | Message content |
| `message_type` | varchar(64) | Type (dialogue, action, mechanical, system, etc.) |
| `visibility` | varchar(32) | public, gm_only |
| `mechanical_data` | text | JSON (dice, conditions, damage, etc.) |
| `source_message_id` | int | Reference to originating message |
| `created` | int | Created timestamp |

**Index:** `session_id`, `campaign_id`, `created`

---

## Service Dependency Graph

```
GameCoordinatorService
├── ExplorationPhaseHandler
│   ├── RoomChatService ──► NarrationEngine ──► ChatSessionManager
│   ├── DungeonStateService
│   ├── CharacterStateService
│   ├── NumberGenerationService
│   ├── AiGmService
│   └── NarrationEngine (direct)
├── EncounterPhaseHandler
│   ├── CombatEngine
│   ├── ActionProcessor
│   ├── HPManager
│   ├── ConditionManager
│   ├── CombatCalculator
│   ├── EncounterAiIntegrationService
│   ├── RulesEngine
│   ├── AiGmService
│   ├── NpcPsychologyService
│   └── NarrationEngine (direct)
├── DowntimePhaseHandler
├── AiGmService
├── NarrationEngine (direct, for flush)
└── GameEventLogger
```

---

## Key Implementation Files

| File | Lines | Purpose |
|------|-------|---------|
| `ChatSessionManager.php` | ~941 | Session tree CRUD, hierarchy, feed-up |
| `NarrationEngine.php` | ~940 | Buffer/flush, perception filter, GenAI narration |
| `ChatChannelManager.php` | ~660 | Channel lifecycle, spell mapping |
| `GameCoordinatorService.php` | ~580 | Central orchestrator, phase state machine |
| `ExplorationPhaseHandler.php` | ~990 | 10 exploration actions + NarrationEngine bridge |
| `EncounterPhaseHandler.php` | ~1450 | 9 encounter actions + NarrationEngine bridge |
| `RoomChatService.php` | ~1480 | AI GM conversation, bridge to sessions |
| `AiGmService.php` | ~??? | Trigger-based one-shot narration |

---

## Related Documentation

- [GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md](GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md) — authoritative process flow and orchestrator ownership
- [COMBAT_ENGINE_ARCHITECTURE.md](COMBAT_ENGINE_ARCHITECTURE.md) — combat lifecycle, APIs, persistence, test coverage

---

## Test Suites

| Suite | File | Tests | Coverage |
|-------|------|-------|----------|
| Chat sessions | `tests/chat_session_test.php` | 54 | Session CRUD, hierarchy, feed-up |
| Chat integration | `tests/chat_integration_test.php` | 46 | RoomChat→NarrationEngine bridge, REST |
| Narration pipeline | `tests/narration_pipeline_test.php` | 66 | DI wiring, buildPresentCharacters, queueNarrationEvent |
| Combat engine | `tests/combat_engine_test.php` | 136 | PF2e combat mechanics |
| NPC psychology | `tests/npc_psychology_test.php` | 67 | NPC personality, attitude, inner monologue |
| **Total** | | **369** | |

Run all with:
```bash
cd sites/dungeoncrawler
for t in chat_session chat_integration narration_pipeline combat_engine npc_psychology; do
  vendor/bin/drush php:script web/modules/custom/dungeoncrawler_content/tests/${t}_test.php
done
```
