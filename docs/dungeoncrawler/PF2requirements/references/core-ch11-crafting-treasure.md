# PF2E Core Rulebook — Chapter 11: Crafting & Treasure
## Extracted Requirements for DungeonCrawler System

---

## SECTION: Magic Item Basics

### Paragraph — Constant Abilities
> Items with constant abilities (like an everburning torch or a flaming weapon) always function automatically without needing to be activated — they work whenever the item is worn or held.

- REQ: Some magic items must have a "passive/constant" effect mode that triggers automatically when the item is equipped/held, requiring no action from the player.

### Paragraph — Investing Magic Items
> Invested items require the Invest an Item activity to work. You can benefit from no more than 10 invested magic items each day. Uninvested items still provide mundane benefits but not magical ones (e.g., uninvested +1 resilient armor gives its item bonus to AC but not its bonus to saves).

- REQ: Magic items with the invested trait track an "invested" boolean state per character.
- REQ: Each character has a maximum of 10 invested magic items per day; attempting to invest an 11th has no effect (the cap is not enforced by destroying the item — it simply doesn't work).
- REQ: An uninvested item still grants its mundane (non-magical) stat contributions (Hardness, AC bonus from armor itself, etc.); only magical effects are gated behind investiture.
- REQ: Investiture is lost when the item is removed.
- REQ: The daily investment limit resets during Daily Preparations. Items worn from the previous day can be re-invested during that preparation (they still count against the limit).

### Paragraph — INVEST AN ITEM Activity
> Investing takes the same number of Interact actions as donning the item. Investiture lasts until the item is removed. Items removed after investment still count against the daily limit.

- REQ: Implement `Invest an Item` as an activity requiring the same Interact actions as donning that item type.
- REQ: After removal, a previously-invested item retains its "used one slot" status for the rest of the day.

---

## SECTION: Activating Items

### Paragraph — ACTIVATE AN ITEM Activity
> Activating an Item is a special activity with variable action cost listed in the item's stat block. It can sometimes be a reaction or free action. The four activation components are: Command (auditory, concentrate), Envision (concentrate), Interact (manipulate), Cast a Spell.

- REQ: Implement `Activate an Item` as a variable-action activity. The action cost and components are item-specific.
- REQ: Activation components add traits to the activation action: Command → auditory + concentrate; Envision → concentrate; Interact → manipulate; Cast a Spell → all traits from Cast a Spell.
- REQ: Items with `Cast a Spell` activation require the character to have a spellcasting class feature.
- REQ: Long-duration activations (minutes/hours) have the exploration trait and cannot be initiated in an encounter; if combat starts mid-activation, the activation is disrupted.
- REQ: Disrupted activations cause the character to lose the actions spent and the activation still counts against daily use limits.

### Paragraph — Limited Activations
> Items can be activated only a limited number of times per day. The limit resets during daily preparations and is per-item, not per-character (another creature re-investing doesn't reset it).

- REQ: Track per-item daily use counts that reset at Daily Preparations.
- REQ: The use limit is tied to the item object, not the character — a different character investing the same item cannot bypass a used limit.

### Paragraph — Sustain an Activation
> Once activated, some items can be sustained until end of next turn. The Sustain an Activation action (1 action, concentrate) extends duration. Sustaining for more than 10 minutes (100 rounds) ends the effect and makes the character fatigued.

- REQ: Implement `Sustain an Activation` as a 1-action, concentrate activity that extends a sustained item effect until end of next turn.
- REQ: Track consecutive rounds of sustaining; if total exceeds 100 rounds (10 minutes), automatically end the effect and apply the Fatigued condition to the character.
- REQ: If a Sustain action is disrupted, the effect ends immediately.

### Paragraph — Dismiss Action
> The Dismiss action (1 action, concentrate) ends any spell effect or item activation that allows dismissal.

- REQ: Implement `Dismiss` as a 1-action concentrate action that ends one eligible sustained activation.

---

## SECTION: Item Stat Block Format

### Paragraph — Stat Block Fields
> Item stat blocks include: Item Name, Level, Traits, Price, Ammunition (for magic ammo), Usage (held in N hands / worn [slot] / affixed to / etched onto), Bulk, Activate (action cost + components, Frequency, Trigger, Requirements), Onset (for delayed effects), description, Type entries (for multi-version items), Craft Requirements.

- REQ: Magic item data model must include: name, level, rarity/traits, price, usage type (held/worn/affixed/etched), bulk, activate definition (actions, components, frequency, trigger, requirements), onset, description, typed variants, craft requirements.
- REQ: For multi-type items (e.g., "+1 armor potency" vs "+2 armor potency"), model as a base item with typed variants each specifying level, price, and overriding stats.

### Paragraph — Item Level and Crafting Cap
> Any character can use an item of any level. A character can Craft only items whose level is ≤ their own character level.

- REQ: No level gate on using items. Gate only on Crafting: character's level must be ≥ item level to Craft it.

---

## SECTION: Item Rarity

### Paragraph — Rarity Tiers
> Items are common, uncommon (U superscript), rare (R superscript), or unique. Uncommon items may appear infrequently for sale. Rare items generally cannot be purchased, and their formulas are lost to time. Unique items are one-of-a-kind.

- REQ: Item rarity system with four tiers: Common, Uncommon, Rare, Unique.
- REQ: Uncommon items: available infrequently for sale; formulas are restricted.
- REQ: Rare items: not available for purchase by default (GM override required); formulas not available.
- REQ: Unique items: only one exists.

---

## SECTION: Item Traits (Notable)

### Paragraph — Alchemical Trait
> Alchemical items are NOT magical. They don't radiate magical auras and can't be dispelled. Require the Alchemical Crafting feat to Craft.

- REQ: Alchemical items have no magic aura. They are immune to `Dispel Magic` and similar anti-magic effects.
- REQ: Crafting alchemical items requires the `Alchemical Crafting` feat.

### Paragraph — Consumable Trait
> A consumable item can be used only once and is destroyed after activation (unless otherwise noted). Consumable items are crafted in batches of 4.

- REQ: Consumable items are destroyed/removed from inventory on use.
- REQ: When Crafting consumable items, the system produces 4 copies per successful Craft activity.

### Paragraph — Focused Trait
> A focused item, when invested, gives 1 additional Focus Point separate from the focus pool (does not count toward pool cap). Max 1 Focus Point per day from focused items total, regardless of how many focused items are invested.

- REQ: Focused items grant a bonus Focus Point on investiture, separate from the character's focus pool.
- REQ: Bonus Focus Point from focused items: max 1 per day regardless of how many focused items are invested.
- REQ: Only characters with a focus pool can benefit from focused items.

### Paragraph — Invested Trait
> Wearing 10+ invested items exceeds the limit; magical effects of un-invested items don't apply.

- REQ: See Investing Magic Items above. Enforce 10-item invested limit.

### Paragraph — Magical Trait
> Magical items radiate magic auras tied to their school (abjuration, conjuration, etc.). Crafting requires the Magical Crafting feat.

- REQ: Each magical item has an associated school of magic (aura type).
- REQ: Crafting magical items requires the `Magical Crafting` feat.

---

## SECTION: Crafting Requirements

### Paragraph — Proficiency Thresholds by Item Level
> Items level 1–8: trained in Crafting. Items level 9–15: master proficiency required. Items level 16+: legendary proficiency required.

- REQ: Enforce Crafting proficiency gate: Trained (levels 1–8), Master (levels 9–15), Legendary (levels 16+).
- REQ: These proficiency requirements are in addition to the character's level matching the item's level.
- REQ: Creating alchemical items requires the Alchemical Crafting feat; magic items require the Magical Crafting feat; snares require the Snare Crafting feat.

### Paragraph — Upgrading Items
> A GM may allow Crafting an upgrade from a lower-level version to a higher-level version of the same item. The cost is the full Price difference; the DC is for the higher-level item.

- REQ: Support item upgrade Crafting: cost = (new item price) − (old item price); DC uses the new item's level.

---

## SECTION: Precious Materials

### Paragraph — Material Overview
> Items can be made from precious materials, substituting base materials. An item can have no more than one precious material. Only an expert Crafter can create a low-grade item; master for standard-grade; legendary for high-grade.

- REQ: Items have at most one precious material component.
- REQ: Precious material item grade gates: Low-grade → Expert Crafting; Standard-grade → Master Crafting; High-grade → Legendary Crafting.
- REQ: Item level must be ≥ precious material's level requirement.

### Paragraph — Grade and Level Caps
> Low-grade materials: support items/runes up to 8th level. Standard-grade: up to 15th level. High-grade: any level/any rune.

- REQ: Low-grade precious items cannot hold runes above 8th level or be used for items above 8th level.
- REQ: Standard-grade: max 15th level items/runes.
- REQ: High-grade: no restriction on item or rune level.

### Paragraph — Raw Material Investment Minimums
> When Crafting with precious materials: Low-grade → at least 10% of investment must be the material; Standard-grade → 25%; High-grade → 100%.

- REQ: Crafting system tracks material composition requirement: 10% (low), 25% (standard), 100% (high) of initial investment must be the precious material.

### Paragraph — Table 11-4: Material Hardness, HP, and BT

| Material | Hardness | HP | BT |
|---|---|---|---|
| Paper | 0 | 1 | — |
| Thin cloth | 0 | 1 | — |
| Thin glass | 0 | 1 | — |
| Cloth | 1 | 4 | 2 |
| Glass | 1 | 4 | 2 |
| Glass structure | 2 | 8 | 4 |
| Thin leather | 2 | 8 | 4 |
| Thin rope | 2 | 8 | 4 |
| Thin wood | 3 | 12 | 6 |
| Leather | 4 | 16 | 8 |
| Rope | 4 | 16 | 8 |
| Thin stone | 4 | 16 | 8 |
| Thin iron/steel | 5 | 20 | 10 |
| Wood | 5 | 20 | 10 |
| Stone | 7 | 28 | 14 |
| Iron or steel | 9 | 36 | 18 |
| Wooden structure | 10 | 40 | 20 |
| Stone structure | 14 | 56 | 28 |
| Iron/steel structure | 18 | 72 | 36 |

- REQ: Implement material Hardness, HP, and Broken Threshold values for all common materials using the table above.
- REQ: Multi-material items: use the strongest material's stats, or GM may choose the weaker point of failure.

### Paragraph — Precious Material: Cold Iron (Level 2+)
> Cold iron weapons/armor trigger Sickened 1 on creatures with weakness to cold iron (demons, fey). Weapon that critically misses → no effect; armor wearer with weakness → sickened 1 while worn.

| Grade | Item Level | Thin (H/HP/BT) | Items (H/HP/BT) | Structures (H/HP/BT) |
|---|---|---|---|---|
| Low | 2 | 5/20/10 | 9/36/18 | 18/72/36 |
| Standard | 7 | 7/28/14 | 11/44/22 | 22/88/44 |
| High | 15 | 10/40/20 | 14/56/28 | 28/112/56 |

- REQ: Cold iron weapons/armor: critical fail of unarmed attack by creature with cold iron weakness → Sickened 1. Wearing cold iron armor → Sickened 1 (while worn, if creature has weakness).

### Paragraph — Precious Material: Adamantine (Level 8+, Uncommon)
> Adamantine weapons treat hit objects as having half Hardness (rounded down). Not available as low-grade.

| Grade | Item Level | Thin (H/HP/BT) | Items (H/HP/BT) |
|---|---|---|---|
| Standard | 8 | 10/40/20 | 14/56/28 |
| High | 16 | 13/52/26 | 17/68/34 |

- REQ: Adamantine weapons halve the Hardness of objects they hit (no effect if object's Hardness > adamantine weapon's Hardness).

### Paragraph — Precious Material: Darkwood (Level 8+, Uncommon)
> Darkwood is 1 Bulk lighter (min light Bulk). Wooden armor: reduces Strength penalty by 2, Speed penalty by 5 ft.

| Grade | Item Level | Thin (H/HP/BT) | Items (H/HP/BT) | Structures (H/HP/BT) |
|---|---|---|---|---|
| Standard | 8 | 5/20/10 | 7/28/14 | 14/56/28 |
| High | 16 | 8/32/16 | 10/40/20 | 20/80/40 |

- REQ: Darkwood items reduce Bulk by 1 (min light Bulk). Price calculation uses original Bulk.

### Paragraph — Precious Material: Dragonhide (Level 8+, Uncommon)
> Dragonhide items are immune to one damage type based on dragon origin: Black/Copper → Acid; Blue/Bronze → Electricity; Brass/Gold/Red → Fire; Green → Poison; Silver/White → Cold. Armor also grants +1 circumstance bonus to AC and saves against that damage type.

| Grade | Item Level | Thin (H/HP/BT) | Items (H/HP/BT) |
|---|---|---|---|
| Standard | 8 | 4/16/8 | 7/28/14 |
| High | 16 | 8/32/16 | 11/44/22 |

- REQ: Dragonhide items have immunity to their dragon type's damage. Dragonhide armor additionally grants +1 circumstance bonus to AC and saves against that damage type.

### Paragraph — Precious Material: Mithral (Level 8+, Uncommon)
> Mithral is treated as silver for weakness purposes. 1 Bulk lighter (min light Bulk). Armor: reduces Strength penalty by 2, Speed penalty by 5 ft.

| Grade | Item Level | Thin (H/HP/BT) | Items (H/HP/BT) | Structures (H/HP/BT) |
|---|---|---|---|---|
| Standard | 8 | 5/20/10 | 9/36/18 | 18/72/36 |
| High | 16 | 8/32/16 | 12/48/24 | 24/96/48 |

- REQ: Mithral items count as silver for weakness/resistance calculations.
- REQ: Mithral reduces Bulk by 1 (min light Bulk). Armor: -2 Str penalty, -5 ft Speed penalty.

### Paragraph — Precious Material: Orichalcum (Level 17+, Rare, High-grade only)
> Orichalcum is the rarest skymetal. If damaged but not destroyed, it self-repairs after 24 hours. Orichalcum armor grants +1 circumstance bonus to initiative. Orichalcum weapons/armor can hold 4 property runes (instead of 3 at +3 potency).

| Grade | Item Level | Thin (H/HP/BT) | Items (H/HP/BT) | Structures (H/HP/BT) |
|---|---|---|---|---|
| High | 17 | 16/64/32 | 18/72/36 | 35/140/70 |

- REQ: Orichalcum items self-repair to full HP after 24 hours if damaged but not destroyed.
- REQ: Orichalcum armor: +1 circumstance bonus to initiative.
- REQ: Orichalcum items can be etched with 4 property runes (regardless of potency rune value).

### Paragraph — Precious Material: Silver (Level 2+)
> Silver items are less durable than steel. Silver weapons deal extra damage to creatures with weakness to silver (werewolves, devils).

| Grade | Item Level | Thin (H/HP/BT) | Items (H/HP/BT) | Structures (H/HP/BT) |
|---|---|---|---|---|
| Low | 2 | 3/12/6 | 5/20/10 | 10/40/20 |
| Standard | 7 | 5/20/10 | 7/28/14 | 14/56/28 |
| High | 15 | 8/32/16 | 10/40/20 | 20/80/40 |

---

## SECTION: Runes

### Paragraph — Rune Overview
> Runes are etched onto weapons and armor to grant magical enhancements. Two types: fundamental runes (potency + striking/resilient) and property runes. Number of property runes allowed = value of potency rune. An item can have only one fundamental rune of each type.

- REQ: Weapons and armor track: potency rune value (0/+1/+2/+3), striking/resilient rune tier, and property rune slots.
- REQ: Property rune slots = potency rune value (e.g., +2 weapon → 2 property rune slots).
- REQ: Each item holds at most one armor potency rune, one resilient rune, one weapon potency rune, one striking rune.
- REQ: An item's effective level = max(base item level, all rune levels).

### Paragraph — Runes and Investiture
> If a suit of armor has any runes, it gains the invested trait and must be invested to benefit from the runes.

- REQ: Any armor with at least one etched rune automatically gains the invested trait.

### Paragraph — The Etching Process
> Etching a rune follows the Craft activity. Must be able to Craft magic items (Magical Crafting feat), have the rune formula, have the item in possession throughout, and meet Craft Requirements. Only one rune can be etched at a time.

- REQ: Etching runes uses the Craft activity (downtime); requires Magical Crafting feat, rune formula, item in possession, one rune at a time.

### Paragraph — Transferring Runes
> Rune transfers use the Craft activity. Can move one rune between items, or swap matching rune types (both fundamental or both property). DC = item level of the rune being transferred. Cost = 10% of rune's Price (free if transferring from a runestone). Takes 1 day (not the normal 4-day minimum), with option to extend for discounts. Incompatible transfers (e.g., melee rune to ranged weapon) result in automatic critical failure.

- REQ: Implement `Transfer Rune` as a Craft activity: DC by rune level, cost 10% of rune price, minimum 1 day (extendable for discount).
- REQ: Transfer from a runestone is free.
- REQ: Incompatible rune transfers (wrong weapon/armor type) → automatic critical failure.
- REQ: If a potency rune is transferred away, orphaned property runes go dormant until compatible potency rune is present.
- REQ: Can only swap runes of the same category (fundamental ↔ fundamental, property ↔ property).

### Paragraph — Fundamental Armor Runes: Armor Potency
> Armor potency rune increases item bonus to AC and determines max property rune slots.
> - +1 armor potency: Level 5, 160 gp; +1 to AC, 1 property slot; requires Expert Crafting.
> - +2 armor potency: Level 11, 1,060 gp; +2 to AC, 2 property slots; requires Master Crafting.
> - +3 armor potency: Level 18, 20,560 gp; +3 to AC, 3 property slots; requires Legendary Crafting.

- REQ: Armor potency rune data: three types with level, price, AC bonus, property slot count, Crafting proficiency requirement.

### Paragraph — Fundamental Armor Runes: Resilient
> Resilient rune grants item bonus to all saving throws.
> - Resilient: Level 8, 340 gp; +1 item bonus to saves.
> - Greater resilient: Level 14, 3,440 gp; +2 item bonus to saves.
> - Major resilient: Level 20, 49,440 gp; +3 item bonus to saves.

- REQ: Resilient rune data: three tiers with level, price, and save bonus.

### Paragraph — Fundamental Weapon Runes: Striking
> Striking rune increases weapon damage dice count.
> - Striking: Level 4, 65 gp; 2 damage dice.
> - Greater striking: Level 12, 1,065 gp; 3 damage dice.
> - Major striking: Level 19, 31,065 gp; 4 damage dice.

- REQ: Striking rune data: three tiers; stores damage die count multiplier (2/3/4).

### Paragraph — Fundamental Weapon Runes: Weapon Potency
> Weapon potency rune grants item bonus to attack rolls and determines property rune slots.
> - +1 weapon potency: Level 2, 35 gp; +1 attack, 1 property slot; requires Expert Crafting.
> - +2 weapon potency: Level 10, 935 gp; +2 attack, 2 property slots; requires Master Crafting.
> - +3 weapon potency: Level 16, 8,935 gp; +3 attack, 3 property slots; requires Legendary Crafting.

- REQ: Weapon potency rune data: three types with level, price, attack bonus, property slot count, Crafting proficiency requirement.

### Paragraph — Rune Upgrade Pricing (Table 11-5 and 11-6)

**Armor Upgrade Prices:**

| Starting Armor | Improvement | Cost | Level |
|---|---|---|---|
| +1 armor | +1 resilient armor | 340 gp (etch resilient) | 8 |
| +1 resilient | +2 resilient armor | 900 gp (etch +2 potency) | 11 |
| +2 resilient | +2 greater resilient | 3,100 gp (etch greater resilient) | 14 |
| +2 greater resilient | +3 greater resilient | 19,500 gp (etch +3 potency) | 18 |
| +3 greater resilient | +3 major resilient | 46,000 gp (etch major resilient) | 20 |

**Weapon Upgrade Prices:**

| Starting Weapon | Improvement | Cost | Level |
|---|---|---|---|
| +1 weapon | +1 striking | 65 gp (etch striking) | 4 |
| +1 striking | +2 striking | 900 gp (etch +2 potency) | 10 |
| +2 striking | +2 greater striking | 1,000 gp (etch greater striking) | 12 |
| +2 greater striking | +3 greater striking | 8,000 gp (etch +3 potency) | 16 |
| +3 greater striking | +3 major striking | 30,000 gp (etch major striking) | 19 |

- REQ: Item upgrade Crafting cost = price difference between target rune and existing rune (not full new rune price). The Craft DC uses the level of the new rune.

### Paragraph — Property Runes
> Property runes add varied abilities. Only highest-level duplicate rune applies (except energy-resistant, which can have multiple as long as each is a different damage type). Can be upgraded like fundamental runes. Activatable property rune abilities follow item activation rules.

- REQ: If a weapon/armor has two identical property runes, only the higher-level one applies.
- REQ: Exception: energy-resistant armor can have multiple energy-resistant runes, each for a different damage type (all apply).

### Paragraph — Specific Magic Armor and Weapons
> Specific magic items are uniquely crafted items that cannot have property runes added or transferred. They can have fundamental runes added/upgraded normally.

- REQ: Flag specific magic armor and weapons: property rune slots = 0 (locked). Fundamental runes can still be added/upgraded.

---

## SECTION: Magic Armor

### Paragraph — Basic Magic Armor (Fundamental Rune Combos)

| Type | Level | Price |
|---|---|---|
| +1 armor | 5 | 160 gp |
| +1 resilient armor | 8 | 500 gp |
| +2 resilient armor | 11 | 1,400 gp |
| +2 greater resilient armor | 14 | 4,500 gp |
| +3 greater resilient armor | 18 | 24,000 gp |
| +3 major resilient armor | 20 | 70,000 gp |

- REQ: Basic magic armor types are predefined item entries representing armor + fundamental rune combinations; prices include all runes.

### Paragraph — Precious Material Armor
> Adamantine/mithral/darkwood/dragonhide armor can be made at standard or high grade (cold iron and silver also available at low grade). Prices are base + per-Bulk cost. Use carried Bulk for material pricing.

- REQ: Precious material armor pricing uses carried Bulk (not worn Bulk). Price = base + (gp per Bulk × carried Bulk).

---

## SECTION: Magic Weapons

### Paragraph — Basic Magic Weapon (Fundamental Rune Combos)
> Same pricing applies to all weapon types (the price doesn't vary between a dagger and a greatsword for base rune combos). Prices are listed for standard material weapons.

- REQ: Basic magic weapon entries represent rune combinations; price is uniform across weapon types (base material differences covered separately).

### Paragraph — Adamantine Weapons
> Adamantine weapons treat hit objects as having half Hardness. Not available as low-grade.
> - Standard-grade (Level 11): 1,400 gp + 140 gp/Bulk
> - High-grade (Level 17): 13,500 gp + 1,350 gp/Bulk

### Paragraph — Cold Iron Weapons
> Deadly to demons and fey (triggers their weakness).
> - Low-grade (Level 2): 40 gp + 4 gp/Bulk
> - Standard-grade (Level 10): 880 gp + 88 gp/Bulk
> - High-grade (Level 16): 9,000 gp + 900 gp/Bulk

### Paragraph — Darkwood Weapons (Uncommon)
> Darkwood weapon: Bulk reduced by 1 (min light).
> - Standard-grade (Level 11): 1,400 gp + 140 gp/Bulk
> - High-grade (Level 17): 13,500 gp + 1,350 gp/Bulk

### Paragraph — Mithral Weapons (Uncommon)
> Mithral weapons count as silver. Bulk -1 (min light).
> - Standard-grade (Level 11): 1,400 gp + 140 gp/Bulk
> - High-grade (Level 17): 13,500 gp + 1,350 gp/Bulk

### Paragraph — Orichalcum Weapons (Rare, High-grade only)
> Can hold 4 property runes. Speed rune costs half price when etched on orichalcum weapon.
> - High-grade (Level 18): 22,500 gp + 2,250 gp/Bulk

### Paragraph — Silver Weapons
> Silver weapons trigger weakness to silver.
> - Low-grade (Level 2): 40 gp + 4 gp/Bulk
> - Standard-grade (Level 10): 880 gp + 88 gp/Bulk
> - High-grade (Level 16): 9,000 gp + 900 gp/Bulk

- REQ: Precious material weapon pricing uses per-Bulk cost added to the base price.

---

## SECTION: Shields

### Paragraph — Magic Shields Overview
> All magic shields are specific items with varied effects. Unlike magic armor, magic shields cannot be etched with runes.

- REQ: Shields cannot have runes etched onto them.

### Paragraph — Precious Material Shields
> Precious material shields use same grade system. Adamantine shields: treat as adamantine weapons when used for shield bash.

| Material | Grade | Level | Buckler (H/HP/BT) | Shield (H/HP/BT) |
|---|---|---|---|---|
| Adamantine | Standard | 8 | 8/32/16 | 10/40/20 |
| Adamantine | High | 16 | 11/44/22 | 13/52/26 |
| Cold iron | Low | 2 | 3/12/6 | 5/20/10 |
| Cold iron | Standard | 7 | 5/20/10 | 7/28/14 |
| Cold iron | High | 15 | 8/32/16 | 10/40/20 |

- REQ: Shield Hardness/HP/BT values are grade-specific and stored per shield item.
- REQ: Adamantine shields are treated as adamantine weapons for shield bash attacks.

---

## SECTION: Alchemical Items

### Paragraph — Alchemical Items Overview
> Alchemical items are NOT magical. They use chemical reactions, don't radiate auras, and can't be dispelled. Alchemists can create short-lived items using infused reagents (free, no monetary cost) that don't change this. Critically failing a Craft check for alchemical items can cause dangerous effects (explosions, accidental exposure).

- REQ: Alchemical items have `is_magical = false`. They cannot be detected by magical aura detection and are immune to dispel/anti-magic.
- REQ: Alchemist ability to create free "infused" alchemical items: track as a class-level resource, not a store purchase.
- REQ: Critical failure when Crafting alchemical items triggers a hazardous side effect (bomb → explosion; poison → accidental exposure).

### Paragraph — Alchemical Bombs
> Alchemical bombs are martial thrown weapons (range 20 ft). Attack rolls vs AC. Strike action gains the manipulate trait. Activation is automatic when thrown (no separate Activate). Most bombs have the splash trait.

- REQ: Alchemical bombs are implemented as martial thrown weapons with range increment 20 ft.
- REQ: Throwing a bomb Strike gains the manipulate trait.
- REQ: No separate activation needed; the Strike itself activates the bomb.

### Paragraph — Splash Trait
> On a hit or miss (not critical failure), all creatures within 5 feet of the target take splash damage. Strength modifier is NOT added to damage of splash weapons. Splash damage is not multiplied on critical hits. Add splash to initial damage before applying resistance/weakness.

- REQ: Splash damage applies to all creatures within 5 feet of the target on any outcome except critical failure.
- REQ: No Strength modifier added to splash weapon damage rolls.
- REQ: Splash damage is not doubled on critical hits.
- REQ: When calculating resistance/weakness, combine splash + initial damage first, then apply the target's resistance/weakness.

### Paragraph — Alchemical Elixirs
> Elixirs are activated by drinking (Interact action). Can be fed to a willing/unconscious creature within reach, one-handed. Mutagens are polymorph effects with both benefit and drawback entries.

- REQ: Elixirs use a 1-action Interact to consume (or feed to an adjacent willing/incapacitated creature).
- REQ: Mutagens have both Benefit and Drawback fields; both apply simultaneously.
- REQ: Mutagens are polymorph effects; a new polymorph effect attempts to counteract an existing one using the item's level as modifier.

### Paragraph — Alchemical Poisons — Methods of Exposure
> Four exposure types:
> - **Contact**: applied to item/skin; first creature to touch it saves; onset typically 1 minute.
> - **Ingested**: applied to food/drink or placed in mouth; creature saves on consumption; onset 1 min – 1 day.
> - **Inhaled**: released from container; creates a 10-ft cube cloud lasting 1 minute; every creature entering must save; holding breath (1 action) grants +2 circumstance bonus to save for 1 round.
> - **Injury**: applied to weapon/ammo; if Strike succeeds and deals piercing/slashing damage, target saves; on critical failure of Strike, poison is spent with no effect.

- REQ: Implement four poison exposure types: Contact, Ingested, Inhaled, Injury — each with distinct trigger conditions and mechanics.
- REQ: Inhaled poisons: 10-ft cube cloud; 1-minute duration; entering the cloud triggers the save.
- REQ: Holding breath vs. inhaled poison: 1 action to hold breath; +2 circumstance bonus to save for 1 round.
- REQ: Injury poison: consumed on critical fail Strike even if target is unaffected; remains on weapon after failed Strike; consumed after successful piercing/slashing Strike.

### Paragraph — Alchemical Tools
> Non-bomb, non-elixir, non-poison alchemical items. Follow general alchemical item rules.

- REQ: Alchemical tools are a catch-all subcategory; they are consumable, non-magical, and use alchemical rules.

---

## SECTION: Consumables (Magical)

### Paragraph — Oils Overview
> Oils are magical gels applied to items or creatures. Require two hands (one holds jar, one applies). Can only be applied to willing/unconscious targets or items in their possession.

- REQ: Applying an oil requires two hands and a 1-action Interact (unless otherwise specified).
- REQ: Applying an oil to an unwilling, non-incapacitated creature is not possible.

### Paragraph — Potions Overview
> Potions are drunk (Interact action). Benefits typically affect only the drinker. Must be held in 1 hand.

- REQ: Potions are activated with 1 action (Interact/drink). Usage: held in 1 hand.

### Paragraph — Scrolls Overview
> A scroll contains a single spell cast at a fixed level. Cantrips, focus spells, and rituals cannot be put on scrolls. The scroll is destroyed when cast. Spell level on scroll is fixed; cannot be heightened beyond what's etched.
> To identify a scroll: if it's a common spell from your list or a spell you know → 1 Recall Knowledge action (auto-success). Otherwise → Identify Magic activity required.
> To cast from a scroll: must hold it in one hand; must be a spell on your spell list; uses your attack roll/DC; gains your tradition trait; material components replaced with somatic component; focus requirements still apply.

- REQ: Scroll data includes a fixed spell and fixed spell level.
- REQ: Scrolls destroyed on use.
- REQ: Cannot place cantrips, focus spells, or rituals on scrolls.
- REQ: Casting from a scroll requires it to be on the caster's spell list.
- REQ: Scroll cast uses the caster's spell attack/DC and tradition trait.
- REQ: Material components replaced by somatic when casting from a scroll; focus requirements unchanged.

### Paragraph — Scroll Statistics (Table 11-3)

| Spell Level | Item Level | Price |
|---|---|---|
| 1 | 1 | 4 gp |
| 2 | 3 | 12 gp |
| 3 | 5 | 30 gp |
| 4 | 7 | 70 gp |
| 5 | 9 | 150 gp |
| 6 | 11 | 300 gp |
| 7 | 13 | 600 gp |
| 8 | 15 | 1,300 gp |
| 9 | 17 | 3,000 gp |
| 10 | 19 | 8,000 gp |

- REQ: Scroll level and price are derived from the stored spell's level per Table 11-3 above.
- REQ: If a scroll's spell has a cost, add that cost to the listed price.
- REQ: Scroll rarity matches the stored spell's rarity.

### Paragraph — Talismans Overview
> A talisman is affixed to armor, shield, or weapon (Affix a Talisman activity: 10 minutes, requires repair kit, uses both hands). Only one talisman per item; multiple talismans on one item deactivates all. Must wield/wear affixed item to activate. Consumable: burns out after activation (permanently destroyed). Many can be activated as a free action on a trigger.

- REQ: Talismans are affix-only consumables; one per item.
- REQ: Multiple talismans on one item → all deactivated (must remove and re-affix).
- REQ: `Affix a Talisman` activity: 10 minutes, requires repair kit and two hands.
- REQ: Must be wielding/wearing the item to activate a talisman on it.
- REQ: Talisman is permanently destroyed on activation.

---

## SECTION: Staves

### Paragraph — Staves Overview
> A magical staff is tied to one person via a daily preparation process. Only the preparer can use the staff's charges. Staff spells are organized by level in the stat block; higher-version staves include all lower-version spells too. All staves have the staff trait and are also staff weapons (can be used as a weapon).

- REQ: Staves track a single "preparer" identity per day; only that character can expend charges.
- REQ: Staff spell lists organized by spell level; higher-version staves include all spells from lower-version entries.
- REQ: Staves can be used as melee weapons (staff weapon type) and can be etched with fundamental runes (not property runes).

### Paragraph — Preparing a Staff
> During daily preparations, prepare one staff (free action). Staff gains charges = character's highest-level spell slot. No spell slots expended to add charges this way. Only one staff can be prepared per day. Unused charges expire after 24 hours.

- REQ: Daily preparation grants a staff charges = preparer's highest-level spell slot level.
- REQ: Only one staff can be prepared per day, per character.
- REQ: Charges expire after 24 hours; re-preparing removes remaining charges.

### Paragraph — Prepared Spellcasters and Staves
> A prepared spellcaster can additionally expend one spell slot during prep to add charges equal to that slot's level. Max one extra spell this way per day.

- REQ: Prepared spellcasters can sacrifice one spell slot during daily prep to add charges (charges gained = slot level). Max one such sacrifice per day.

### Paragraph — Spontaneous Spellcasters and Staves
> A spontaneous spellcaster can expend 1 charge + 1 spell slot to cast a spell of that slot level or lower from the staff. This is instead of expending charges equal to the spell's level.

- REQ: Spontaneous spellcasters can use 1 charge + 1 spell slot to cast a staff spell of ≤ slot level (instead of spending charges = spell level).

### Paragraph — Casting from a Staff
> Must have spell on your spell list; must be able to cast spells of that level; expend charges = spell level. Uses your attack/DC and tradition. Must provide material components, cost, and focus. Cantrips on staves cost 0 charges and are heightened to your cantrip level.

- REQ: Casting from a staff: must have spell on spell list, charges spent = spell level, uses caster's attack/DC/tradition.
- REQ: Material components, spell costs, and focus requirements apply when casting from a staff.
- REQ: Cantrip casting from a staff: 0 charges, heightened to caster's cantrip level.

---

## SECTION: Wands

### Paragraph — Wands Overview
> Wands hold one spell at a fixed level. Cantrips, focus spells, and rituals cannot be placed in wands. Usable once per day plus one overcharge attempt.

- REQ: Wand data includes a fixed spell at a fixed level.
- REQ: Cannot place cantrips, focus spells, or rituals in wands.
- REQ: Wands have a daily use limit of 1 cast.

### Paragraph — Casting from a Wand
> Hold in one hand, Cast a Spell activity. Spell must be on your spell list. Uses your attack/DC and tradition trait. Material components replaced by somatic component. Focus and cost requirements still apply.

- REQ: Casting from a wand requires the spell to be on the caster's spell list.
- REQ: Wand casts use the caster's attack/DC and tradition.
- REQ: Material components replaced by somatic; focus and cost requirements still apply.

### Paragraph — Overcharging a Wand
> After the daily cast, a wand can be overcharged once: cast the spell again, then roll a flat DC 10 check. Success → wand is Broken. Failure → wand is Destroyed. Attempting to overcharge an already-overcharged wand → automatic Destroyed, no spell cast.

- REQ: Overcharge flag per wand per day. One overcharge attempt allowed after daily cast.
- REQ: Overcharge resolution: flat DC 10 check → success = Broken, failure = Destroyed.
- REQ: Attempting overcharge on an already-overcharged wand: wand is automatically Destroyed, no spell cast.

### Paragraph — Wand Statistics
> All wands: light Bulk, held in 1 hand, Hardness/HP/BT of a thin item of its material. Level and price based on spell level.

| Spell Level | Wand Level | Price |
|---|---|---|
| 1 | 3 | 60 gp |
| 2 | 5 | 160 gp |
| 3 | 7 | 360 gp |
| 4 | 9 | 700 gp |
| 5 | 11 | 1,500 gp |
| 6 | 13 | 3,000 gp |
| 7 | 15 | 6,500 gp |
| 8 | 17 | 15,000 gp |
| 9 | 19 | 40,000 gp |

- REQ: Wand level and price derived from stored spell level per pricing table above.

---

## SECTION: Snares

### Paragraph — Snare Rules
> Snares require the Snare Crafting feat and a snare kit. Built within a single 5-foot square; cannot be moved. Crafted in 1 minute at full price (or downtime for discount). Detection DC = creator's Crafting DC. Disable DC = creator's Crafting DC (Thievery).
> Proficiency-based detection and disable gating: Expert Crafter → only Trained Perception can find; Master → only Expert; Legendary → only Master. Expert+ Crafting: only actively searching creatures detect them.
> Creator can automatically disarm their own snare with 1 Interact action while adjacent.

- REQ: Snares require `Snare Crafting` feat and a snare kit.
- REQ: Snares occupy one 5-foot square and cannot be relocated.
- REQ: Quick crafting: 1 minute at full price. Discounted: downtime Craft activity.
- REQ: Snare Stealth (Detection) DC = creator's Crafting DC. Disable DC = same (uses Thievery).
- REQ: Detection/disable requires minimum Perception/Thievery proficiency based on creator's Crafting proficiency (Trained/Expert/Master gate).
- REQ: Expert+ Crafter snares: only found by actively-searching creatures.
- REQ: Creator automatically disarms their own snare with 1 Interact action (adjacent).

---

## SECTION: Worn Items

### Paragraph — Worn Item Slots
> Most worn items have the invested trait (max 10 per day). Slot restrictions: "worn [slot]" in Usage entry means only one item per slot. Examples: worn belt, worn cloak, worn headband, worn ring — rings have no slot restriction (can wear multiple). Explorer's clothing can be etched with runes despite not being formal armor.

- REQ: Enforce worn item slot uniqueness: if a Usage entry specifies "worn [slot]", only one item of that slot type can be worn at a time.
- REQ: Rings have no slot limit (multiple rings can be worn simultaneously).
- REQ: Explorer's clothing can be etched with armor runes even though it is not light/medium/heavy armor.

### Paragraph — Apex Items
> Apex items increase one ability score by 2, or to 18, whichever is higher. Only one apex item benefit at a time (attempting to invest a second while one is active — no ability score increase, other effects still work). Apex benefit granted only on first investiture within a 24-hour period.

- REQ: Apex items grant +2 to a specified ability score, or raise it to 18, whichever is higher.
- REQ: Only one apex ability score benefit active at a time. Investing a second apex item while one is active: no additional score increase (other effects still apply).
- REQ: Apex benefit fires only on the first investment within any 24-hour window.
- REQ: Increasing ability scores via apex items immediately applies all downstream effects (HP from Constitution, skill training from Intelligence, etc.).
