# Feature Brief: Crafting Skill Actions

- Work item id: dc-cr-skills-crafting-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Crafting (Int)
- Depends on: dc-cr-skill-system, dc-cr-equipment-ch06, dc-cr-economy

## Goal

Implement Crafting (Int) skill action handlers — Craft (downtime), Identify Magic, Recall Knowledge, and Repair — as the primary item creation and maintenance mechanic including multi-day downtime resolution.

## Source reference

> "Craft: You can create an item from raw materials. You must have the formula for the item, access to the appropriate tools, and spend 4 days of downtime; after that initial period you can attempt a Crafting check against the item's DC."

## Implementation hint

`CraftAction` is a multi-phase downtime activity: validate formula ownership and material cost, deduct half item price in materials upfront, run daily downtime ticks, resolve the Crafting check on day 4, and compute final material savings (or extra cost on failure). Store in-progress craft jobs as `CraftingJob` entities with status (in_progress/complete/failed) queryable by the character. `RepairAction` is a 1-hour exploration activity; roll Crafting vs item-level DC, healing item HP by degree of success. `IdentifyMagicCrafting` follows the same pattern as other Identify Magic variants using the Crafting modifier.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; material deduction and gold savings computed server-side; formula ownership validated before craft begins.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Item ID must reference a valid craftable item with an existing formula; downtime days must be positive integers; material cost validated against character wealth.
- PII/logging constraints: no PII logged; log character_id, item_id, days_spent, outcome, gp_spent; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1644 (partial), 1645–1656 (Craft/Repair/Identify Alchemy actions), 2325
- See `runbooks/roadmap-audit.md` for audit process.
