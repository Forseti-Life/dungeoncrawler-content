# Feature Brief: Acrobatics Skill Actions

- Work item id: dc-cr-skills-acrobatics-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260410-dungeoncrawler-release-d
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Acrobatics (Dex)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening

## Goal

Implement all Acrobatics (Dex) skill action handlers — Balance, Tumble Through, Maneuver in Flight, Squeeze — with proper trained/untrained gating, armor check penalties, and full degree-of-success outcome logic.

## Source reference

> "Balance: You move across a narrow surface or uneven ground. The DC is set by the GM based on the surface; on a critical success you're unaffected, on a failure you fall prone and stop moving."

## Implementation hint

Route all four actions through a shared `AcrobaticsActionHandler` that injects the character's Acrobatics modifier (from `SkillsCalculatorService`) and applies `armor_check_penalty` before the roll. Each action maps to a `DegreeOfSuccessResolver` with four outcome handlers (crit success/success/failure/crit failure); define outcome enums per action in a constants file. `TumbleThrough` integrates with `AOOTriggerService`; `ManeuverInFlight` requires flight speed > 0 on the character movement record. `Squeeze` is an exploration-phase activity; flag it as incompatible with encounter-phase combat.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; skill check rolls only valid during the owning character's turn or designated exploration phase.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Action type must be a valid Acrobatics action enum; terrain DC overrides must be positive integers; character must have flight speed for ManeuverInFlight.
- PII/logging constraints: no PII logged; log character_id, action_type, dc_attempted, outcome; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1603, 1604, 1605, 1606, 1607, 1608, 1609, 1610, 1611, 1612, 1613, 1614
- See `runbooks/roadmap-audit.md` for audit process.
