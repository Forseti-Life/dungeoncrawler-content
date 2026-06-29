# Feature Brief: APG General and Skill Feats

- Work item id: dc-apg-feats
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
20260409-dungeoncrawler-release-h
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch04
- Category: feats
- Created: 2026-04-06
- DB sections: apg/ch04/General Feats Overview, apg/ch04/Non-Skill General Feats, apg/ch04/Skill Feats — Acrobatics, apg/ch04/Skill Feats — Athletics, apg/ch04/Skill Feats — Crafting, apg/ch04/Skill Feats — Deception, apg/ch04/Skill Feats — Diplomacy, apg/ch04/Skill Feats — Intimidation, apg/ch04/Skill Feats — Lore (Warfare), apg/ch04/Skill Feats — Medicine, apg/ch04/Skill Feats — Multi-Skill (Varying), apg/ch04/Skill Feats — Nature, apg/ch04/Skill Feats — Occultism, apg/ch04/Skill Feats — Performance, apg/ch04/Skill Feats — Religion, apg/ch04/Skill Feats — Society, apg/ch04/Skill Feats — Stealth, apg/ch04/Skill Feats — Survival, apg/ch04/Skill Feats — Thievery
- Depends on: dc-cr-skill-system, dc-cr-general-feats, dc-cr-skill-feats

## Goal

Extend the feat catalog with ~200 APG feats — covering APG ancestry feats, general feats, and skill feats — using the same feat schema as dc-cr-feats-ch05 and enabling APG ancestries and expanded build options for all characters.

## Source reference

> "The Advanced Player's Guide adds hundreds of new feats across all categories, including ancestry feats for the six new ancestries and new general and skill feats available to all characters."

## Implementation hint

APG feats use the same `Feat` entity schema as CRB feats; add `source_book: apg` for filtering. Import all ~200 feats via the bulk import pipeline; validate prerequisites and feat types against the `PrerequisiteValidator` before insertion. APG ancestry feats must reference valid APG ancestry IDs (from dc-apg-ancestries); validate foreign key integrity during import. After import, verify the `FeatSlotCalculator` correctly includes APG feats in the selection pool for the relevant feat slot types.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Feat catalog is admin-write only; character feat selections scoped to owning player; prerequisites enforced server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Source book field must be valid; prerequisite expressions validated against PrerequisiteValidator schema; ancestry feat references must point to valid APG ancestry entities.
- PII/logging constraints: no PII logged; log import_batch_id, feats_imported, validation_errors[]; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
