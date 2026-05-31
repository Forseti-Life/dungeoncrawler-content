# Feature Brief: Encounter and Combat Rules

- Work item id: dc-cr-encounter-rules
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P1 (primary gameplay loop; depends on dice and DC)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26
- DB sections: core/ch09/Attack Rolls

## Goal

Implement the full encounter/combat loop: initiative rolling (Perception check), turn order, attack rolls with multiple attack penalty (MAP), hit/miss/critical determination (20+ over DC = crit success, 10+ under DC = crit fail), damage application, and end-of-turn conditions processing. Combat is the primary gameplay loop for dungeoncrawler sessions.

## Source reference

> "This includes rules on encounters, exploration, and downtime, as well as everything you need for combat." (Chapter 9: Playing the Game)

## Implementation hint

Encounter engine: maintains sorted initiative order, tracks HP for all combatants, applies damage and conditions per rules. Attack roll = d20 + attack bonus vs. target AC; result determines degree of success. MAP: −5 on second attack, −10 on third (or −4/−8 with agile trait). Condition system must be integrated (flat-footed, grabbed, dying, etc.).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only for encounter creation/mutation; `_character_access: TRUE` on character-scoped encounter routes
- CSRF expectations: all POST/PATCH encounter routes require `_csrf_request_header_mode: TRUE`
- Input validation: encounter parameters (initiative, actions) validated against PF2E rule constraints; no free-text user input without sanitization
- PII/logging constraints: no PII logged; encounter id + character id + action type only
