# Feature Brief: Monk Class Mechanics

- Work item id: dc-cr-class-monk
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Monk
- Depends on: dc-cr-character-class, dc-cr-focus-spells

## Goal

Implement Monk class mechanics — Powerful Fist, Flurry of Blows, Ki tradition, Stances, and Stunning Fist — so players can execute a fast unarmed martial arts playstyle with stance-based combat enhancements.

## Source reference

> "Flurry of Blows lets you make two unarmed Strikes as a single action, applying the Multiple Attack Penalty as if only one of those attacks had the penalty (−4/−8 instead of −5/−10)."

## Implementation hint

`FlurryOfBlowsAction` generates two `StrikeResolver` calls in sequence with a MAP override of -4/-8; validate that both targets use unarmed attack. Stances are mutually exclusive buff entities applied to the character; implement a `StanceManager` that clears the current stance on entering a new one. Ki spells are optional (character has `ki_tradition` flag); gate Ki Strike and Stunning Fist behind focus pool. `PowerfulFistUpgrade` upgrades unarmed damage die at specific levels (1d6→1d8→1d10); compute dynamically from character level rather than storing a separate field.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; stance changes and flurry only valid during encounter phase for that character's turn.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Stance ID must be a valid monk stance enum; strike targets must be valid encounter entities; Ki spell targets validated against spell range.
- PII/logging constraints: no PII logged; log character_id, stance_entered, action_type; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
