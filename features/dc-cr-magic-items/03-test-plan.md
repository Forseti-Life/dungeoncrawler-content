# Test Plan: dc-cr-magic-items

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-MIT-01-05)
- Suites: playwright (inventory, equipment, investment limits)
- Security: Security AC exemption: catalog, inventory, and equipment-rule scope only; use existing item management surfaces without introducing new routes or novel input handling.

---

## TC-MIT-01 — Catalog scope and availability
- Description: The catalog covers weapons, armor, wondrous items, and other held/worn item types needed by chapter 11 scope.
- Suite: playwright/inventory
- Expected: The catalog covers weapons, armor, wondrous items, and other held/worn item types needed by chapter 11 scope.
- AC: Happy Path-1

## TC-MIT-02 — Required metadata and primary rule data
- Description: Each magic item includes level, price, activation method, and usage state such as held, worn, or invested.
- Suite: playwright/inventory
- Expected: Each magic item includes level, price, activation method, and usage state such as held, worn, or invested.; Characters can equip and track invested items, with a hard cap of 10 invested items at one time.
- AC: Happy Path-2, Happy Path-3

## TC-MIT-03 — Runtime item state and downstream flow
- Description: Characters can equip and track invested items, with a hard cap of 10 invested items at one time.
- Suite: playwright/inventory
- Expected: Characters can equip and track invested items, with a hard cap of 10 invested items at one time.; Inventory/equipment flows can differentiate held, worn, and invested behaviors when presenting item actions and restrictions.
- AC: Happy Path-3, Happy Path-4

## TC-MIT-04 — Edge-case category handling
- Description: Items that are worn or held but not invested do not consume one of the 10 investment slots.
- Suite: playwright/inventory
- Expected: Items that are worn or held but not invested do not consume one of the 10 investment slots.; Activation types such as command word, Cast a Spell, and Interact remain distinguishable in the catalog and UI contract.; Characters unequipping or uninvesting an item immediately free the consumed invest slot for another item.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-MIT-05 — Validation safeguards and invalid metadata handling
- Description: Attempting to invest an eleventh item fails with a validation error rather than silently exceeding the cap.
- Suite: playwright/inventory
- Expected: Attempting to invest an eleventh item fails with a validation error rather than silently exceeding the cap.; Catalog entries missing required activation or usage metadata are rejected during validation.
- AC: Failure Modes-1, Failure Modes-2
