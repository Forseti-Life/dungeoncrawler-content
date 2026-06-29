# Feature Brief: APG Ancestries and Versatile Heritages

- Work item id: dc-apg-ancestries
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
20260408-dungeoncrawler-release-h
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 1
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch01/Additional Ancestry Options (Existing Ancestries), apg/ch01/Ancestries Overview, apg/ch01/Backgrounds, apg/ch01/Catfolk (Amurrun), apg/ch01/Kobold, apg/ch01/Orc, apg/ch01/Ratfolk (Ysoki), apg/ch01/Tengu, apg/ch01/Versatile Heritages
- Depends on: dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-character-creation

## Goal

Add the 6 APG ancestries — Catfolk, Kobold, Leshy, Orc, Ratfolk, Tengu — to the character creation system with full heritage options, ancestry feat trees, ability boosts/flaws, and language selections matching the existing dc-cr ancestry schema.

## Source reference

> "The Advanced Player's Guide introduces six new ancestries, each with unique ability boosts, heritage options, and ancestry feats that expand character creation options."

## Implementation hint

Each APG ancestry uses the same `AncestryEntity` schema as CRB ancestries: hp, size, speed, ability_boosts[], ability_flaws[], languages[], traits[], heritages[], ancestry_feats[]. Implement a bulk import for all 6 ancestries from structured JSON; validate against the existing ancestry schema before import. Heritage options (5+ per ancestry) are child entities with additional traits or abilities; ancestry feats (levels 1–17) extend the `FeatCatalog` with ancestry-scoped entries. Add APG ancestry options to the character creation ancestry selector without disrupting existing CRB ancestry selection.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; ancestry selection immutable post character creation; ancestry feat selections validated against character ancestry.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Ancestry ID must reference a valid ancestry entity; heritage selection must belong to the chosen ancestry; ability boost/flaw choices validated against ancestry's designated boosts.
- PII/logging constraints: no PII logged; log character_id, ancestry_id, heritage_id, feats_selected[]; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
