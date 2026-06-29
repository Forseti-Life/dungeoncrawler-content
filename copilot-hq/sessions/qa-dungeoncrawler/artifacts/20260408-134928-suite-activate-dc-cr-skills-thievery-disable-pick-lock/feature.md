# Feature Brief: Thievery Skill Actions

- Work item id: dc-cr-skills-thievery-disable-pick-lock
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Thievery (Dex)
- Depends on: dc-cr-skill-system, dc-cr-hazards

## Goal

Implement Thievery (Dex) skill action handlers — Disable Device, Pick a Lock, Palm an Object, and Steal — with DC-by-complexity tables for locks and traps and trained-only enforcement across all actions.

## Source reference

> "Pick a Lock: You try to open a lock using Thievery. The DC to pick a lock is based on the lock's quality: Simple DC 15, Average DC 20, Good DC 25, Superior DC 30."

## Implementation hint

Define a `LockComplexity` enum (Simple/Average/Good/Superior) with associated DCs (15/20/25/30); `PickALockAction` validates trained proficiency, resolves the Thievery check, and on success sets the lock entity's `locked` flag to false. `DisableDeviceAction` follows the same pattern with trap-specific DC tables from the hazard entity's `disable_dc` field. `StealAction` resolves Thievery vs observer Perception DC; set a `concealed_object_id` on the character on success. All four actions require Trained proficiency; enforce at the action handler level before roll resolution.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; lock/trap state changes are server-authoritative; Trained check enforced server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Lock/trap entity ID must be a valid game entity; Thievery DC from server-side table only; all four actions require Trained proficiency enforced before roll.
- PII/logging constraints: no PII logged; log character_id, action_type, target_entity_id, dc, outcome; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1747, 1748, 1749, 1750, 1751, 1752, 1753, 1754, 1755, 1756
- See `runbooks/roadmap-audit.md` for audit process.
