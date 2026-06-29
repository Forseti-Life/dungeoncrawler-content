# Acceptance Criteria — dc-cr-magic-items

- Feature: Magic Items and Treasure
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Capture the magic-item backlog as a QA-ready contract covering the item catalog, activation/usage metadata, and the invested-item limit so inventory and encounter systems have a concrete rules target.

## Dependency checkpoints

- Consolidated into: dc-cr-magic-ch11 (requirements covered in that feature's acceptance criteria)

## Happy Path

- [ ] `[NEW]` The catalog covers weapons, armor, wondrous items, and other held/worn item types needed by chapter 11 scope.
- [ ] `[NEW]` Each magic item includes level, price, activation method, and usage state such as held, worn, or invested.
- [ ] `[NEW]` Characters can equip and track invested items, with a hard cap of 10 invested items at one time.
- [ ] `[NEW]` Inventory/equipment flows can differentiate held, worn, and invested behaviors when presenting item actions and restrictions.

## Edge Cases

- [ ] `[NEW]` Items that are worn or held but not invested do not consume one of the 10 investment slots.
- [ ] `[NEW]` Activation types such as command word, Cast a Spell, and Interact remain distinguishable in the catalog and UI contract.
- [ ] `[NEW]` Characters unequipping or uninvesting an item immediately free the consumed invest slot for another item.

## Failure Modes

- [ ] `[NEW]` Attempting to invest an eleventh item fails with a validation error rather than silently exceeding the cap.
- [ ] `[NEW]` Catalog entries missing required activation or usage metadata are rejected during validation.

## Security acceptance criteria

- Security AC exemption: catalog, inventory, and equipment-rule scope only; use existing item management surfaces without introducing new routes or novel input handling.
