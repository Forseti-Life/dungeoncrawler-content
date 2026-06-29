# Test Plan: dc-cr-magic-ch11

## Feature
Magic Chapter 11 — comprehensive magic item system covering 154 requirements across:
Magic Item Basics, Activating Items, Item Stat Block Format, Item Rarity, Item Traits,
Crafting Requirements, Precious Materials, Runes, Magic Armor/Weapons, Shields,
Alchemical Items, Consumables, Staves, Wands, Snares, and Worn Items.

## KB references
- Cross-reference: dc-cr-rune-system (property rune rules) — required dependency.
- Cross-reference: dc-cr-crafting (Craft activity, proficiency gates) — required dependency.
- Cross-reference: dc-cr-alchemical-items (alchemist infused items) — required dependency.
- Cross-reference: dc-cr-magic-items (base item data model) — required dependency.
- Cross-reference: dc-cr-spells-ch07 (TC-SP series) — Cast a Spell activation traits overlap.

## Dependencies
- dc-cr-magic-items (base item data model)
- dc-cr-alchemical-items (alchemist class resource tracking)
- dc-cr-rune-system (fundamental + property rune mechanics)
- dc-cr-crafting (Craft activity, proficiency)

## Suite mapping
All TCs target the `dungeoncrawler-content` suite (game-mechanic data/logic).
No new routes or auth surfaces — Security AC exemption confirmed.

---

## Test Cases

### Magic Item Basics

#### TC-MCH-01: Passive/constant effects — automatic on equip
- **Description:** Items with passive/constant effects activate automatically when equipped or held; no Activate action required.
- **Suite:** dungeoncrawler-content
- **Expected:** Item with `effect_type="passive"` triggers effect at equip time; `activate_action` field is null/absent.
- **Roles:** N/A

#### TC-MCH-02: Invested trait — boolean tracking per character
- **Description:** Items with the invested trait track a per-character `invested: boolean` field.
- **Suite:** dungeoncrawler-content
- **Expected:** Character object holds `invested_items: []`; item with `trait="invested"` adds to that list on Invest action.
- **Roles:** N/A

#### TC-MCH-03: Investment cap — 10th item succeeds; 11th has no effect
- **Description:** A character may invest up to 10 magic items per day. Attempting to invest an 11th item neither destroys the item nor returns an error; it simply has no magical effect.
- **Suite:** dungeoncrawler-content
- **Expected:** `invest_item(character_invested_count=10)` → `{success: false, item_destroyed: false, error: null, magic_active: false}`.
- **Roles:** N/A

#### TC-MCH-04: Uninvested item — mundane bonuses still apply
- **Description:** An uninvested item still provides mundane stat bonuses (Hardness, base AC); only magical effects require investiture.
- **Suite:** dungeoncrawler-content
- **Expected:** Item with `invested=false` contributes `base_ac_bonus` and `hardness` to character; `magic_effect` returns null.
- **Roles:** N/A

#### TC-MCH-05: Investiture lost on item removal
- **Description:** When an invested item is removed from the character, investiture is lost for that item.
- **Suite:** dungeoncrawler-content
- **Expected:** `remove_item(invested=true)` → item.invested changes to false.
- **Roles:** N/A

#### TC-MCH-06: Daily investment limit resets at Daily Preparations
- **Description:** The daily investment count resets at Daily Preparations; previously-worn items may be re-invested but still count against the 10-slot limit.
- **Suite:** dungeoncrawler-content
- **Expected:** After `daily_preparations()`, `invested_count` resets to 0; re-investing same items is valid (counts as fresh slots).
- **Roles:** N/A

#### TC-MCH-07: Invest an Item — action cost matches item type
- **Description:** The Invest an Item action takes the same number of Interact actions as donning the item type (varies by item).
- **Suite:** dungeoncrawler-content
- **Expected:** `invest_action_cost(item_type="armor")` returns the same cost as `don_action_cost(item_type="armor")`.
- **Roles:** N/A

#### TC-MCH-08: Removed invested item — slot used for remainder of day
- **Description:** After removing a previously-invested item, it still counts as using one investment slot for the rest of the day.
- **Suite:** dungeoncrawler-content
- **Expected:** `remove_item()` → `invested_slots_used` does not decrement; slot remains consumed until next Daily Preparations.
- **Roles:** N/A

---

### Activating Items

#### TC-MCH-09: Activation cost is item-specific
- **Description:** Activate an Item is a variable-action activity; cost and component type come from the item's stat block.
- **Suite:** dungeoncrawler-content
- **Expected:** `item.activation.actions` and `item.activation.component` fields present and item-specific; no single global default.
- **Roles:** N/A

#### TC-MCH-10: Activation components add traits correctly
- **Description:** Command → auditory + concentrate; Envision → concentrate; Interact → manipulate; Cast a Spell → all Cast a Spell traits.
- **Suite:** dungeoncrawler-content
- **Expected:** `resolve_activation_traits(component="Command")` → `["auditory", "concentrate"]`; `component="Envision"` → `["concentrate"]`; `component="Interact"` → `["manipulate"]`.
- **Roles:** N/A

#### TC-MCH-11: Cast a Spell activation requires spellcasting class feature
- **Description:** A character without a spellcasting class feature cannot activate items with "Cast a Spell" component.
- **Suite:** dungeoncrawler-content
- **Expected:** `activate_item(component="Cast a Spell", character_has_spellcasting=false)` → blocked/returns error.
- **Roles:** N/A

#### TC-MCH-12: Long-duration activation — exploration trait; blocked in encounter
- **Description:** Activations taking minutes or hours gain the exploration trait and cannot be used during an encounter.
- **Suite:** dungeoncrawler-content
- **Expected:** `activate_item(duration="10 minutes", in_encounter=true)` → blocked; `in_encounter=false` → permitted.
- **Roles:** N/A

#### TC-MCH-13: Disrupted activation — actions lost, daily use consumed
- **Description:** If an activation is disrupted (e.g., combat starts mid-activation), the actions are lost but the daily use count is still consumed.
- **Suite:** dungeoncrawler-content
- **Expected:** `disrupt_activation(item)` → `item.daily_uses_remaining` decrements; effect does not apply.
- **Roles:** N/A

#### TC-MCH-14: Daily use counts reset at Daily Preparations
- **Description:** Per-item daily use counts reset at Daily Preparations.
- **Suite:** dungeoncrawler-content
- **Expected:** Item with `daily_uses_remaining=0` → after `daily_preparations()` → `daily_uses_remaining` restores to `max_daily_uses`.
- **Roles:** N/A

#### TC-MCH-15: Use limit tied to item object, not character
- **Description:** A different character cannot bypass a used-up item's daily use limit.
- **Suite:** dungeoncrawler-content
- **Expected:** Item with `daily_uses_remaining=0`; different character attempts activation → blocked (same result as original holder).
- **Roles:** N/A

#### TC-MCH-16: Sustain an Activation — 1-action concentrate extends effect
- **Description:** Sustain an Activation is a 1-action concentrate activity that extends a sustained effect to end of next turn.
- **Suite:** dungeoncrawler-content
- **Expected:** `sustain_activation(item_with_sustained_effect)` costs 1 action with concentrate trait; effect duration extends to end of next turn.
- **Roles:** N/A

#### TC-MCH-17: Sustaining >100 rounds — fatigue applied, effect ends
- **Description:** Sustaining an activation beyond 100 rounds (10 minutes) applies the fatigued condition and ends the effect.
- **Suite:** dungeoncrawler-content
- **Expected:** `sustain_activation(rounds_sustained=101)` → character gains fatigued condition; effect terminates.
- **Roles:** N/A

#### TC-MCH-18: Disrupted Sustain — effect ends immediately
- **Description:** If a Sustain action is disrupted, the sustained effect ends immediately.
- **Suite:** dungeoncrawler-content
- **Expected:** `disrupt_sustain(item)` → sustained effect status changes to inactive.
- **Roles:** N/A

#### TC-MCH-19: Dismiss — 1-action concentrate ends eligible activation
- **Description:** Dismiss is a 1-action concentrate activity that ends one eligible sustained activation.
- **Suite:** dungeoncrawler-content
- **Expected:** `dismiss(item_with_sustained_effect)` costs 1 action with concentrate trait; effect ends.
- **Roles:** N/A

---

### Item Stat Block Format

#### TC-MCH-20: Magic item data model completeness
- **Description:** The magic item data model includes all required fields: name, level, rarity/traits, price, usage, bulk, activation definition, onset, description, typed variants, craft requirements.
- **Suite:** dungeoncrawler-content
- **Expected:** Schema validation confirms all listed fields exist and are non-null for a sample item set.
- **Roles:** N/A

#### TC-MCH-21: Multi-type items — base + typed variants
- **Description:** Items with multiple types (e.g., lesser/greater/major) are modeled as a base item with typed variants carrying level/price/stat overrides.
- **Suite:** dungeoncrawler-content
- **Expected:** `item.variants` contains entries with `variant_level`, `variant_price` fields; base item fields serve as defaults.
- **Roles:** N/A

#### TC-MCH-22: No level gate on using items (only on Crafting)
- **Description:** Any character may use a magic item regardless of level; the level gate applies only to Crafting (character level must be ≥ item level).
- **Suite:** dungeoncrawler-content
- **Expected:** `can_use_item(character_level=1, item_level=10)` → `true`; `can_craft_item(character_level=1, item_level=10)` → `false`.
- **Roles:** N/A

---

### Item Rarity

#### TC-MCH-23: Four rarity tiers present
- **Description:** Item rarity has exactly four valid values: Common, Uncommon, Rare, Unique.
- **Suite:** dungeoncrawler-content
- **Expected:** Rarity enum contains `["common", "uncommon", "rare", "unique"]`.
- **Roles:** N/A

#### TC-MCH-24: Rare items not purchasable by default
- **Description:** Rare items cannot be purchased from standard shops; formulas unavailable by default.
- **Suite:** dungeoncrawler-content
- **Expected:** `item_available_for_purchase(rarity="rare")` → `false`; `formula_available(rarity="rare")` → `false`.
- **Roles:** N/A

#### TC-MCH-25: Unique items — only one exists
- **Description:** Unique items have a quantity constraint of 1 in the game world.
- **Suite:** dungeoncrawler-content
- **Expected:** `item.max_quantity` field for unique items = 1; attempting to create a second instance returns an error or warning.
- **Roles:** N/A

---

### Item Traits

#### TC-MCH-26: Alchemical items — no magic aura, immune to Dispel Magic
- **Description:** Alchemical items have `is_magical=false`, are not detectable by magical aura detection, and are immune to Dispel Magic and anti-magic effects.
- **Suite:** dungeoncrawler-content
- **Expected:** `detect_magic(item_type="alchemical")` → `false`; `apply_dispel_magic(item_type="alchemical")` → no effect.
- **Roles:** N/A

#### TC-MCH-27: Consumable crafting — produces 4 copies per Craft activity
- **Description:** Crafting a consumable item produces 4 copies per single Craft activity.
- **Suite:** dungeoncrawler-content
- **Expected:** `craft_item(item_type="consumable")` → `quantity_produced=4`.
- **Roles:** N/A

#### TC-MCH-28: Focused items — +1 Focus Point on investiture, max 1 bonus/day
- **Description:** Items with the focused trait grant +1 Focus Point when invested; only characters with a focus pool benefit; max 1 bonus Focus Point per day regardless of items invested.
- **Suite:** dungeoncrawler-content
- **Expected:** `invest_focused_item(has_focus_pool=true)` → `focus_points += 1`; investing a second focused item adds 0 additional points; `has_focus_pool=false` → 0 points.
- **Roles:** N/A

---

### Crafting Requirements

#### TC-MCH-29: Crafting proficiency gates — Trained/Master/Legendary tiers
- **Description:** Crafting proficiency determines which item levels can be crafted: Trained = level 1–8, Master = levels 9–15, Legendary = levels 16+.
- **Suite:** dungeoncrawler-content
- **Expected:** `can_craft_item(crafter_proficiency="trained", item_level=9)` → `false`; `item_level=8` → `true`; similar boundary assertions for master and legendary.
- **Roles:** N/A

#### TC-MCH-30: Crafting feat requirements enforced
- **Description:** Magical Crafting feat required for magic items; Alchemical Crafting for alchemical; Snare Crafting for snares.
- **Suite:** dungeoncrawler-content
- **Expected:** `can_craft_item(item_type="magic", has_magical_crafting=false)` → `false`; `has_magical_crafting=true` → `true`.
- **Roles:** N/A

#### TC-MCH-31: Item upgrade Crafting — cost = new price minus old price
- **Description:** When upgrading an item (e.g., lesser → greater), the Crafting cost is `new_price - old_price`; DC uses the new item's level.
- **Suite:** dungeoncrawler-content
- **Expected:** `craft_upgrade_cost(old_price=50, new_price=300)` → `250`; `craft_upgrade_dc_level` = new item level.
- **Roles:** N/A

---

### Precious Materials

#### TC-MCH-32: Item has at most one precious material
- **Description:** An item cannot combine two precious materials.
- **Suite:** dungeoncrawler-content
- **Expected:** `apply_precious_material(item_already_has_material=true)` → blocked/error.
- **Roles:** N/A

#### TC-MCH-33: Precious material grade proficiency gates
- **Description:** Low-grade requires Expert Crafting; Standard-grade requires Master; High-grade requires Legendary.
- **Suite:** dungeoncrawler-content
- **Expected:** `can_apply_precious_material(grade="standard", proficiency="expert")` → `false`; `proficiency="master"` → `true`.
- **Roles:** N/A

#### TC-MCH-34: Precious material level caps
- **Description:** Low-grade allows max level 8 items/runes; Standard-grade max level 15; High-grade no restriction.
- **Suite:** dungeoncrawler-content
- **Expected:** `can_apply_precious_material(grade="low", item_level=9)` → `false`; `item_level=8` → `true`.
- **Roles:** N/A

#### TC-MCH-35: Precious material investment percentages
- **Description:** Material investment costs: Low = 10% of initial cost, Standard = 25%, High = 100%.
- **Suite:** dungeoncrawler-content
- **Expected:** `precious_material_cost(base_price=100, grade="standard")` → `25`.
- **Roles:** N/A

#### TC-MCH-36: Orichalcum self-repair and rune slots
- **Description:** Orichalcum self-repairs to full HP after 24 hours if damaged but not destroyed; armor grants +1 circumstance to initiative; can hold 4 property rune slots.
- **Suite:** dungeoncrawler-content
- **Expected:** `item.material="orichalcum"` → `property_rune_slots=4` (override of normal potency-based count); `auto_repair_after_hours=24`.
- **Roles:** N/A

---

### Runes

#### TC-MCH-37: Property rune slots equal potency rune value
- **Description:** The number of property rune slots on an item equals its potency rune value (0 through 3).
- **Suite:** dungeoncrawler-content
- **Expected:** `item.potency_rune=2` → `item.property_rune_slots=2`.
- **Roles:** N/A

#### TC-MCH-38: Armor with rune gains invested trait automatically
- **Description:** Any armor with at least one rune etched gains the invested trait automatically.
- **Suite:** dungeoncrawler-content
- **Expected:** `etch_rune(item_type="armor")` → `item.traits` includes "invested".
- **Roles:** N/A

#### TC-MCH-39: Orphaned property runes go dormant when potency removed
- **Description:** If a potency rune is removed, any property runes that relied on it go dormant until a compatible potency rune is present.
- **Suite:** dungeoncrawler-content
- **Expected:** `remove_rune(type="potency", item_with_property_runes=true)` → property runes status = "dormant"; effects suspended.
- **Roles:** N/A

#### TC-MCH-40: Duplicate property runes — higher level only (except energy-resistant different types)
- **Description:** Duplicate property runes: only the higher-level one applies. Exception: energy-resistant runes of different damage types all apply.
- **Suite:** dungeoncrawler-content
- **Expected:** Two flaming runes at different levels → only higher applies. Fire-resistant + cold-resistant → both apply.
- **Roles:** N/A

#### TC-MCH-41: Transfer Rune — 10% cost, minimum 1 day
- **Description:** Transfer Rune is a Craft activity; cost = 10% of rune price; minimum 1 day regardless of rune level.
- **Suite:** dungeoncrawler-content
- **Expected:** `transfer_rune_cost(rune_price=100)` → `10`; `minimum_craft_days=1`.
- **Roles:** N/A

#### TC-MCH-42: Transfer from runestone — free
- **Description:** Transferring a rune from a runestone costs nothing.
- **Suite:** dungeoncrawler-content
- **Expected:** `transfer_rune_cost(source="runestone")` → `0`.
- **Roles:** N/A

#### TC-MCH-43: Incompatible rune transfer — automatic critical failure
- **Description:** Attempting to transfer an incompatible rune (e.g., weapon rune to armor) is an automatic critical failure.
- **Suite:** dungeoncrawler-content
- **Expected:** `transfer_rune(rune="striking", target_item_type="armor")` → `result="critical_failure"`.
- **Roles:** N/A

#### TC-MCH-44: Specific locked magic items — property rune slots = 0
- **Description:** Specific locked magic items have 0 property rune slots; fundamental runes are still allowed.
- **Suite:** dungeoncrawler-content
- **Expected:** `item.specific_item=true` → `property_rune_slots=0`; `can_etch_fundamental_rune=true`.
- **Roles:** N/A

---

### Alchemical Items

#### TC-MCH-45: Bomb — martial thrown weapon, 20 ft range, manipulate trait on Strike
- **Description:** Alchemical bombs are martial thrown weapons with 20 ft range; the Strike action gains the manipulate trait; no Activate action needed.
- **Suite:** dungeoncrawler-content
- **Expected:** `item.type="bomb"` → `weapon_group="martial"`, `range=20`, `strike_traits` includes "thrown" and "manipulate"; no activation entry.
- **Roles:** N/A

#### TC-MCH-46: Splash damage — 5 ft radius, no Str modifier, not doubled on crit
- **Description:** Bomb splash damage applies within 5 ft on any outcome except critical failure; Str modifier not added; splash not doubled on critical hits.
- **Suite:** dungeoncrawler-content
- **Expected:** `resolve_bomb_splash(outcome="critical_success", splash_damage=1)` → 1 damage to nearby creatures; `str_bonus=0`; `outcome="critical_failure"` → 0 splash.
- **Roles:** N/A

#### TC-MCH-47: Resistance/weakness applies to combined splash + initial damage
- **Description:** When computing resistance or weakness, splash damage and initial (direct hit) damage are combined first, then resistance/weakness applied.
- **Suite:** dungeoncrawler-content
- **Expected:** `apply_resistance(initial=4, splash=1, resistance=3)` → total 5, after resistance = 2 (not 1 + 0 separately).
- **Roles:** N/A

#### TC-MCH-48: Mutagen — Benefit + Drawback simultaneously, polymorph effect
- **Description:** A mutagen applies both its benefit and its drawback simultaneously; it is a polymorph effect. New polymorph attempts counteract using item level.
- **Suite:** dungeoncrawler-content
- **Expected:** `consume_mutagen()` → both `benefit_active=true` and `drawback_active=true`; `polymorph_tag=true`; counteract check uses item level as counteract level.
- **Roles:** N/A

#### TC-MCH-49: Four poison exposure types — distinct trigger conditions
- **Description:** Contact, Ingested, Inhaled, and Injury poisons have distinct trigger conditions; system enforces them separately.
- **Suite:** dungeoncrawler-content
- **Expected:** Four poison `exposure_type` enum values; `contact` triggers on skin touch, `ingested` on eating/drinking, `inhaled` on breathing, `injury` on piercing/slashing Strike.
- **Roles:** N/A

#### TC-MCH-50: Injury poison — consumption rules on hit outcomes
- **Description:** Injury poison is consumed on a critical fail Strike (even if target unaffected); remains on weapon after failed Strike; consumed after successful piercing/slashing Strike.
- **Suite:** dungeoncrawler-content
- **Expected:** Strike outcome → crit fail = poison consumed; fail = poison remains; success with piercing/slashing = poison consumed + applies.
- **Roles:** N/A

---

### Consumables (Magical)

#### TC-MCH-51: Scroll — destroyed on use, no cantrips/focus/rituals
- **Description:** Scrolls are consumed (destroyed) when cast; cannot hold cantrips, focus spells, or rituals.
- **Suite:** dungeoncrawler-content
- **Expected:** `cast_scroll()` → `scroll` removed from inventory; `create_scroll(spell_type="cantrip")` → blocked.
- **Roles:** N/A

#### TC-MCH-52: Scroll casting — must be on caster's spell list
- **Description:** A character may only cast a scroll if the stored spell appears on their spell list; uses their own attack/DC and tradition.
- **Suite:** dungeoncrawler-content
- **Expected:** `cast_scroll(spell_on_caster_list=false)` → blocked; `spell_on_caster_list=true` → uses caster's `spell_attack` and `spell_dc`.
- **Roles:** N/A

#### TC-MCH-53: Scroll rarity matches stored spell's rarity
- **Description:** A scroll's rarity equals the rarity of the stored spell.
- **Suite:** dungeoncrawler-content
- **Expected:** `scroll_rarity(stored_spell_rarity="rare")` → `scroll.rarity="rare"`.
- **Roles:** N/A

#### TC-MCH-54: Talisman — one per item; multiple talismans deactivate all
- **Description:** Each item may have only one active talisman; affixing a second deactivates all talismans on that item.
- **Suite:** dungeoncrawler-content
- **Expected:** `affix_talisman(item_already_has_talisman=true)` → all talismans on item deactivated.
- **Roles:** N/A

---

### Staves

#### TC-MCH-55: Staff — one preparer per day; only preparer expends charges
- **Description:** A staff tracks a single preparer per day; only that character can expend charges.
- **Suite:** dungeoncrawler-content
- **Expected:** `staff.preparer_id` set at daily prep; `cast_from_staff(character != preparer)` → blocked.
- **Roles:** N/A

#### TC-MCH-56: Staff daily charges = preparer's highest spell slot level
- **Description:** At daily preparations, a staff gains charges equal to the preparer's highest-level spell slot (before any extra slot sacrifice).
- **Suite:** dungeoncrawler-content
- **Expected:** `prepare_staff(preparer_highest_slot=5)` → `staff.charges=5`.
- **Roles:** N/A

#### TC-MCH-57: Prepared caster — sacrifice slot for extra charges (max once/day)
- **Description:** A prepared caster may sacrifice one spell slot during daily prep to add charges equal to the slot's level; this may only be done once per day.
- **Suite:** dungeoncrawler-content
- **Expected:** `prepare_staff_with_sacrifice(slot_level=3)` → `staff.charges += 3`; second sacrifice attempt blocked.
- **Roles:** N/A

#### TC-MCH-58: Spontaneous caster — costs 1 charge + 1 spell slot
- **Description:** A spontaneous caster expends 1 charge and 1 spell slot (of at least the staff spell's level) to cast from a staff.
- **Suite:** dungeoncrawler-content
- **Expected:** `cast_from_staff(caster_type="spontaneous", spell_level=2)` → `staff.charges -= 1` and `caster.spell_slots[2] -= 1`.
- **Roles:** N/A

#### TC-MCH-59: Staff cantrip — 0 charges, heightened to caster's cantrip level
- **Description:** Casting a cantrip from a staff costs 0 charges and heightens it to the caster's cantrip level.
- **Suite:** dungeoncrawler-content
- **Expected:** `cast_from_staff(spell_type="cantrip")` → `charges_consumed=0`; spell heightened to caster's cantrip level.
- **Roles:** N/A

---

### Wands

#### TC-MCH-60: Wand daily use = 1 cast; overcharge available
- **Description:** A wand may be cast once per day. After the daily use, one overcharge attempt is permitted per wand per day.
- **Suite:** dungeoncrawler-content
- **Expected:** `cast_wand(daily_used=true)` → blocked for normal cast; `overcharge_wand(already_overcharged=false)` → rolls flat DC 10.
- **Roles:** N/A

#### TC-MCH-61: Wand overcharge — success=Broken, failure=Destroyed
- **Description:** Overcharging a wand: success (DC 10 flat check) = wand Broken; failure = wand Destroyed.
- **Suite:** dungeoncrawler-content
- **Expected:** `overcharge_wand(d20_result=10)` → `wand.status="broken"`; `d20_result=9` → `wand.status="destroyed"`.
- **Roles:** N/A

#### TC-MCH-62 (Edge): Wand overcharge when already overcharged — auto-Destroyed
- **Description:** Attempting to overcharge a wand that has already been overcharged today results in automatic destruction with no spell cast.
- **Suite:** dungeoncrawler-content
- **Expected:** `overcharge_wand(already_overcharged=true)` → `wand.status="destroyed"`, `spell_cast=false`.
- **Roles:** N/A

---

### Snares

#### TC-MCH-63: Snare requires Snare Crafting feat + snare kit
- **Description:** Snares cannot be crafted without the Snare Crafting feat and a snare kit in inventory.
- **Suite:** dungeoncrawler-content
- **Expected:** `craft_snare(has_snare_crafting=false)` → blocked; `has_snare_kit=false` → blocked.
- **Roles:** N/A

#### TC-MCH-64: Snare occupies 5-ft square; cannot be relocated
- **Description:** A snare is placed in exactly one 5×5 ft square and cannot be moved after placement.
- **Suite:** dungeoncrawler-content
- **Expected:** `snare.location` is set on placement; `move_snare()` → blocked/error.
- **Roles:** N/A

#### TC-MCH-65: Snare detection/disable DC = creator's Crafting DC
- **Description:** The DC to detect or disable a snare equals the creator's Crafting DC.
- **Suite:** dungeoncrawler-content
- **Expected:** `snare.detection_dc == snare.disable_dc == creator.crafting_dc`.
- **Roles:** N/A

#### TC-MCH-66: Expert+ crafter snares — only found by actively searching
- **Description:** Snares created by a crafter with Expert or higher proficiency are only detected by characters actively Searching, not by passive Perception.
- **Suite:** dungeoncrawler-content
- **Expected:** `snare.creator_proficiency >= "expert"` → `passive_detection=false`; `active_search_required=true`.
- **Roles:** N/A

#### TC-MCH-67: Creator disarms own snare — 1 Interact action, adjacent
- **Description:** The snare's creator can disarm their own snare with 1 Interact action while adjacent; no skill check required.
- **Suite:** dungeoncrawler-content
- **Expected:** `disarm_snare(is_creator=true, adjacent=true)` → snare removed; `action_cost=1`; `skill_check_required=false`.
- **Roles:** N/A

---

### Worn Items

#### TC-MCH-68: Worn slot uniqueness — one item per slot
- **Description:** Only one item may occupy any given worn slot at a time (e.g., one cloak, one headband).
- **Suite:** dungeoncrawler-content
- **Expected:** `equip_item(slot="cloak", slot_already_occupied=true)` → blocked.
- **Roles:** N/A

#### TC-MCH-69: Rings — no slot limit
- **Description:** Multiple rings may be worn simultaneously (no ring slot limit).
- **Suite:** dungeoncrawler-content
- **Expected:** `equip_item(slot="ring", rings_worn=5)` → permitted (no cap).
- **Roles:** N/A

#### TC-MCH-70: Explorer's clothing — can have armor runes
- **Description:** Explorer's clothing may have armor runes etched onto it even though it is not light/medium/heavy armor.
- **Suite:** dungeoncrawler-content
- **Expected:** `etch_rune(item="explorer's clothing")` → permitted.
- **Roles:** N/A

#### TC-MCH-71: Apex items — +2 to ability score or raise to 18
- **Description:** An apex item grants +2 to its specified ability score or raises it to 18, whichever is higher.
- **Suite:** dungeoncrawler-content
- **Expected:** `apply_apex_bonus(current_score=14)` → 16 (14+2); `apply_apex_bonus(current_score=17)` → 18 (raised); `apply_apex_bonus(current_score=20)` → 20+2=22 (no cap).
- **Roles:** N/A
- **Note to PM:** Confirm: does apex apply +2 even if score is already above 18 (e.g., 20 → 22)? AC says "whichever is higher" but doesn't clarify the above-18 case.

#### TC-MCH-72: Only one apex benefit active at a time
- **Description:** Investing a second apex item provides no additional ability score increase; other magical effects of both items still apply.
- **Suite:** dungeoncrawler-content
- **Expected:** `invest_apex_item(already_have_apex_active=true)` → `ability_score_increase_applied=false`; item's other effects active.
- **Roles:** N/A

#### TC-MCH-73: Apex benefit fires only on first investment within 24 hours
- **Description:** The apex ability score benefit applies only the first time a character invests an apex item within a 24-hour window.
- **Suite:** dungeoncrawler-content
- **Expected:** `invest_apex_item(first_investment_today=true)` → score increased; `first_investment_today=false` → score not increased.
- **Roles:** N/A

---

## Edge Cases

#### TC-MCH-74 (Edge): 11th invested item — no error, item not destroyed
- **Description:** Investing an 11th item returns no error and does not destroy the item; magical effects are simply absent.
- **Suite:** dungeoncrawler-content
- **Expected:** `invest_item(current_count=10)` → `{success: false, item_status: "intact", error: null}`.
- **Roles:** N/A

#### TC-MCH-75 (Edge): Two identical property runes — higher level only (unless different energy types)
- **Description:** Two copies of the same property rune: only the higher-level applies. Exception: energy-resistant runes with different damage types all apply.
- **Suite:** dungeoncrawler-content
- **Expected:** Two `flaming` runes at level 2 and level 3 → only level 3 applies. `fire-resistant` + `cold-resistant` → both apply.
- **Roles:** N/A

---

## Failure Modes

#### TC-MCH-76 (Failure): Cast scroll without spell on list — blocked
- **Description:** A character whose spell list does not include the scroll's stored spell cannot cast it.
- **Suite:** dungeoncrawler-content
- **Expected:** `cast_scroll(spell_on_list=false)` → blocked with error; spell not cast; scroll not consumed.
- **Roles:** N/A

#### TC-MCH-77 (Failure): Staff prepared by one character, accessed by another — blocked
- **Description:** A character who did not prepare a staff cannot expend charges from it.
- **Suite:** dungeoncrawler-content
- **Expected:** `cast_from_staff(character_id != staff.preparer_id)` → blocked.
- **Roles:** N/A

#### TC-MCH-78 (Failure): Mutagen countercasting — system attempts counteract check
- **Description:** When a new polymorph effect targets a character under a mutagen (polymorph), the system initiates a counteract check rather than silently stacking the effects.
- **Suite:** dungeoncrawler-content
- **Expected:** `apply_polymorph_effect(target_has_mutagen=true)` → `counteract_check_initiated=true`; does not stack silently.
- **Roles:** N/A

---

## Notes to PM

1. **Apex above-18 case (TC-MCH-71):** AC does not clarify whether apex grants +2 to scores already above 18 (e.g., 20 → 22). BA clarification needed for exact automation assertion.

2. **154-req scope:** This is the largest feature batch groomed to date; sub-systems (staves, wands, precious materials, runes) each have significant interdependencies with dc-cr-rune-system, dc-cr-crafting, and dc-cr-magic-items. PM should confirm all dependency features are scheduled into scope before or alongside dc-cr-magic-ch11.

3. **Consumable scroll — material components:** AC states scroll material components are replaced by somatic. This is tested implicitly in TC-MCH-52 (cast mechanics); a dedicated TC could be added if Dev needs explicit coverage on the component substitution.

4. **Security AC exemption confirmed:** No new routes or user-facing input surfaces. All TCs are logic/data assertions only.
