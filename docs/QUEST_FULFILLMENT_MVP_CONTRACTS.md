# Quest Fulfillment — MVP Interface Contracts

## Purpose

This document defines concrete, implementation-ready contracts for the quest
fulfillment flow:

- touchpoint event payload
- evaluator registry behavior
- confirmation queue model
- minimal API/service boundaries

Use this with `QUEST_FULFILLMENT_PROCESS_FLOW.md`.

---

## 1) Touchpoint Event Contract (Canonical)

A touchpoint is a normalized event emitted from gameplay or DM-agent reasoning
when something may affect quest progress.

### 1.1 Required Envelope

```json
{
  "event_type": "QUEST_TOUCHPOINT",
  "event_id": "qtp_01J4...",
  "campaign_id": 37,
  "character_id": 133,
  "occurred_at": 1772901204,
  "source": "hexmap.interact",
  "source_ref": "entity_instance:room_supply_1",
  "touchpoint": {
    "objective_type": "collect",
    "objective_id": "collect_wine",
    "room_id": "7f2f1051-5f88-45a2-a66a-0f7063900001",
    "entity_ref": "room_supply_1",
    "item_ref": "wine_bottle",
    "npc_ref": null,
    "quantity": 1,
    "confidence": "high",
    "evidence": {
      "prompt_trace_id": null,
      "message_id": null,
      "notes": "Player collected tagged quest item"
    }
  }
}
```

### 1.2 Field Notes

- `event_id`: unique ID for traceability.
- `source`: producer identity (`hexmap.interact`, `dm_agent.chat`, `encounter.resolution`).
- `source_ref`: producer-specific pointer to raw event/entity.
- `objective_id`: optional for extraction cases; resolver may fill it.
- `confidence`: `high|medium|low`; low typically routes to confirmation.

### 1.3 Fingerprint (Deduplication)

Fingerprint input:

- `campaign_id`
- `character_id`
- `objective_id` (or resolved objective)
- `entity_ref|item_ref|npc_ref`
- `room_id`
- time bucket (e.g., 15–30 seconds)

Fingerprint output should be stored and checked before applying progress.

---

## 2) Evaluator Registry Contract

Objective evaluation should be strategy-based (per objective type), not a
single conditional chain.

### 2.1 Evaluator Interface (Conceptual)

```php
interface QuestObjectiveEvaluatorInterface {
  public function supports(string $objectiveType): bool;

  public function evaluate(
    array $objective,
    array $touchpointEvent,
    array $objectiveState,
    array $context
  ): QuestEvaluationResult;
}
```

### 2.2 Evaluation Result Contract

```json
{
  "decision": "APPLY_PROGRESS",
  "objective_id": "collect_wine",
  "progress_delta": 1,
  "new_current": 3,
  "target_count": 5,
  "is_objective_complete": false,
  "needs_confirmation": false,
  "reason": "collect objective matched tagged item_ref"
}
```

Allowed `decision` values:

- `APPLY_PROGRESS`
- `REQUEST_CONFIRMATION`
- `NO_ACTION`

### 2.3 Registry Contract

```php
interface QuestEvaluatorRegistryInterface {
  public function evaluate(
    array $activeObjectives,
    array $touchpointEvent,
    array $context = []
  ): QuestEvaluationResult;
}
```

Registry behavior:

1. Resolve objective candidates.
2. Route to evaluator by objective type.
3. Return strongest deterministic match.
4. If ambiguous, return `REQUEST_CONFIRMATION` with candidate set.

---

## 3) Confirmation Queue Contract

Ambiguous touchpoints should be queued for player/GM confirmation.

### 3.1 Queue Entry Shape

```json
{
  "confirmation_id": "qcf_01J4...",
  "campaign_id": 37,
  "character_id": 133,
  "status": "pending",
  "created_at": 1772901212,
  "expires_at": 1772904812,
  "touchpoint_event": { "...": "canonical event payload" },
  "candidates": [
    {
      "objective_id": "collect_wine",
      "objective_type": "collect",
      "label": "Collect wine bottle from around the tavern"
    },
    {
      "objective_id": "collect_supply_bundle",
      "objective_type": "collect",
      "label": "Gather 2 Supplies from the Room"
    }
  ],
  "prompt": "Should this count toward wine collection or supply gathering?"
}
```

### 3.2 Queue Statuses

- `pending`
- `approved`
- `rejected`
- `expired`
- `resolved_auto` (if later determinism appears)

### 3.3 Resolution Contract

Resolution input:

```json
{
  "confirmation_id": "qcf_01J4...",
  "resolution": "approved",
  "selected_objective_id": "collect_wine",
  "resolved_by": "player|gm|system"
}
```

Resolution output should include the resulting evaluation/application outcome.

---

## 4) Minimal Service/API Boundaries (MVP)

### 4.1 Service Layer

- `QuestTouchpointService::ingestEvent(array $event): array`
  - dedupe check
  - evaluator dispatch
  - apply progress or queue confirmation
  - return structured result

- `QuestConfirmationService`
  - create pending confirmation
  - list pending by campaign/character
  - resolve confirmation

### 4.2 API Endpoints (MVP)

- `POST /api/campaign/{campaign_id}/quest-touchpoints`
  - ingest canonical event
- `GET /api/campaign/{campaign_id}/quest-confirmations`
  - list pending confirmations
- `POST /api/campaign/{campaign_id}/quest-confirmations/{id}/resolve`
  - approve/reject + optional selected objective

All endpoints should return:

- `success`
- `decision`
- `quest_id` / `objective_id` (if applicable)
- `progress_delta`
- `requires_confirmation`
- `confirmation_id` (if queued)

---

## 5) DM Agent Contract

When the DM agent detects a quest touchpoint:

1. Emit canonical touchpoint payload.
2. Include confidence and evidence.
3. Do not narratively assert completion until the quest engine confirms it.

Agent decisions map to engine actions:

- `APPLY_PROGRESS` -> ingest touchpoint with deterministic intent.
- `REQUEST_CONFIRMATION` -> create queue entry.
- `NO_ACTION` -> log ignored reason for audit.

---

## 6) Observability / Audit (MVP)

Each ingest attempt should log:

- `event_id`, fingerprint, dedupe outcome
- evaluator selected
- decision
- progress delta
- resulting objective state
- confirmation ID (if any)

This can be written to quest log tables or a dedicated operational log stream.

---

## 7) Implementation Sequence

1. Add canonical event builder in runtime producers (`hexmap.js` interact paths).
2. Implement evaluator registry with `collect` + `interact` evaluators first.
3. Add dedupe guard persistence.
4. Add confirmation queue and resolution endpoint.
5. Wire DM prompt/tooling to emit canonical touchpoint events.
6. Surface pending confirmations in Quests tab UI.

---

## 8) MVP Acceptance Checks

- Same item interaction spam does not increase objective count repeatedly.
- Deterministic collect objective updates without manual GM intervention.
- Ambiguous touchpoint always creates confirmation (never silent mutation).
- Objective and quest completion transitions remain consistent with
  `dc_campaign_quest_progress`.
- Quest Journal reflects post-apply state immediately after ingest.
