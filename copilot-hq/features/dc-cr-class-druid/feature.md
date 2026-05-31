# Feature Brief: Druid Class Mechanics

- Work item id: dc-cr-class-druid
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260409-dungeoncrawler-release-f
20260409-dungeoncrawler-release-f
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Druid
- Depends on: dc-cr-character-class, dc-cr-spellcasting, dc-cr-animal-companion

## Goal

Implement Druid class mechanics — Order (Animal/Leaf/Storm/Wild), primal prepared spellcasting, Wild Shape, Order Spells, Anathema, and Wild Empathy — so players can express nature-themed martial and magical roles gated by druidic code.

## Source reference

> "A druid's order determines their order spell and the focus spells they gain; Wild Druids gain Wild Shape at 1st level, allowing them to cast that spell once per hour without expending a spell slot."

## Implementation hint

`DruidOrder` is a required enum (Animal/Leaf/Storm/Wild) stored on the Druid entity; each order loads a specific set of order spells into the focus pool and may modify class feature availability (Wild gets Wild Shape at level 1). `WildShapeAction` casts the Polymorph spell variant consuming 1 Focus Point; the available Wild Shape forms expand with level. Anathema violations (e.g., using metal armor for leaf order) should be flagged at the gear-equip validation layer. `WildEmpathy` unlocks a free Diplomacy-equivalent check against animals and plant creatures using the Nature modifier.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Order selection immutable post creation; Wild Shape form selection validated against unlocked forms by level.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Order enum restricted to [Animal, Leaf, Storm, Wild]; Wild Shape form IDs validated against level-gated form list; anathema rules enforced server-side.
- PII/logging constraints: no PII logged; log character_id, order_type, wild_shape_form; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
