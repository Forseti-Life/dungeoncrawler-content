# Feature Brief: Treasure by Level Table

- Work item id: dc-cr-treasure-by-level
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release:
20260412-dungeoncrawler-release-e
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Treasure
- Depends on: dc-cr-economy, dc-cr-equipment-ch06

## Goal

Implement the treasure-by-level system — per-encounter GP budgets, permanent/consumable item distribution tables by party level, and a GM loot assignment tool — enabling level-appropriate reward distribution integrated with the encounter builder.

## Source reference

> "Treasure by level provides a guideline for how much treasure to award per level; a party of 4 should receive a total of permanent items and consumables roughly equal to the values in the Treasure by Level table."

## Implementation hint

Implement `TreasureByLevelTable` as a static lookup (party_level → {total_gp, permanent_items_by_level[], consumable_items_by_level[]}) derived from CRB Table 10-9. `EncounterTreasureBudget` computes per-encounter share: total_level_budget / estimated_encounters_per_level (default 8). The GM loot tool presents a `LootAssignmentForm` with suggested item levels; GM selects actual items from the catalog or enters custom GP value. On commit, `LootDistributionService` adds items/currency to character inventories proportionally or as a party pool.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-only write for loot assignment; players get read access to their own inventory additions; party pool requires GM approval before distribution.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Party level must be 1–20; item levels in loot assignment validated against item catalog; GP values must be positive integers.
- PII/logging constraints: no PII logged; log gm_id, encounter_id, loot_items[], gp_awarded, distribution_method; no PII logged.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2340, 2341, 2342, 2343, 2344, 2345
- See `runbooks/roadmap-audit.md` for audit process.
