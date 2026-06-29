# Feature Brief: APG Core Class Expansions

- Work item id: dc-apg-class-expansions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Core Class Expansions)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Core Class Expansions
- Depends on: dc-cr-class-alchemist, dc-cr-class-barbarian, dc-cr-class-bard, dc-cr-class-champion, dc-cr-class-cleric, dc-cr-class-druid, dc-cr-class-fighter, dc-cr-class-monk, dc-cr-class-ranger, dc-cr-class-rogue, dc-cr-class-sorcerer, dc-cr-class-wizard

## Goal

Extend existing CRB class subclass enums with APG additions — new Barbarian Instincts (Dragon/Fury/Spirit/Superstition), new Ranger Hunter's Edges, new Alchemist Research Fields, and other class expansions — enabling richer build diversity for existing classes.

## Source reference

> "The Advanced Player's Guide expands existing classes with new subclass options; Barbarians gain new instincts, Rangers new hunter's edges, and Alchemists new research fields."

## Implementation hint

For each CRB class with APG subclass expansions, extend the relevant enum: `BarbarianInstinct` gets (Dragon/Fury/Spirit/Superstition), `HunterEdge` gets new entries, `AlchemistResearchField` gets new entries. Each new subclass entry needs its own feature object following the same pattern as existing subclasses (e.g., `DragonInstinct` has a `dragon_type` sub-selection and specific `RageEffect`). Add the new subclass options to character creation selectors via the existing subclass selection flow. Validate that prerequisite feats and abilities for new subclasses don't conflict with existing entries.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; subclass selection immutable post creation; new subclass options validated against the extended enum server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Instinct/edge/field enum values restricted to valid extended lists; Dragon Instinct requires a dragon type sub-selection from a valid dragon type list; all new subclass abilities level-gated server-side.
- PII/logging constraints: no PII logged; log character_id, class_type, subclass_selected, expansion_source; no PII logged.

## Roadmap section
- Book: apg, Chapter: ch02
- REQs: 191–221 (31 REQs, Core Class Expansions section)
- See `runbooks/roadmap-audit.md` for audit process.
