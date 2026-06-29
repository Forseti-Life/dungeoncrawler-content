# Feature Brief: Diplomacy and Intimidation Skill Actions

- Work item id: dc-cr-skills-diplomacy-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Diplomacy (Cha), core/ch04/Intimidation (Cha)
- Depends on: dc-cr-skill-system, dc-cr-gm-narrative-engine

## Goal

Implement all Diplomacy (Cha) skill action handlers — Gather Information, Make an Impression, Request — with NPC attitude state machine (Hostile/Unfriendly/Indifferent/Friendly/Helpful) and appropriate downtime/encounter phase gating.

## Source reference

> "Make an Impression: With at least 1 minute of conversation, you attempt to make a good impression on someone. Attempt a Diplomacy check against the target's Will DC; on a success, the target's attitude toward you improves by one step."

## Implementation hint

NPC attitude is a 5-step enum stored on the `NpcRelationship` entity keyed by (character_id, npc_id); `MakeAnImpressionAction` is a 1-minute activity that rolls Diplomacy vs Will DC and calls `AttitudeStateMachine.advance()` or `retreat()`. `GatherInformationAction` is a 2-hour downtime activity; resolve as Diplomacy vs settlement DC and return a structured `InformationReveal` result. `RequestAction` requires Friendly or Helpful attitude as a prerequisite; validate attitude tier before roll resolution. All three actions are gated to social/downtime/exploration phase only.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; NPC attitude state changes are GM-overridable; attitude updates broadcast to GM view.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: NPC ID must reference a valid NPC entity; attitude tier changes clamped to valid enum range; downtime duration must be ≥ minimum for the action.
- PII/logging constraints: no PII logged; log character_id, npc_id, action_type, attitude_change; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1669, 1670, 1671, 1672, 1673, 1674, 1675, 1676, 1677,
         1678, 1679, 1680, 1681, 1682, 1683, 2327, 2330
- See `runbooks/roadmap-audit.md` for audit process.
