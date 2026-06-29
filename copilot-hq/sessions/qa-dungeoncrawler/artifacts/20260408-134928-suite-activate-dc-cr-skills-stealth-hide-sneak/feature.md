# Feature Brief: Stealth — Hide, Sneak, Conceal Object

- Work item id: dc-cr-skills-stealth-hide-sneak
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
- DB sections: core/ch04/Stealth (Dex)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening

## Goal

Implement Stealth (Dex) skill action handlers — Hide, Sneak, Conceal an Object, and Create a Diversion integration — with full Hidden/Undetected visibility state machine and Rogue Sneak Attack trigger integration.

## Source reference

> "Hide: You attempt to hide from creatures that can see you, using a Stealth check against each opponent's Perception DC; on a success, you become Hidden to that creature until you do something to make yourself known."

## Implementation hint

The visibility state machine has states: Observed → Hidden → Undetected → Unnoticed; `HideAction` transitions Observed→Hidden vs each observer's Perception DC. `SneakAction` is a move action maintaining Hidden status; requires a Stealth check vs observer Perception DC on each move into observation range. Track visibility state per (character, observer) pair in a `VisibilityMatrix` entity updated on each Hide/Sneak resolution. Integrate with `SnackAttackTriggerService` in `dc-cr-class-rogue`: Sneak Attack fires when target is Hidden or Undetected relative to the attacker; query VisibilityMatrix before strike resolution.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; visibility state changes are server-authoritative and broadcast to GM; only GM can see true Undetected characters.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Hide/Sneak DCs computed from observer Perception + situational modifiers; armor check penalty applied from Stealth skill; observer list derived from encounter entities, not client input.
- PII/logging constraints: no PII logged; log character_id, action_type, observer_id, visibility_state; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1721, 1722, 1723, 1724, 1725, 1726, 1727, 1728, 1729, 1730
- See `runbooks/roadmap-audit.md` for audit process.
