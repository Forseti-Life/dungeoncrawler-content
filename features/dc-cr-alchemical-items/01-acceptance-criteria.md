# Acceptance Criteria — dc-cr-alchemical-items

- Feature: Alchemical Items
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Capture the alchemical item backlog as a QA-ready contract covering bombs, elixirs, mutagens, poisons, and other consumables, including the metadata the item catalog and alchemist daily-prep flows need in order to behave consistently.

## Dependency checkpoints

- Consolidated into: dc-cr-equipment-ch06 (requirements covered in that feature's acceptance criteria)

## Happy Path

- [ ] `[NEW]` Catalog coverage includes bombs, elixirs, mutagens, poisons, and at least one non-consumable alchemical tool grouping so QA can verify the chapter scope is represented.
- [ ] `[NEW]` Each alchemical item record exposes level, price, bulk, activation cost, duration or persistence, and effect text needed by inventory and encounter rendering.
- [ ] `[NEW]` Bomb entries are marked as thrown alchemical consumables and identify the damage/effect payload the encounter resolver must apply on use.
- [ ] `[NEW]` Alchemist daily-prep / Infused Reagents flows only surface items flagged as alchemical and consumable where the rules expect that behavior.

## Edge Cases

- [ ] `[NEW]` Alchemical items remain non-magical: they do not consume invest slots and are not mislabeled as spells, runes, or other magical equipment.
- [ ] `[NEW]` Consumable quantity/use tracking removes a spent item after use while persistent catalog metadata remains intact for future crafting or prep.
- [ ] `[NEW]` Category-specific rules (for example poison delivery vs. mutagen self-use) can be validated without collapsing the categories into a single generic effect bucket.

## Failure Modes

- [ ] `[NEW]` Items missing required catalog metadata (level, activation, or effect summary) are rejected during content validation rather than silently published.
- [ ] `[NEW]` Magic-item-only behaviors such as investment or rune slots are not attached to alchemical records.

## Security acceptance criteria

- Security AC exemption: catalog/content and rules-data scope only; use existing item, inventory, and crafting surfaces without introducing new routes or novel input handling.
