# Test Plan: dc-gmg-npc-gallery

## Coverage summary
- AC items: ~17 (prebuilt stat blocks, Elite/Weak templates, GM usage, integration, edge cases)
- Test cases: 9 (TC-NPC-01–09)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-NPC-01 — NPC Gallery: prebuilt entries with level range and archetype tag
- Description: Gallery includes Guard, Soldier, Bandit, Merchant, Assassin, Informant, Noble, Cultist, Innkeeper, Sailor; each has a level range; tagged with "NPC" archetype classifier
- Suite: playwright/character-creation
- Expected: all 10 archetypes present; each has level_range field; NPC tag visible in creature selector
- AC: Gallery-1–4

## TC-NPC-02 — NPC stat blocks follow standard creature stat block schema
- Description: NPC Gallery entries use the same stat block schema as Bestiary creatures
- Suite: playwright/encounter
- Expected: NPC stat blocks render identically to bestiary entries; HP, AC, saves, actions all present
- AC: Gallery-4

## TC-NPC-03 — Elite template: +2 modifiers, +HP by tier
- Description: Elite overlay: +2 to all modifiers, attack bonus, DC, saves; +HP by level tier; base stat block unchanged
- Suite: playwright/encounter
- Expected: elite flag recalculates all modifiers +2; HP increases per tier table; base stats preserved (non-destructive overlay)
- AC: EliteWeak-1

## TC-NPC-04 — Weak template: –2 modifiers, –HP by tier
- Description: Weak overlay: –2 to all modifiers, attack bonus, DC, saves; –HP by level tier
- Suite: playwright/encounter
- Expected: weak flag recalculates all modifiers –2; HP decreases per tier table
- AC: EliteWeak-2

## TC-NPC-05 — Elite/Weak overlay is non-destructive
- Description: Applying Elite/Weak does not alter the base stat block; removing overlay restores original values
- Suite: playwright/encounter
- Expected: toggle Elite on → modified stats; toggle off → original stats restored exactly
- AC: EliteWeak-3

## TC-NPC-06 — GM scene setup: NPC selection and rename
- Description: GM can select Gallery NPC from creature selector during scene setup; rename NPC without altering mechanical stats; rename persists in session log
- Suite: playwright/encounter
- Expected: creature selector shows NPC Gallery entries with NPC filter; custom name saves; mechanical stats unchanged
- AC: GMUsage-1–3

## TC-NPC-07 — NPC HP/condition/action management identical to standard creatures
- Description: HP tracking, condition tracking, and action management function identically for NPC Gallery entries
- Suite: playwright/encounter
- Expected: NPC HP bar, conditions, and actions work the same as bestiary creatures
- AC: GMUsage-4

## TC-NPC-08 — NPC Gallery searchable by level, archetype tag, alignment
- Description: Creature selector filters NPC Gallery by level range, archetype tag, and alignment
- Suite: playwright/encounter
- Expected: filter by "NPC" tag returns only Gallery entries; level range filter; alignment filter all function
- AC: Integration-4

## TC-NPC-09 — Edge: Elite + Weak mutually exclusive; rename identity persists
- Description: Applying both Elite and Weak simultaneously is disallowed; renamed NPC identity preserved in encounter history
- Suite: playwright/encounter
- Expected: applying second template when first active → blocked with error; custom name stored in encounter log with stat block identity
- AC: Edge-1–2
