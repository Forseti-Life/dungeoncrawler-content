# Test Plan Design: dc-cr-magic-ch11

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:39:55+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-magic-ch11/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-magic-ch11 "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: dc-cr-magic-ch11

## Gap analysis reference
- DB sections: core/ch11 — Magic Item Basics (8), Activating Items (11), Item Stat Block Format (3), Item Rarity (4), Item Traits Notable (10), Crafting Requirements (4), Precious Materials (18), Runes (19), Magic Armor (2), Magic Weapons (2), Shields (3), Alchemical Items (18), Consumables Magical (17), Staves (11), Wands (10), Snares (7), Worn Items (7) = 154 reqs
- Depends on: dc-cr-magic-items, dc-cr-alchemical-items, dc-cr-rune-system, dc-cr-crafting

---

## Happy Path

### Magic Item Basics
- [ ] `[NEW]` Items with passive/constant effects trigger automatically when equipped/held (no action required).
- [ ] `[NEW]` Items with the invested trait track an "invested" boolean per character.
- [ ] `[NEW]` Maximum 10 invested magic items per day; attempting to invest an 11th has no effect (item not destroyed).
- [ ] `[NEW]` Uninvested items still grant mundane stat bonuses (Hardness, base AC); only magical effects gated behind investiture.
- [ ] `[NEW]` Investiture lost when item is removed.
- [ ] `[NEW]` Daily investment limit resets at Daily Preparations; previously-worn items can be re-invested (still count against limit).
- [ ] `[NEW]` Invest an Item: same Interact actions as donning the item type.
- [ ] `[NEW]` After removal, previously-invested item retains "used one slot" status for remainder of the day.

### Activating Items
- [ ] `[NEW]` Activate an Item: variable-action activity; cost and components item-specific.
- [ ] `[NEW]` Activation components add traits: Command → auditory + concentrate; Envision → concentrate; Interact → manipulate; Cast a Spell → all Cast a Spell traits.
- [ ] `[NEW]` Cast a Spell activation requires a spellcasting class feature.
- [ ] `[NEW]` Long-duration activations (minutes/hours): exploration trait; blocked in encounter; disrupted if combat starts mid-activation.
- [ ] `[NEW]` Disrupted activations: actions lost; daily use count still consumed.
- [ ] `[NEW]` Per-item daily use counts tracked; reset at Daily Preparations.
- [ ] `[NEW]` Use limit tied to the item object, not the character (different character cannot bypass used limit).
- [ ] `[NEW]` Sustain an Activation: 1-action concentrate; extends sustained effect to end of next turn.
- [ ] `[NEW]` Sustaining > 100 rounds (10 min): fatigue applied, effect ends.
- [ ] `[NEW]` Disrupted Sustain: effect ends immediately.
- [ ] `[NEW]` Dismiss: 1-action concentrate; ends one eligible sustained activation.

### Item Stat Block Format
- [ ] `[NEW]` Magic item data model includes: name, level, rarity/traits, price, usage (held/worn/affixed/etched), bulk, activation definition (actions, components, frequency, trigger, requirements), onset, description, typed variants, craft requirements.
- [ ] `[NEW]` Multi-type items modeled as base item + typed variants with level/price/stat overrides.
- [ ] `[NEW]` No level gate on using items; level gate only on Crafting (character level ≥ item level).

### Item Rarity
- [ ] `[NEW]` Four rarity tiers: Common, Uncommon, Rare, Unique.
- [ ] `[NEW]` Uncommon: available infrequently; formulas restricted.
- [ ] `[NEW]` Rare: not purchasable by default; formulas unavailable.
- [ ] `[NEW]` Unique: only one exists.

### Item Traits
- [ ] `[NEW]` Alchemical items: no magic aura; immune to Dispel Magic and anti-magic effects; require Alchemical Crafting feat to craft.
- [ ] `[NEW]` Consumable items: destroyed/removed from inventory on use; crafting produces 4 copies per Craft activity.
- [ ] `[NEW]` Focused items: grant +1 Focus Point on investiture; max 1 bonus Focus Point per day regardless of number invested; only characters with a focus pool benefit.
- [ ] `[NEW]` Invested limit of 10 enforced (per Magic Item Basics above).
- [ ] `[NEW]` Each magical item has an associated school of magic (aura type).
- [ ] `[NEW]` Magical Crafting feat required to craft magical items.

### Crafting Requirements
- [ ] `[NEW]` Crafting proficiency gates: Trained (items level 1–8), Master (levels 9–15), Legendary (levels 16+).
- [ ] `[NEW]` Proficiency requirement is additive with character level requirement.
- [ ] `[NEW]` Feat requirements: Alchemical Crafting (alchemical), Magical Crafting (magic), Snare Crafting (snares).
- [ ] `[NEW]` Item upgrade Crafting: cost = (new price) – (old price); DC uses new item's level.

### Precious Materials
- [ ] `[NEW]` Items have at most one precious material.
- [ ] `[NEW]` Precious material grades: Low-grade (Expert Crafting), Standard-grade (Master Crafting), High-grade (Legendary Crafting).
- [ ] `[NEW]` Item level must meet precious material's minimum.
- [ ] `[NEW]` Low-grade: max level 8 items/runes. Standard-grade: max level 15. High-grade: no restriction.
- [ ] `[NEW]` Precious material investment: Low = 10%, Standard = 25%, High = 100% of initial cost.
- [ ] `[NEW]` All material Hardness/HP/BT values implemented per material table.
- [ ] `[NEW]` Multi-material items: use strongest material stats.
- [ ] `[NEW]` Cold iron: Sickened 1 on critical fail unarmed attack or while worn (for creatures with weakness).
- [ ] `[NEW]` Adamantine weapons: halve Hardness of struck objects (unless object Hardness > adamantine).
- [ ] `[NEW]` Darkwood: reduce Bulk by 1 (minimum light); price calculated on original Bulk.
- [ ] `[NEW]` Dragonhide: immunity to dragon type's damage; armor additionally grants +1 circumstance to AC/saves vs that type.
- [ ] `[NEW]` Mithral: counts as silver; reduces Bulk by 1; armor: –2 Str penalty, –5 ft Speed.
- [ ] `[NEW]` Orichalcum: self-repairs to full HP after 24 hours if damaged but not destroyed; armor: +1 circumstance to initiative; can hold 4 property rune slots.

### Runes
- [ ] `[NEW]` Weapons/armor track: potency rune value (0/+1/+2/+3), striking/resilient tier, and property rune slots.
- [ ] `[NEW]` Property rune slots = potency rune value.
- [ ] `[NEW]` Each item holds at most 1 armor potency, 1 resilient, 1 weapon potency, 1 striking rune.
- [ ] `[NEW]` Item effective level = max(base level, all rune levels).
- [ ] `[NEW]` Any armor with at least one etched rune automatically gains invested trait.
- [ ] `[NEW]` Etching runes uses Craft activity; requires Magical Crafting feat, formula, item in possession, one rune at a time.
- [ ] `[NEW]` Transfer Rune: Craft activity; DC by rune level; cost 10% of rune price; minimum 1 day.
- [ ] `[NEW]` Transfer from runestone is free.
- [ ] `[NEW]` Incompatible rune transfer: automatic critical failure.
- [ ] `[NEW]` Orphaned property runes (potency removed): go dormant until compatible potency present.
- [ ] `[NEW]` Only same-category swaps (fundamental ↔ fundamental, property ↔ property).
- [ ] `[NEW]` Duplicate property runes: only higher-level applies (exception: energy-resistant runes of different damage types all apply).
- [ ] `[NEW]` Specific locked magic items: property rune slots = 0; fundamental runes still allowed.

### Magic Armor and Weapons
- [ ] `[NEW]` Basic magic armor/weapon entries represent rune combinations; prices include all runes.
- [ ] `[NEW]` Precious material armor pricing uses carried Bulk (not worn Bulk).
- [ ] `[NEW]` Precious material weapon pricing adds per-Bulk cost to base price.

### Shields
- [ ] `[NEW]` Shields cannot have runes etched onto them.
- [ ] `[NEW]` Shield Hardness/HP/BT values grade-specific and stored per item.
- [ ] `[NEW]` Adamantine shields treated as adamantine weapons for bash attacks.

### Alchemical Items
- [ ] `[NEW]` Alchemical items: `is_magical = false`; not detectable by magical aura; immune to anti-magic.
- [ ] `[NEW]` Alchemist infused items tracked as class-level resource (not store purchases).
- [ ] `[NEW]` Critical failure crafting alchemical: bomb → explosion; poison → accidental exposure.
- [ ] `[NEW]` Bombs: martial thrown weapon, 20 ft range; Strike gains manipulate trait; no activation needed.
- [ ] `[NEW]` Splash damage: applies within 5 feet on any outcome except crit fail; no Str modifier; not doubled on crits.
- [ ] `[NEW]` Resistance/weakness: combine splash + initial damage, then apply resistance/weakness.
- [ ] `[NEW]` Elixirs: 1-action Interact to consume or feed to adjacent willing/incapacitated creature.
- [ ] `[NEW]` Mutagens: Benefit + Drawback both apply simultaneously; polymorph effect; new polymorph attempts counteract using item level.
- [ ] `[NEW]` Four poison exposure types: Contact, Ingested, Inhaled, Injury — distinct trigger conditions.
- [ ] `[NEW]` Inhaled poisons: 10-ft cube cloud, 1-minute duration; entering cloud triggers save.
- [ ] `[NEW]` Holding breath vs inhaled: 1 action; +2 circumstance to save for 1 round.
- [ ] `[NEW]` Injury poison: consumed on crit fail Strike even if target unaffected; remains on weapon after failed Strike; consumed after successful piercing/slashing Strike.

### Consumables (Magical)
- [ ] `[NEW]` Applying oil: two hands + 1-action Interact; impossible on unwilling non-incapacitated creature.
- [ ] `[NEW]` Potions: 1-action Interact/drink; held in 1 hand.
- [ ] `[NEW]` Scrolls: fixed spell at fixed level; destroyed on use; no cantrips/focus/rituals.
- [ ] `[NEW]` Scroll casting: spell must be on caster's spell list; uses caster's attack/DC and tradition.
- [ ] `[NEW]` Scroll: material components replaced by somatic; focus requirements unchanged.
- [ ] `[NEW]` Scroll level/price derived from stored spell level per Table 11-3.
- [ ] `[NEW]` Scroll rarity matches stored spell's rarity.
- [ ] `[NEW]` Talismans: one per item; multiple talismans = all deactivated; Affix = 10 min + repair kit + two hands.
- [ ] `[NEW]` Must be wielding/wearing item to activate its talisman; talisman destroyed on activation.

### Staves
- [ ] `[NEW]` Staff tracks single preparer per day; only that character expends charges.
- [ ] `[NEW]` Staff spell lists by level; higher versions include all lower-version spells.
- [ ] `[NEW]` Staff usable as melee weapon; can have fundamental runes (not property runes).
- [ ] `[NEW]` Daily prep grants charges = preparer's highest-level spell slot level.
- [ ] `[NEW]` Only one staff prepared per day per character.
- [ ] `[NEW]` Charges expire after 24 hours; re-preparing removes remaining charges.
- [ ] `[NEW]` Prepared caster: sacrifice spell slot during prep → add charges equal to slot level (max once/day).
- [ ] `[NEW]` Spontaneous caster: 1 charge + 1 spell slot to cast staff spell ≤ slot level.
- [ ] `[NEW]` Casting from staff: spell on list, charges = spell level, uses caster's attack/DC/tradition.
- [ ] `[NEW]` Material components, spell costs, focus requirements apply from staff.
- [ ] `[NEW]` Cantrip from staff: 0 charges; heightened to caster's cantrip level.

### Wands
- [ ] `[NEW]` Wand contains fixed spell at fixed level; no cantrips/focus/rituals.
- [ ] `[NEW]` Wand daily use limit = 1 cast; spell must be on caster's spell list.
- [ ] `[NEW]` Wand uses caster's attack/DC and tradition; material → somatic; focus/cost still apply.
- [ ] `[NEW]` Overcharge: one attempt per wand per day after daily cast; flat DC 10 → success = Broken, failure = Destroyed.
- [ ] `[NEW]` Overcharge already-overcharged wand: automatically Destroyed, no spell cast.
- [ ] `[NEW]` Wand level/price derived from stored spell level.

### Snares
- [ ] `[NEW]` Snares require Snare Crafting feat and snare kit.
- [ ] `[NEW]` Snares occupy one 5-ft square; cannot be relocated.
- [ ] `[NEW]` Quick crafting: 1 minute at full price; discounted via downtime Craft activity.
- [ ] `[NEW]` Detection DC = creator's Crafting DC; Disable DC = same (Thievery).
- [ ] `[NEW]` Detection/disable minimum proficiency gates: Trained/Expert/Master based on creator's Crafting proficiency.
- [ ] `[NEW]` Expert+ Crafter snares: only found by actively-searching creatures.
- [ ] `[NEW]` Creator disarms own snare: 1 Interact action, adjacent.

### Worn Items
- [ ] `[NEW]` Worn item slot uniqueness enforced: only one item per "worn [slot]" at a time.
- [ ] `[NEW]` Rings have no slot limit (multiple rings allowed).
- [ ] `[NEW]` Explorer's clothing can be etched with armor runes even though not light/medium/heavy armor.
- [ ] `[NEW]` Apex items: +2 to specified ability score OR raise to 18, whichever is higher.
- [ ] `[NEW]` Only one apex benefit active at a time; investing second apex item provides no additional score increase (other effects still apply).
- [ ] `[NEW]` Apex benefit fires only on first investment within a 24-hour window.
- [ ] `[NEW]` Apex score increase immediately applies all downstream effects (HP from Con, skill training from Int, etc.).

---

## Edge Cases
- [ ] `[NEW]` 11th invested item: no magical effect; item not destroyed; no error to player unless explicitly checked.
- [ ] `[NEW]` Wand overcharge on already-overcharged: auto-Destroyed, no spell fired.
- [ ] `[NEW]` Two identical property runes: only higher level applies (unless energy-resistant/different types).

## Failure Modes
- [ ] `[TEST-ONLY]` Casting from scroll without spell on list: blocked.
- [ ] `[TEST-ONLY]` Staff prepared by one character and accessed by another: second character blocked from expending charges.
- [ ] `[TEST-ONLY]` Mutagen countercasting: system attempts counteract (does not silently stack).

## Security acceptance criteria
- Security AC exemption: game-mechanic magic item and alchemical system; no new routes or user-facing input beyond existing character creation, inventory, and encounter phase forms
- Agent: qa-dungeoncrawler
- Status: pending
