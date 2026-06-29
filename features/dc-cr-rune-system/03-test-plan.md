# Test Plan: dc-cr-rune-system

## Coverage summary
- AC items: 21 (17 happy path, 2 edge cases, 2 failure modes)
- Test cases: 22 (TC-RUN-01–22)
- Suites: playwright (inventory/crafting/character creation)
- Security: AC exemption granted (no new routes)
- Note: These tests EXTEND dc-cr-magic-ch11 coverage; rune system is a standalone subsystem feature.

---

## TC-RUN-01 — Weapon potency runes: attack bonus and slot unlock
- Description: +1/+2/+3 weapon potency runes each grant attack bonus and unlock property rune slots equal to potency value
- Suite: playwright/inventory
- Expected: potency=1 → attack_bonus +1, property_slots = 1; potency=2 → +2, slots = 2; potency=3 → +3, slots = 3
- AC: Fundamental-1

## TC-RUN-02 — Striking runes: damage dice count
- Description: Striking → 2d, Greater Striking → 3d, Major Striking → 4d (of weapon's damage die)
- Suite: playwright/inventory
- Expected: striking=Striking: damage_dice = 2; Greater: 3; Major: 4
- AC: Fundamental-2

## TC-RUN-03 — Armor potency runes: AC bonus and slot unlock
- Description: +1/+2/+3 armor potency runes grant item bonus to AC and unlock property rune slots
- Suite: playwright/inventory
- Expected: armor potency=1 → AC_item_bonus +1, property_slots = 1; potency=2 → +2, slots = 2; potency=3 → +3, slots = 3
- AC: Fundamental-3

## TC-RUN-04 — Resilient runes: save bonuses
- Description: Resilient → +1 saves, Greater Resilient → +2, Major Resilient → +3 (item bonus)
- Suite: playwright/inventory
- Expected: resilient=Resilient: all_saves_item_bonus = +1; Greater: +2; Major: +3
- AC: Fundamental-4

## TC-RUN-05 — Property rune slots require potency rune
- Description: Without a potency rune, zero property rune slots available
- Suite: playwright/inventory
- Expected: weapon.potency_rune = none → property_slots = 0; etching property rune blocked
- AC: Property-1, Edge-1

## TC-RUN-06 — Duplicate property runes: higher-level wins
- Description: Two identical property runes on same item — only higher-level applies
- Suite: playwright/inventory
- Expected: duplicate_property_rune → item uses the higher-level version; lower is dormant
- AC: Property-2

## TC-RUN-07 — Energy-resistant runes stack by damage type
- Description: Multiple energy-resistant property runes apply if each covers a different energy type
- Suite: playwright/inventory
- Expected: fire_resistance + cold_resistance both active simultaneously; same-type duplicate follows duplicate rule
- AC: Property-2

## TC-RUN-08 — Orphaned property rune goes dormant
- Description: Removing potency rune makes property runes dormant; reactivates when compatible potency restored
- Suite: playwright/inventory
- Expected: remove potency → property_rune.state = dormant; re-equip potency → property_rune.state = active
- AC: Property-3, Failure-2

## TC-RUN-09 — Etch a Rune: requirements gate
- Description: Requires Magical Crafting feat, formula, item in possession; one rune at a time
- Suite: playwright/crafting
- Expected: attempt without Magical Crafting feat → blocked; without formula → blocked; with all → starts craft activity
- AC: Etching-1

## TC-RUN-10 — Etch a Rune: downtime activity
- Description: Etch a Rune is a downtime Craft activity
- Suite: playwright/crafting
- Expected: etch action phase = downtime; cannot be triggered in encounter or exploration
- AC: Etching-1

## TC-RUN-11 — Transfer Rune: DC, cost, and duration
- Description: Transfer Rune DC = rune level DC; cost = 10% of rune price; minimum 1 day
- Suite: playwright/crafting
- Expected: transfer.DC = rune_level_dc; transfer.cost = rune.price × 0.10; transfer.min_duration = 1 day
- AC: Etching-2

## TC-RUN-12 — Transfer from runestone: free
- Description: Transferring a rune from a runestone has no cost
- Suite: playwright/crafting
- Expected: transfer.source = runestone → cost = 0
- AC: Etching-3

## TC-RUN-13 — Incompatible rune transfer: auto-crit fail
- Description: Attempting to transfer incompatible rune results in automatic critical failure and no cost charged
- Suite: playwright/crafting
- Expected: incompatible transfer → result = critical_failure; cost not deducted
- AC: Etching-4, Failure-1

## TC-RUN-14 — Same-category swaps only
- Description: Fundamental runes can only swap with fundamental; property with property
- Suite: playwright/crafting
- Expected: fundamental-to-property swap attempt → blocked (incompatible category)
- AC: Etching-5

## TC-RUN-15 — Item upgrade Crafting cost formula
- Description: Upgrade cost = new rune price minus existing rune price; DC uses new rune level
- Suite: playwright/crafting
- Expected: upgrade_cost = new_rune.price − old_rune.price; craft_dc = new_rune_level_dc
- AC: Etching-6

## TC-RUN-16 — One precious material per item
- Description: Items can have at most one precious material
- Suite: playwright/inventory
- Expected: attempt to add second material to item → blocked
- AC: Materials-1

## TC-RUN-17 — Material grade requirements: Low/Standard/High
- Description: Low = Expert Crafting, max level 8; Standard = Master Crafting, max level 15; High = Legendary Crafting
- Suite: playwright/crafting
- Expected: Low grade blocked if Crafting < Expert or item.level > 8; Standard blocked if < Master or level > 15; High blocked if < Legendary
- AC: Materials-2

## TC-RUN-18 — Material investment percentages
- Description: Low = 10% of initial cost, Standard = 25%, High = 100%
- Suite: playwright/crafting
- Expected: material_investment_cost = base_item_cost × grade_percentage
- AC: Materials-3

## TC-RUN-19 — Material Hardness/HP/BT values
- Description: All listed materials (Adamantine, Cold Iron, Darkwood, Dragonhide, Mithral, Orichalcum, Silver) have correct H/HP/BT per grade
- Suite: playwright/inventory
- Expected: each material × each grade combination returns correct hardness, hp, bt values per table
- AC: Materials-4

## TC-RUN-20 — Special material properties
- Description: Cold iron, adamantine, darkwood, dragonhide, mithral, orichalcum, silver implement their special properties
- Suite: playwright/encounter
- Expected: each material's special property triggers correctly (e.g., adamantine ignores hardness of objects; cold iron disrupts fey regeneration)
- AC: Materials-5

## TC-RUN-21 — Specific locked magic items: no property rune slots
- Description: Items with 0 locked property slots only allow fundamental runes
- Suite: playwright/inventory
- Expected: locked_item.property_slots = 0; only potency and striking/resilient runes can be etched
- AC: Edge-2

## TC-RUN-22 — Orichalcum: fixed 4 property rune slots (material override)
- Description: Orichalcum overrides normal property slot formula; always grants 4 property rune slots regardless of potency value
- Suite: playwright/inventory
- Expected: orichalcum_weapon.property_slots = 4 (not equal to potency rune value)
- Dev NOTE: This is a material-level override, not a potency rune formula result. Dev must implement Orichalcum as a special case that bypasses the slots = potency_value formula. See also TC-MCH-36 in dc-cr-magic-ch11.
- AC: Materials-4 (Orichalcum special case)
