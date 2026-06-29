# Test Plan: dc-cr-skills-crafting-actions

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Crafting skill actions — Repair, Craft (downtime), Identify Alchemy
**KB reference:** equipment-system dependency pattern follows dc-cr-class-fighter/03-test-plan.md (conditional on dc-cr-equipment-system, in-progress Release B); item HP/Hardness/broken/destroyed states require equipment system data model.
**Dependency note:** dc-cr-equipment-system (in-progress Release B) — 12 TCs conditional on equipment system for item HP, Hardness, broken/destroyed states, formula catalog, material cost tracking; 16 TCs immediately activatable at Stage 0.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Crafting action business logic: proficiency gates, tool requirements, activity types, HP restoration formulas, proficiency level caps, downtime day tracking, degree outcomes, consumable batch logic, feat gates |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing exploration/downtime handler routes only |

---

## Test Cases

### Repair

### TC-CRF-01 — Repair requires Trained Crafting
- **Suite:** module-test-suite
- **Description:** Repair is blocked for characters with Untrained Crafting.
- **Expected:** crafting_rank = untrained → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-02 — Repair requires a repair kit
- **Suite:** module-test-suite
- **Description:** Repair is blocked if the character does not have a repair kit in their inventory.
- **Expected:** repair_kit_available = false → action blocked; error returned.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (inventory/item presence check)

### TC-CRF-03 — Repair is an exploration activity (10 minutes)
- **Suite:** module-test-suite
- **Description:** Repair is classified as an exploration activity; its time cost is 10 minutes per attempt.
- **Expected:** action_type = exploration; time_cost = 10 minutes.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-04 — Repair Success: HP restoration = 5 + 5 per proficiency rank
- **Suite:** module-test-suite
- **Description:** On Success, item recovers (5 + 5 × rank) HP, where rank is: Trained=1, Expert=2, Master=3, Legendary=4.
- **Expected:** HP restored = 5 + (5 × rank); e.g., Trained = 10, Expert = 15, Master = 20, Legendary = 25.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (item HP tracking)

### TC-CRF-05 — Repair Critical Success: HP restoration = 10 + 10 per proficiency rank
- **Suite:** module-test-suite
- **Description:** On Critical Success, item recovers (10 + 10 × rank) HP.
- **Expected:** HP restored = 10 + (10 × rank); e.g., Trained = 20, Expert = 30, Master = 40, Legendary = 50.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (item HP tracking)

### TC-CRF-06 — Repair Critical Failure: 2d6 damage to item after Hardness
- **Suite:** module-test-suite
- **Description:** On Critical Failure, item takes 2d6 bludgeoning damage, reduced by the item's Hardness first.
- **Expected:** item damage = max(0, 2d6_roll - item_hardness); item_hp decremented accordingly.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (item HP and Hardness data model)

### TC-CRF-07 — Repair blocked on destroyed item
- **Suite:** module-test-suite
- **Description:** Edge case — Repair cannot be used on an item that is in the destroyed state.
- **Expected:** item_state = destroyed → action blocked; error returned; no check rolled.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (item destroyed state)

### TC-CRF-08 — Repair on item at full HP: no-op
- **Suite:** module-test-suite
- **Description:** Failure mode — Repair on an item already at maximum HP either has no effect or restores 0 HP; the action still resolves normally (no error, just no gain).
- **Expected:** item_hp = item_hp_max → repair heals 0 HP; no error; action recorded.
- **Notes to PM:** AC states "no-op or minimal effect per GM." For automation, recommend defining "no-op" as 0 HP restored and no error; confirm this is acceptable or if there is a UI-level warning.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (item HP max check)

---

### Craft

### TC-CRF-09 — Craft requires Trained Crafting
- **Suite:** module-test-suite
- **Description:** Craft is blocked for characters with Untrained Crafting.
- **Expected:** crafting_rank = untrained → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-10 — Craft is a downtime activity
- **Suite:** module-test-suite
- **Description:** Craft is classified as a downtime activity (not encounter or exploration).
- **Expected:** action_type = downtime; not available as encounter/exploration action.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-11 — Craft blocked without a formula for the target item
- **Suite:** module-test-suite
- **Description:** Failure mode — attempting to Craft an item for which the character does not have a formula is blocked before the check.
- **Expected:** formula_known_for_item = false → action blocked; error returned.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (formula catalog/ownership)

### TC-CRF-12 — Craft blocked if item level exceeds character level
- **Suite:** module-test-suite
- **Description:** Edge case — character cannot Craft an item whose level exceeds their own character level.
- **Expected:** item_level > character_level → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-13 — Craft: item level 9+ requires Master Crafting
- **Suite:** module-test-suite
- **Description:** Crafting an item of level 9 or higher requires Master proficiency in Crafting; Trained or Expert is insufficient.
- **Expected:** item_level >= 9 AND crafting_rank < master → action blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-14 — Craft: item level 16+ requires Legendary Crafting
- **Suite:** module-test-suite
- **Description:** Crafting an item of level 16 or higher requires Legendary proficiency in Crafting.
- **Expected:** item_level >= 16 AND crafting_rank < legendary → action blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-15 — Craft requires ≥50% raw material cost paid upfront
- **Suite:** module-test-suite
- **Description:** The Craft activity cannot begin unless the character has paid at least 50% of the item's base cost in raw materials.
- **Expected:** materials_paid < item_base_cost × 0.5 → action blocked; error returned.
- **Notes to PM:** Confirm whether "raw material cost" is tracked as currency or as item inventory entries; this affects how the check is parameterized.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (item cost/currency tracking)

### TC-CRF-16 — Craft minimum 4 days; additional days reduce remaining cost
- **Suite:** module-test-suite
- **Description:** Craft requires a minimum of 4 downtime days; each day beyond the minimum reduces the remaining cost owed for the item.
- **Expected:** days_elapsed = 4 → minimum complete; remaining_cost decrements per additional day at the character's daily crafting rate.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (downtime day tracking and cost reduction)

### TC-CRF-17 — Craft can pause and resume
- **Suite:** module-test-suite
- **Description:** A Craft activity can be paused mid-progress and resumed later; progress (days and materials) is preserved between sessions.
- **Expected:** craft_state = paused; on resume, days_elapsed and remaining_cost continue from saved state.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (downtime state persistence)

### TC-CRF-18 — Craft Critical Success: cost reduction at item-level+1 rate
- **Suite:** module-test-suite
- **Description:** On Critical Success, each additional crafting day reduces the remaining cost at the rate of an item one level higher than the actual item (faster reduction).
- **Expected:** daily_cost_reduction_on_crit = rate_for_item_level + 1.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (cost rate table)

### TC-CRF-19 — Craft Failure: full material salvage (no item produced)
- **Suite:** module-test-suite
- **Description:** On Failure, no item is produced; all invested materials are returned to the character.
- **Expected:** item_created = false; materials_returned = materials_invested; character inventory updated.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (inventory/material tracking)

### TC-CRF-20 — Craft Critical Failure: 10% material loss
- **Suite:** module-test-suite
- **Description:** On Critical Failure, no item is produced; 10% of the invested materials are lost (destroyed), and the remaining 90% are returned.
- **Expected:** materials_returned = materials_invested × 0.9 (rounded per rules); material_loss = materials_invested × 0.1.
- **Notes to PM:** Confirm rounding direction for 10% loss (floor, ceil, or nearest).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (material cost tracking)

### TC-CRF-21 — Craft consumables: up to 4 identical items per check
- **Suite:** module-test-suite
- **Description:** When crafting consumable items, a single Craft check can produce up to 4 identical consumables simultaneously.
- **Expected:** consumable_batch_max = 4; attempting > 4 of same consumable in one check → blocked or capped at 4.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (consumable item type and batch logic)

### TC-CRF-22 — Craft ammunition: standard batch quantities
- **Suite:** module-test-suite
- **Description:** Ammunition is crafted in standard batch quantities (e.g., 10 arrows per batch) rather than individual units.
- **Expected:** craft_result for ammo_type = batch_size (e.g., 10 arrows); partial batch not allowed.
- **Notes to PM:** Confirm the standard batch sizes for each ammunition type in scope for dc-cr.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (ammunition item type)

### TC-CRF-23 — Craft alchemical items blocked without Alchemical Crafting feat
- **Suite:** module-test-suite
- **Description:** Crafting an alchemical item requires the Alchemical Crafting feat; characters without it are blocked.
- **Expected:** item_type = alchemical AND feat_alchemical_crafting = false → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (feat gate logic; no equipment system needed for the block check)

### TC-CRF-24 — Craft magic items blocked without Magical Crafting feat
- **Suite:** module-test-suite
- **Description:** Crafting a magic item requires the Magical Crafting feat; characters without it are blocked.
- **Expected:** item_type = magic AND feat_magical_crafting = false → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-25 — Craft snares blocked without Snare Crafting feat
- **Suite:** module-test-suite
- **Description:** Crafting a snare requires the Snare Crafting feat; characters without it are blocked.
- **Expected:** item_type = snare AND feat_snare_crafting = false → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Identify Alchemy

### TC-CRF-26 — Identify Alchemy requires Trained Crafting
- **Suite:** module-test-suite
- **Description:** Identify Alchemy is blocked for characters with Untrained Crafting.
- **Expected:** crafting_rank = untrained → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-27 — Identify Alchemy requires alchemist's tools
- **Suite:** module-test-suite
- **Description:** Identify Alchemy is blocked if the character does not have alchemist's tools.
- **Expected:** alchemists_tools_available = false → action blocked; error returned.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (inventory/tool presence check)

### TC-CRF-28 — Identify Alchemy is an exploration activity (10 minutes)
- **Suite:** module-test-suite
- **Description:** Identify Alchemy is classified as an exploration activity; time cost = 10 uninterrupted minutes.
- **Expected:** action_type = exploration; time_cost = 10 minutes; interruption resets the attempt.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CRF-29 — Identify Alchemy Critical Failure: false identification (not an error)
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure, the system returns a false identification result (wrong item name or properties); it does NOT return an error or null. The character believes they identified the item correctly.
- **Expected:** result = plausible_false_item_data (not null, not error); identified_correctly = false; no exception thrown.
- **Notes to PM:** Confirm whether the false result is: (a) a random other alchemical item, (b) a specifically seeded false entry, or (c) a modified version of the real item. Automation needs a deterministic false-result definition.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (logic gate; does not require equipment-system for the false-result return behavior)

---

### ACL regression

### TC-CRF-30 — ACL regression: no new routes introduced by Crafting actions
- **Suite:** role-url-audit
- **Description:** Crafting action implementation adds no new HTTP routes; existing exploration/downtime handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Conditional dependency summary

| TC | Dependency feature | Reason conditional |
|---|---|---|
| TC-CRF-02 | `dc-cr-equipment-system` | Repair kit inventory presence check |
| TC-CRF-04 | `dc-cr-equipment-system` | Item HP tracking for repair restoration |
| TC-CRF-05 | `dc-cr-equipment-system` | Item HP tracking for repair restoration |
| TC-CRF-06 | `dc-cr-equipment-system` | Item HP and Hardness for crit-fail damage |
| TC-CRF-07 | `dc-cr-equipment-system` | Item destroyed state |
| TC-CRF-08 | `dc-cr-equipment-system` | Item HP max for full-HP no-op |
| TC-CRF-11 | `dc-cr-equipment-system` | Formula catalog ownership |
| TC-CRF-15 | `dc-cr-equipment-system` | Item base cost and material tracking |
| TC-CRF-16 | `dc-cr-equipment-system` | Downtime day tracking and cost reduction |
| TC-CRF-17 | `dc-cr-equipment-system` | Downtime state persistence (pause/resume) |
| TC-CRF-18 | `dc-cr-equipment-system` | Cost rate table for Crit Success |
| TC-CRF-19 | `dc-cr-equipment-system` | Inventory/material return on Failure |
| TC-CRF-20 | `dc-cr-equipment-system` | Material cost tracking for 10% loss |
| TC-CRF-21 | `dc-cr-equipment-system` | Consumable item type and batch logic |
| TC-CRF-22 | `dc-cr-equipment-system` | Ammunition item type and batch sizes |
| TC-CRF-27 | `dc-cr-equipment-system` | Alchemist's tools inventory check |

16 TCs immediately activatable at Stage 0.
14 TCs conditional on `dc-cr-equipment-system` (in-progress Release B).

---

## Notes to PM

1. **TC-CRF-04/05 (Repair HP restoration formula):** "Crit Success = 10 + 10/rank; Success = 5 + 5/rank" — confirm rank scale (Trained=1, Expert=2, Master=3, Legendary=4) for formula parameterization.
2. **TC-CRF-08 (Repair at full HP):** AC says "no-op or minimal effect per GM." For deterministic automation, recommend specifying "0 HP restored, no error" as the system behavior. GM override is out-of-scope for automated testing.
3. **TC-CRF-15 (material cost):** Confirm whether raw material cost is tracked as currency (GP/SP) or as inventory entries (raw material items). This changes the pre-condition check type for TC-CRF-15.
4. **TC-CRF-20 (10% loss rounding):** Confirm floor/ceil/nearest for material loss rounding on Craft Crit Failure.
5. **TC-CRF-22 (ammunition batch sizes):** Standard batch sizes for each ammunition type in scope need to be enumerated in the feature implementation notes.
6. **TC-CRF-29 (false identification data):** Identify Alchemy Crit Failure must return a plausible false result. Define whether the false result is: (a) a random other alchemical item from the catalog, (b) a seeded/deterministic false entry (preferred for automated testing), or (c) a corrupted version of the real item.
7. **Staging recommendation:** The 16 immediately activatable TCs (proficiency gates, activity types, item-level caps, proficiency-level caps, feat gates, ACL, false-result logic) can activate at Stage 0 now. The 14 equipment-dependent TCs activate when dc-cr-equipment-system ships. This is the same staging pattern as dc-cr-class-fighter (equipment-system conditional).
