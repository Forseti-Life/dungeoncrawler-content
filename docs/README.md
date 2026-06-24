# Dungeon Crawler Content Docs Index

This folder contains active implementation and contract docs used by the module runtime.

## Current State (2026-06-24)

- Room chat is the authoritative ingress for GM-driven state mutation.
- Quest surfacing and activation are wired to canonical quest rows and validated quest update payloads.
- Quest start materializes collect-objective room items immediately.
- Hexmap v2 runtime uses the server-backed quest journal refresh path and stabilized active-room resolution.

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

## Related Top-Level Architecture Docs

- `../README.md`
- `../GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md`
- `../CHAT_AND_NARRATION_ARCHITECTURE.md`
- `../DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md`
- `../HEXMAP_ARCHITECTURE.md`
