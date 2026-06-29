# Test Plan: dc-cr-alchemical-items

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-ALC-01-05)
- Suites: playwright (inventory, encounter, daily prep)
- Security: Security AC exemption: catalog/content and rules-data scope only; use existing item, inventory, and crafting surfaces without introducing new routes or novel input handling.

---

## TC-ALC-01 — Catalog scope and availability
- Description: Catalog coverage includes bombs, elixirs, mutagens, poisons, and at least one non-consumable alchemical tool grouping so QA can verify the chapter scope is represented.
- Suite: playwright/inventory
- Expected: Catalog coverage includes bombs, elixirs, mutagens, poisons, and at least one non-consumable alchemical tool grouping so QA can verify the chapter scope is represented.
- AC: Happy Path-1

## TC-ALC-02 — Required metadata and primary rule data
- Description: Each alchemical item record exposes level, price, bulk, activation cost, duration or persistence, and effect text needed by inventory and encounter rendering.
- Suite: playwright/inventory
- Expected: Each alchemical item record exposes level, price, bulk, activation cost, duration or persistence, and effect text needed by inventory and encounter rendering.; Bomb entries are marked as thrown alchemical consumables and identify the damage/effect payload the encounter resolver must apply on use.
- AC: Happy Path-2, Happy Path-3

## TC-ALC-03 — Runtime item state and downstream flow
- Description: Bomb entries are marked as thrown alchemical consumables and identify the damage/effect payload the encounter resolver must apply on use.
- Suite: playwright/encounter
- Expected: Bomb entries are marked as thrown alchemical consumables and identify the damage/effect payload the encounter resolver must apply on use.; Alchemist daily-prep / Infused Reagents flows only surface items flagged as alchemical and consumable where the rules expect that behavior.
- AC: Happy Path-3, Happy Path-4

## TC-ALC-04 — Edge-case category handling
- Description: Alchemical items remain non-magical: they do not consume invest slots and are not mislabeled as spells, runes, or other magical equipment.
- Suite: playwright/inventory
- Expected: Alchemical items remain non-magical: they do not consume invest slots and are not mislabeled as spells, runes, or other magical equipment.; Consumable quantity/use tracking removes a spent item after use while persistent catalog metadata remains intact for future crafting or prep.; Category-specific rules (for example poison delivery vs. mutagen self-use) can be validated without collapsing the categories into a single generic effect bucket.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-ALC-05 — Validation safeguards and invalid metadata handling
- Description: Items missing required catalog metadata (level, activation, or effect summary) are rejected during content validation rather than silently published.
- Suite: playwright/inventory
- Expected: Items missing required catalog metadata (level, activation, or effect summary) are rejected during content validation rather than silently published.; Magic-item-only behaviors such as investment or rune slots are not attached to alchemical records.
- AC: Failure Modes-1, Failure Modes-2
