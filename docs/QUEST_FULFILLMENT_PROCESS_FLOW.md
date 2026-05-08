# Quest Fulfillment — High-Level Process Flow

## Goal

Create a deterministic, auditable quest-fulfillment loop where the DM agent can
recognize quest touchpoints and trigger progress updates without relying on
manual GM checkboxing for every event.

## Core Principles

- Quest state is authoritative in persistence (`dc_campaign_quest_progress`).
- DM agent proposes actions; quest services apply state transitions.
- Deterministic touchpoints auto-apply; ambiguous touchpoints require confirmation.
- Progress updates are idempotent to prevent duplicate increments.
- UI (Quest Journal) renders from canonical state, never from narration text.

## State Model

At objective level:

- `not_started`
- `in_progress`
- `ready_for_turn_in` (optional objective type)
- `completed`
- `failed`

Objective completion check:

- incomplete = `completed == false` OR `current < target_count`
- complete = `completed == true` OR `current >= target_count`

Quest completion check:

- all required objectives complete across relevant phases.

## Event Contract (Touchpoints)

Every potential quest-relevant action should become a normalized event payload:

```json
{
  "event_type": "QUEST_TOUCHPOINT",
  "objective_type": "collect",
  "objective_id": "collect_wine",
  "campaign_id": 37,
  "character_id": 133,
  "room_id": "7f2f1051-...0001",
  "entity_ref": "room_supply_1",
  "item_ref": "wine_bottle",
  "confidence": "high",
  "occurred_at": 1772899999
}
```

## End-to-End Flow

1. Player acts (move/interact/chat/combat/explore).
2. Runtime emits candidate touchpoint event.
3. DM agent evaluates event against active objective context.
4. Agent chooses one of:
   - `APPLY_PROGRESS` (deterministic)
   - `REQUEST_CONFIRMATION` (ambiguous)
   - `NO_ACTION` (irrelevant)
5. Quest engine validates event/objective compatibility.
6. If valid, progress is persisted via quest tracker service/API.
7. Engine checks objective -> phase -> quest completion transitions.
8. Quest log entry is appended (campaign + character scope).
9. Quest Journal refreshes from canonical payload.

## Deterministic vs Ambiguous Rules

Deterministic (auto-apply):

- objective exists and is active
- objective type matches event type
- target entity/item/NPC matches expected refs
- event not already consumed (dedupe fingerprint)

Ambiguous (confirm first):

- target is loosely matched by name only
- item/NPC is not quest-tagged
- multiple objectives could consume the same event
- low-confidence extraction from free-form text

## Idempotency and Safety

Use an event fingerprint to suppress duplicates:

- `objective_id + character_id + entity_ref/item_ref + room_id + short time bucket`

Behavior:

- duplicate within active window => ignored/no increment
- duplicate outside window => allow if state still incomplete and target permits repeat

## DM Agent Responsibilities

The DM agent must:

- include active objective context in decision-making
- emit structured quest touchpoint proposals
- avoid direct state mutation in narration
- call quest APIs/services for updates
- surface pending confirmations to player/GM when ambiguous

The DM agent must not:

- declare objective complete without quest-engine confirmation
- increment progress from ungrounded entities
- double-apply touchpoints from repeated chat text

## Integration Points (Current System)

Existing components that can host this flow:

- `QuestTrackerService` (progress + completion)
- `QuestTrackerController` APIs (`/progress`, `/complete`, journal endpoints)
- `HexMapController::loadQuestSummary` (active quest payload for UI/runtime)
- `hexmap.js` interact/collect/NPC turn-in flow (event producers)
- GM prompt contract in `GM_INSTRUCTIONS.md`

## MVP Rollout Plan

1. Standardize touchpoint payload in frontend/runtime and chat handlers.
2. Add deterministic evaluator registry by objective type (`collect`, `interact`, etc.).
3. Add duplicate-event guard table or cache key strategy.
4. Add pending confirmation queue for ambiguous touchpoints.
5. Expose debug/audit feed for quest decisions and state transitions.

## Acceptance Criteria

- Quest Journal reflects objective progress accurately after touchpoints.
- Repeated identical touchpoints do not double-count.
- DM agent can progress deterministic objectives without manual intervention.
- Ambiguous touchpoints produce explicit confirmation requests.
- Completion and reward triggers occur only after objective state validates.
