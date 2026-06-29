# Feature Brief: Core Book Chapter 5 — Feats Overview and Key Mechanics

- Work item id: dc-cr-feats-ch05
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch05
- Category: feats
- Created: 2026-04-07
- DB sections: core/ch05/Chapter Overview, core/ch05/Key Feat Mechanic Notes, core/ch05/Non-Skill General Feats Table
- Depends on: dc-cr-general-feats, dc-cr-skill-feats, dc-cr-character-leveling

## Goal

Implement the feat catalog and character feat slot system — covering Ancestry, General, Skill, and Class feats — with prerequisite validation, feat type assignment, and level-gated slot schedules for all feat categories.

## Source reference

> "Characters receive General feats at levels 3, 7, 11, 15, and 19; Skill feats at every even level starting at 2; and Ancestry feats at levels 1, 5, 9, 13, and 17."

## Implementation hint

Define a `Feat` entity with fields: id, name, feat_type (ancestry/class/general/skill/archetype), level, prerequisites[], traits[], source_book, effect_text. Store prerequisites as a JSON array of prerequisite expressions (e.g., `[{"skill": "Athletics", "rank": "trained"}]`); implement a `PrerequisiteValidator` that evaluates these against the current character state. Character feat slots are computed dynamically from level using the feat schedule tables (not stored as entities); a `FeatSlotCalculator` returns available slots by type and level. Implement a bulk import for ~400+ CRB feats.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; feat selection validated server-side against prerequisites and feat type eligibility.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Feat ID must reference a valid feat entity; feat type must match the slot type being filled; all prerequisite conditions validated server-side.
- PII/logging constraints: no PII logged; log character_id, feat_id, feat_type, character_level; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
