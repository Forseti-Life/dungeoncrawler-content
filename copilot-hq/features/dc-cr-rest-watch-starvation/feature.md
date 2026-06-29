# Feature Brief: Resting, Watch Schedule, and Starvation/Thirst

- Work item id: dc-cr-rest-watch-starvation
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-d
20260412-dungeoncrawler-release-d
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Resting and Daily Preparations
- Depends on: dc-cr-encounter-rules, dc-cr-conditions

## Goal

Implement the rest, watch rotation, and starvation/thirst subsystem — recovering HP and spell slots on 8-hour rest, tracking days without food/water with stacking Enfeebled penalties, and managing watch assignments for encounter interruption during rest.

## Source reference

> "After you rest for 8 hours, you recover Hit Points equal to your Constitution modifier × your level (minimum 1 per level), recover 1 Focus Point, and regain your spell slots."

## Implementation hint

`RestService.executeRest(party)` runs when GM advances to rest phase: for each character compute HP recovery as `max(1, con_mod) × level`, set Focus Points to max(current+1, focus_pool_max), and reset spell slots. Watch rotation is stored as a `WatchAssignment` entity with character assignments per watch period; if an encounter is triggered during rest, pause rest for assigned watchers. Starvation tracks `days_without_food` and `days_without_water` as integers on the character; apply `Enfeebled N` (stacking) after threshold days (1 without water, 3 without food) using `ConditionManager.applyOrWorsen(Enfeebled)`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Rest phase transitions GM-controlled; HP and spell slot recovery computed server-side only.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Watch assignment character IDs must be valid party members; rest duration must be ≥ 8 hours; starvation thresholds enforced server-side.
- PII/logging constraints: no PII logged; log session_id, rest_phase, hp_recovered_per_character[], watch_assignments[]; no PII logged.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2346, 2347, 2348, 2349
- See `runbooks/roadmap-audit.md` for audit process.
