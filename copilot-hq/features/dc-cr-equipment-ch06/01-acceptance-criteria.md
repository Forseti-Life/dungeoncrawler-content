# Acceptance Criteria: dc-cr-equipment-ch06

## Gap analysis reference
- DB sections: core/ch06 — Weapons (50), Carrying and Item Rules (32), Adventuring Gear (28), Armor (16), Currency and Economy (6), Shields (7), Services and Economy (5), Animals and Mounts (5), Alchemical Gear (4), Magical Gear (4), Formulas (3), Class Starting Kits (1)
- Depends on: dc-cr-skill-system ✓, dc-cr-character-leveling

---

## Happy Path

### Currency and Economy
- [ ] `[NEW]` Currency supports cp, sp, gp, pp with standard exchange rates (10cp=1sp, 10sp=1gp, 10gp=1pp).
- [ ] `[NEW]` Items sell for half purchase price; exceptions (coins, gems, art objects, raw materials) sell at full price.
- [ ] `[NEW]` New character starting wealth = 15 gp (150 sp).
- [ ] `[NEW]` Items with Price "—" cannot be purchased; Price 0 items are free.
- [ ] `[NEW]` Items carry a rarity flag (common/uncommon/rare); uncommon requires explicit access grant; character creation abilities may grant access.

### Item States and Bulk
- [ ] `[NEW]` Three item states modeled: held, worn, stowed; abilities may require a specific state.
- [ ] `[NEW]` Each item has a Bulk value (numeric, L, or —); 10 Light = 1 Bulk (fractions rounded down); negligible items don't count.
- [ ] `[NEW]` Carrying limit without penalty = 5 + Strength modifier Bulk.
- [ ] `[NEW]` Over limit: encumbered → clumsy 1 + –10 ft Speed (minimum 5 ft).
- [ ] `[NEW]` Maximum carry = 10 + Strength modifier Bulk (hard cap).
- [ ] `[NEW]` Coins: 1,000 = 1 Bulk (rounded down).
- [ ] `[NEW]` Creature Bulk limits scale by size tier per table 6-19.
- [ ] `[NEW]` Item Bulk and Price scale with size of creature they're made for (table 6-20).
- [ ] `[NEW]` Small/Medium wielding Large weapons: clumsy 1; no extra damage benefit.
- [ ] `[NEW]` Large armor cannot be worn by Small/Medium creatures.
- [ ] `[NEW]` Dragging: halves effective Bulk, requires 2 hands, slow movement.
- [ ] `[NEW]` Changing equipment costs actions per Table 6-2.
- [ ] `[NEW]` Tool sets worn = accessed free as part of use action; worn tool Bulk limit 2 Bulk.

### Item Damage / Hardness / HP
- [ ] `[NEW]` Items track Hardness, current HP, and Broken Threshold (BT).
- [ ] `[NEW]` Damage to item = max(0, damage – Hardness) subtracted from item HP.
- [ ] `[NEW]` Item at HP ≤ BT = broken condition; item at HP = 0 = destroyed.
- [ ] `[NEW]` Broken items can't be used normally and grant no bonuses.
- [ ] `[NEW]` Broken armor exception: still grants AC bonus but applies status penalty (–1/–2/–3 by armor category); still imposes all armor penalties.
- [ ] `[NEW]` Characters normally don't take item damage from being hit (exceptions: Shield Block, special monster abilities).
- [ ] `[NEW]` Objects immune to listed damage types, effects, and conditions by default.
- [ ] `[NEW]` Shoddy quality flag: –2 item penalty to all attacks/checks; armor check penalty worsened by –2; HP and BT at half normal.

### Item Level
- [ ] `[NEW]` Every item has an item level (default = 0 if not listed).
- [ ] `[NEW]` Craft action enforces item level ≤ character level.

### Armor
- [ ] `[NEW]` AC formula: 10 + min(Dex mod, Dex Cap) + proficiency bonus + item bonus + other bonuses + penalties.
- [ ] `[NEW]` Armor proficiency tracked by category (unarmored/light/medium/heavy).
- [ ] `[NEW]` Donning light armor: 1 minute; medium/heavy: 5 minutes; removing: 1 minute. Characters caught without armor are vulnerable until fully donned.
- [ ] `[NEW]` Armor tracks: Price, AC bonus, Dex Cap, check penalty, speed penalty, Strength threshold, Bulk, Group, Traits.
- [ ] `[NEW]` Check penalty exempted for attack-trait actions.
- [ ] `[NEW]` Meeting Strength threshold removes check penalty + reduces speed penalty by 5 ft.
- [ ] `[NEW]` All 11 standard armors + unarmored/explorer's clothing implemented with correct statistics.
- [ ] `[NEW]` Full plate and half plate include undercoat padded armor and gauntlets in price.
- [ ] `[NEW]` All 4 armor traits implemented with listed behaviors.
- [ ] `[NEW]` Armor specialization effects gated behind class features (not automatic).
- [ ] `[NEW]` All four armor group specialization effects scale with potency rune value.
- [ ] `[NEW]` Chain group specialization: crit-damage reduction (floor = pre-doubling roll).
- [ ] `[NEW]` Composite/Leather/Plate group specializations: persistent resistances.
- [ ] `[NEW]` Armor HP/Hardness/BT matches material type.

### Shields
- [ ] `[NEW]` Shield bonus = circumstance bonus to AC (not item bonus); applies only when raised via Raise a Shield action.
- [ ] `[NEW]` Speed penalty from shield applies whenever held (not only when raised).
- [ ] `[NEW]` Buckler: strapped to forearm; doesn't occupy hand; can Raise Shield with hand free or holding a light non-weapon.
- [ ] `[NEW]` Tower Shield + Take Cover: AC bonus increases to +4; provides standard cover to nearby allies.
- [ ] `[NEW]` Shield Block: reduce damage by shield's Hardness; remainder damages both character and shield.
- [ ] `[NEW]` All 4 shield types implemented with correct statistics.
- [ ] `[NEW]` Shield attacks (bash/boss/spikes) use weapon rules; shields can't have runes (boss/spikes can).

### Weapons
- [ ] `[NEW]` Melee attacks use Str modifier (or Dex modifier for finesse weapons); ranged use Dex; thrown use Str (full); propulsive use half Str (positive) or full Str (negative).
- [ ] `[NEW]` Multiple Attack Penalty (MAP): 2nd attack –5; 3rd+ –10. Agile reduces to –4/–8. MAP does not apply to off-turn attacks (reactions).
- [ ] `[NEW]` Critical hit = double all damage components; Striking/Greater/Major runes add 1/2/3 extra weapon dice.
- [ ] `[NEW]` Unarmed attacks are distinct from weapons; default fist: 1d4 B, agile, finesse, nonlethal, unarmed traits, Brawling group.
- [ ] `[NEW]` Improvised weapons: simple category, –2 item penalty, GM-adjudicated damage.
- [ ] `[NEW]` Ranged beyond 1st increment: –2 per additional increment; impossible beyond 6th.
- [ ] `[NEW]` Reload value = Interact actions needed; 0 = combined draw+fire.
- [ ] `[NEW]` Damage die progression: d4→d6→d8→d10→d12; maximum d12; only one increase allowed.
- [ ] `[NEW]` All listed melee weapons implemented with correct damage dice, Bulk, group, and traits.
- [ ] `[NEW]` Bows use 1+ hand notation (held one hand, second hand free to fire).
- [ ] `[NEW]` All 14 weapon group critical specialization effects implemented; gated behind class features.
- [ ] `[NEW]` All listed weapon traits implemented with correct mechanical behaviors.

### Adventuring Gear
- [ ] `[NEW]` Adventurer's Pack: backpack + bedroll + chalk (×10) + flint & steel + rope (50 ft) + 2 weeks rations + soap + torches (×5) + waterskin; 15 sp, 1 Bulk.
- [ ] `[NEW]` Healer's Tools: required for First Aid / Treat Disease / Treat Poison / Treat Wounds; expanded kit adds +1 item bonus; can be worn.
- [ ] `[NEW]` Thieves' Tools: required for Pick Lock / Disable Device; infiltrator version +1 item bonus; can be worn; broken picks replaced without Repair action.
- [ ] `[NEW]` Disguise Kit: required for Impersonate; elite version +1 item bonus; can be worn.
- [ ] `[NEW]` Climbing Kit: allows wall attachment at half Speed; extreme version +1 item bonus to Climb.
- [ ] `[NEW]` Crowbar: removes –2 item penalty to Force Open; levered version +1 item bonus.
- [ ] `[NEW]` Caltrops: deployed in adjacent square; first creature entering must succeed DC 14 Acrobatics or take 1d4 P + 1 persistent bleed + –5 ft Speed; Interact to remove.
- [ ] `[NEW]` Compass: without one, –2 penalty to Sense Direction; lensatic version +1 item bonus.
- [ ] `[NEW]` Repair Kit: required to Repair items; superb version +1 item bonus; can be worn.
- [ ] `[NEW]` Snare Kit: required to Craft snares; specialist version +1 item bonus.
- [ ] `[NEW]` Spellbook and Formula Book: required by wizards/alchemists; each holds up to 100 entries.
- [ ] `[NEW]` Grappling Hook: thrown with attack roll (secret, typically DC 20); crit fail appears anchored but falls midway.
- [ ] `[NEW]` Oil: fuel for lanterns (6 hrs/pint); can be thrown as fire bomb (1d6 fire, DC 10 ignite).
- [ ] `[NEW]` Lock: DC and successes required by quality implemented.
- [ ] `[NEW]` Manacles: leg manacles: –15 ft Speed; wrist manacles: DC 5 flat check on manipulate actions. Escape DCs by quality.
- [ ] `[NEW]` Religious Symbol: divine focus for divine spellcasters; must be held in hand.

### Alchemical and Magical Gear (1st-Level Access)
- [ ] `[NEW]` Alchemical bombs: consumable; 20 ft range; Bomb weapon group; splash damage rules.
- [ ] `[NEW]` Elixirs: consumable; drinking as activation.
- [ ] `[NEW]` Holy/unholy water: consumable magic items available in appropriate settlements.
- [ ] `[NEW]` Potions: consumable magic items; drinking is activation method.
- [ ] `[NEW]` Scrolls: consumable; single-use spell container; common 1st-level = 4 gp.
- [ ] `[NEW]` Talismans: affixed to specific gear slot; single-use activation.

### Formulas
- [ ] `[NEW]` Formulas must be purchased, copied, or reverse-engineered; reverse engineering: disassemble (half-price materials) + Craft check vs item DC.
- [ ] `[NEW]` Formula prices by item level per Table 6-13.
- [ ] `[NEW]` Basic Crafter's Book: contains all 0-level common formulas.

### Services and Economy
- [ ] `[NEW]` Hirelings: level 0; unskilled = +0 all skills; skilled = +4 specialty, +0 otherwise; rates double in danger.
- [ ] `[NEW]` Spellcasting services: uncommon; cost = table price + material cost; surcharges for uncommon/long-cast spells.
- [ ] `[NEW]` Subsist action can fulfill subsistence standard (no coin cost).

### Animals and Mounts
- [ ] `[NEW]` Non-combat-trained animals panic in combat (frightened 4 + fleeing).
- [ ] `[NEW]` Combat-trained animals (warhorse, warpony) do not panic.
- [ ] `[NEW]` Animals have purchase and rental prices per Table 6-17.
- [ ] `[NEW]` Barding: uses armor rules; Strength modifier-based; no runes; Price/Bulk scale by size.
- [ ] `[NEW]` All animals trained in light barding; combat-trained also trained in heavy barding.

### Class Starting Kits
- [ ] `[NEW]` System supports character creation starting kits as pre-configured equipment bundles.

---

## Edge Cases
- [ ] `[NEW]` Over-encumbered state: minimum Speed 5 ft (not 0).
- [ ] `[NEW]` Broken armor: status penalty applies on top of existing AC; check/speed penalties still apply.
- [ ] `[NEW]` Agile weapon MAP reduction: only applies to that specific weapon's attacks.

## Failure Modes
- [ ] `[TEST-ONLY]` Carrying beyond hard cap (10 + Str Bulk): blocked outright.
- [ ] `[TEST-ONLY]` Shield bonus is circumstance (not item); does not stack with other circumstance bonuses to AC.
- [ ] `[TEST-ONLY]` Improvised weapon attacks: –2 penalty applied, not blocked.
- [ ] `[TEST-ONLY]` Bulk of coins calculated correctly at 1000=1 Bulk (floor, not ceil).

## Security acceptance criteria
- Security AC exemption: game-mechanic equipment system logic; no new routes or user-facing input beyond existing character creation, inventory management, and encounter forms
