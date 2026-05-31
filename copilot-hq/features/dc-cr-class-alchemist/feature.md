# Feature Brief: Alchemist Class Mechanics

- Work item id: dc-cr-class-alchemist
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260409-dungeoncrawler-release-f
20260409-dungeoncrawler-release-f
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Alchemist
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-equipment-system

## Goal

Implement Alchemist class mechanics — Infused Reagents, Advanced Alchemy, Quick Alchemy, Formula Book, and Research Fields — so players can craft and deploy alchemical items as a core class identity.

## Source reference

> "Alchemists are defined by their research field and the reagents they infuse each day; with Advanced Alchemy during daily preparations, they craft a number of alchemical items equal to their level plus their Intelligence modifier."

## Implementation hint

Model `infused_reagents_pool` as a per-day integer on the character record (computed from level + Int mod). `AdvancedAlchemyService` consumes reagents during daily prep to create `AlchemicalItem` entities tagged as infused (expire on next daily prep). `QuickAlchemyAction` deducts 1 reagent and creates a single infused item as a 1-action activity in encounter phase. The Formula Book is a `FormulaCollection` entity linking character to known item formulas; validate that Quick Alchemy targets only known formulas.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; only the owning player or GM may modify reagent pool and formula book entries.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Reagent spend must be a positive integer ≤ current pool; formula IDs validated against known formula list; Research Field enum restricted to [Bomber, Chirurgeon, Mutagenist].
- PII/logging constraints: no PII logged; log character_id, action_type, reagents_spent; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
