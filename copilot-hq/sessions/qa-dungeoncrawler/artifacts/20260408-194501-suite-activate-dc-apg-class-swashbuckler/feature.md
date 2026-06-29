# Feature Brief: Swashbuckler Class Mechanics (APG)

- Work item id: dc-apg-class-swashbuckler
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Swashbuckler)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Swashbuckler
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-encounter-rules

## Goal

Implement Swashbuckler class mechanics — Panache condition, Style-specific Panache gain triggers, Finisher attacks that consume Panache for bonus damage, and Precise Strike scaling — enabling a high-risk, high-reward flair-based combat playstyle.

## Source reference

> "Panache is a condition gained by performing stylish deeds; when you have Panache, you can use a Finisher to deal extra damage and trigger special effects, but you lose the Panache condition."

## Implementation hint

`Panache` is a boolean condition on the character managed by `PanacheConditionManager`; gained by Style-specific trigger actions and consumed on any Finisher attack. Style (Battledancer/Braggart/Fencer/Gymnast/Wit) is a required selection determining which actions grant Panache (e.g., Tumble Through for Gymnast, Bon Mot for Wit). `FinisherAction` wraps any Strike: validate Panache is active, execute the strike with `PreciseStrike` damage (+1d6 per 5 levels), apply Finisher-specific bonus effect, then consume Panache. `OpportuneRiposte` triggers as a reaction when an enemy critically fails a Strike against the character; implement via the reaction hook system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Panache state managed server-side; Finisher only executable when Panache is true (server-validated).
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Style enum restricted to [Battledancer, Braggart, Fencer, Gymnast, Wit]; Finisher target must be a valid encounter entity; Panache gain triggers validated against Style selection server-side.
- PII/logging constraints: no PII logged; log character_id, panache_gained_by, finisher_target_id, precise_strike_damage; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
