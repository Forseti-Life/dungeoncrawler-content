# Feature Brief: Deception Skill Actions

- Work item id: dc-cr-skills-deception-actions
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
- DB sections: core/ch04/Deception (Cha)
- Depends on: dc-cr-skill-system, dc-cr-conditions

## Goal

Implement all Deception (Cha) skill action handlers — Create a Diversion, Feint, Impersonate, Lie — with opposed-check resolution against target Perception/Sense Motive and appropriate duration and condition tracking.

## Source reference

> "Feint: With a gesture, a trick, or a ruse, you throw your opponent off guard. Make a Deception check against your target's Perception DC. On a success, the target is flat-footed against your melee attacks until the end of your current turn."

## Implementation hint

`FeintAction` resolves as a Deception check vs the target's Perception DC; apply `flat-footed` condition to the target scoped to the current attacker's turn end. `CreateADiversionAction` uses Deception vs Perception DC of all observers; on success sets the caster as `Hidden` (transition from Observed to Hidden state in the visibility system). `ImpersonateAction` is an exploration/downtime activity; store an `impersonation_subject_id` and duration flag on the character. `LieAction` resolves Deception vs Perception DC; outcome determines NPC belief state update.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; condition application (flat-footed, hidden) must be server-authoritative.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Feint target must be a valid encounter entity within melee reach; Impersonate subject must be a valid character/NPC reference; Lie narrative text sanitized with HTML strip.
- PII/logging constraints: no PII logged; log character_id, action_type, target_id, outcome; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1658, 1659, 1660, 1661, 1662, 1663, 1664, 1665, 1666, 1667, 1668
- See `runbooks/roadmap-audit.md` for audit process.
