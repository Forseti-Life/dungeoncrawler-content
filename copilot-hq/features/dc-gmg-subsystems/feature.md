# Feature Brief: GMG Chapter 4 — Subsystems

- Work item id: dc-gmg-subsystems
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-m
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch03-ch04
- Category: gm-tools
- Created: 2026-04-07
- DB sections: gmg/ch03/Baseline Requirements, gmg/ch03/Integration Notes, gmg/ch04/Baseline Requirements, gmg/ch04/Integration Notes
- Depends on: dc-cr-encounter-rules, dc-cr-exploration-mode, dc-cr-downtime-mode

## Goal

Implement the five GMG structured subsystems — Chases, Influence, Research, Infiltration, and Reputation — as modular mini-game engines with their own initiative, turn structure, and win/fail conditions, enabling complex non-combat scenarios.

## Source reference

> "Subsystems are structured systems for non-combat challenges; Chases use an obstacle track, Influence uses an NPC influence pool, Research uses a knowledge accumulation clock, and Infiltration uses security points."

## Implementation hint

Define a `SubsystemSession` entity with a `subsystem_type` enum (chase/influence/research/infiltration/reputation) and a generic `progress_state` JSON field storing the current track/pool/threshold values. Each subsystem type has its own `SubsystemEngine` class implementing a shared `ISubsystemEngine` interface with methods: `initiate()`, `takeTurn(character, action)`, `checkWinCondition()`, `checkFailCondition()`. Chase uses an `ObstacleTrack` with obstacles as ordered entities; Influence tracks `npc_influence_pools`; Research uses a `ClueReveal` accumulator; Infiltration uses a `SecurityPoints` threshold. All subsystems integrate with the initiative tracker for turn order.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-scoped write for subsystem initiation; player actions within subsystems scoped to their character turn.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Subsystem type must be a valid enum; action choices validated against available subsystem actions for the current phase; progress state mutated server-side only.
- PII/logging constraints: no PII logged; log gm_id, session_id, subsystem_type, character_id, action_taken, progress_state_delta; no PII logged.

## Roadmap section
- Book: gmg, Chapter: ch04
- REQs: 2732, 2733, 2734, 2735, 2736, 2737
- See `runbooks/roadmap-audit.md` for audit process.
