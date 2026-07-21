# Dungeon Crawler Content Docs Index

This folder contains active implementation and contract docs used by the module runtime.

## Current State (2026-06-24)

- Room chat is the authoritative ingress for GM-driven state mutation.
- Quest surfacing and activation are wired to canonical quest rows and validated quest update payloads.
- Quest start materializes collect-objective room items immediately.
- Hexmap v2 runtime uses the server-backed quest journal refresh path and stabilized active-room resolution.
- Canonical storyline template storage authority is `dc_canonical_storylines` (no legacy dual-table reads/writes).
- Canonical dungeon/room location indexing for storyline validation is DB-backed (`dungeoncrawler_content_dungeons`, `dungeoncrawler_content_rooms`), not JSON-file sourced.
- Storyline AI generation paths hard-fail on model errors when AI mode is active (no implicit fallback recovery).
- Contract-critical quest-to-storyline sync failures are surfaced as hard failures.
- Starter-room and room-authored NPC `quest_id` references are prevalidated against `dc_canonical_quests`; missing canonical template references now fail fast as integrity violations.
- Quests generated with `initial_status=offered` are normalized to `active` at materialization time so surfaced quests are immediately progressable.

## Validation Boundary (Authoritative)

Use this boundary when troubleshooting validation defects:

| Subsystem | Primary ownership | Primary interfaces |
|---|---|---|
| Campaign runtime quest-state validation | Live campaign quest progression and touchpoint handling | `/api/campaign/{campaign_id}/quest-touchpoints`, `/api/campaign/{campaign_id}/quest-confirmations`, `QuestTouchpointService`, `QuestTrackerService` |
| Canonical library validator | Canonical storyline/quest structure and contract correctness | `StorylineManagerService::validateStorylineEndToEndContract()`, Storyline Explorer diagnostic stages |

Never treat these as one subsystem: runtime progression validation is for active campaign state, while canonical validation is for library/template integrity.

## Documents in This Folder

- **CANONICAL_ACTION_EXECUTOR_REGISTRY.md**  
  Canonical action execution registry and contract boundaries.

- **QUEST_FULFILLMENT_PROCESS_FLOW.md**  
  End-to-end quest fulfillment and touchpoint flow.

- **QUEST_FULFILLMENT_MVP_CONTRACTS.md**  
  Quest fulfillment payload contracts and decision model.

- **FEAT_EFFECT_AUDIT.md**  
  Feat implementation coverage and effect-audit status.

- **FEAT_IMPLEMENTATION_REVIEW.md**  
  Feat implementation review notes and integration details.

- **INVENTORY_CAPACITY_RULES.md**  
  Entity inventory capacity rules and constraints.

- **INVENTORY_REFACTORING_COMPLETE.md**  
  Inventory refactor integration summary.

- **CONTAINER_SYSTEM_INTEGRATION.md**  
  Container behavior and integration model.

- **STANDARD_DUNGEON_TILES.md**  
  Canonical dungeon tile definitions and usage.

- **ACTOR_HARNESS_SECURITY_BOUNDARY.md**  
  Authoritative capability boundary for actor harness runtime security hardening.

## Related Top-Level Architecture Docs

- `../README.md`
- `../GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md`
- `../CHAT_AND_NARRATION_ARCHITECTURE.md`
- `../DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md`
- `../HEXMAP_ARCHITECTURE.md`
