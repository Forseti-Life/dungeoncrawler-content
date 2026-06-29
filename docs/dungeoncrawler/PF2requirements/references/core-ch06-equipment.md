# PF2E Core Rulebook — Chapter 6: Equipment
## Requirements Extraction

---

## SECTION: Currency and Economy

### Paragraph — Coin Values

> Four coin denominations: copper piece (cp), silver piece (sp), gold piece (gp), platinum piece (pp).
> 1 pp = 10 gp = 100 sp = 1,000 cp. The standard unit for commoners is the silver piece.

- REQ: System currency must support at minimum: cp, sp, gp, pp with standard exchange rates
- REQ: Most items sell for half their purchase Price; exceptions: coins, gems, art objects, raw materials sell at full Price
- REQ: Starting wealth for new characters: 15 gp (150 sp)
- REQ: Items with Price "—" cannot be purchased; Price 0 items are normally free

### Paragraph — Rarity and Access

> Common items are available for purchase in most cities. Uncommon items require special access from character creation abilities or GM permission.

- REQ: Items must carry a rarity flag (common/uncommon/rare); uncommon requires explicit access grant
- REQ: Character creation abilities may grant access to otherwise unavailable items

---

## SECTION: Carrying and Item Rules

### Paragraph — Item Carrying Modes

> Characters carry items in three ways: held (in hands, typically 2 max), worn (accessible, draw/stow with Interact), stowed (in backpack, requires remove backpack + Interact). Drawing a worn item: 1 Interact. Retrieving from backpack: requires removing pack (Interact) then second Interact.

- REQ: System must model three item states: held, worn, stowed
- REQ: Changing equipment costs actions per Table 6-2:
  - Draw/put away worn item: 1-2 hands, 1 Interact
  - Pass item to/from willing creature: 1-2 hands, 1 Interact
  - Drop item: 1-2 hands, Release action
  - Detach strapped shield/item: 1 hand, 1 Interact
  - Remove hand from item: 2 hands, Release
  - Add hand to item: 2 hands, Interact
  - Retrieve from backpack: 2 hands, 1 Interact (after removing pack)

### Paragraph — Bulk System

> Bulk measures how difficult an item is to handle (size, weight, awkwardness). Values: numeric (1, 2…), Light (L), Negligible (—). Ten light items = 1 Bulk (round down fractions).
> General rule: 5–10 lbs = 1 Bulk; few ounces or less = negligible; between = light.
> Bulk limits: carry up to 5 + Str modifier without penalty; encumbered if more; max 10 + Str modifier.
> Encumbered: clumsy 1 + –10 ft to all Speeds (minimum 5 ft).

- REQ: Each item must have a Bulk value (numeric, L, or —)
- REQ: 10 Light items = 1 Bulk; fractions rounded down
- REQ: Negligible items don't count toward Bulk (unless enormous quantities)
- REQ: Carrying limit without penalty = 5 + Strength modifier Bulk
- REQ: Over limit → encumbered: clumsy 1 + –10 ft Speed (minimum 5 ft)
- REQ: Maximum carry = 10 + Strength modifier Bulk (can't exceed)

### Paragraph — Bulk of Coins and Creatures

> 1,000 coins of any denomination = 1 Bulk (round down fractions of 1,000; 999 coins = 0 Bulk).
> Creature Bulk by size: Tiny=1, Small=3, Medium=6, Large=12, Huge=24, Gargantuan=48.
> Dragging: treat object's Bulk as half; uses both hands; ~50 ft/min.

- REQ: Coins have Bulk (1,000 = 1 Bulk, round down)
- REQ: Creature Bulk for carrying purposes scales by size tier
- REQ: Dragging halves effective Bulk; requires 2 hands; slow movement

### Paragraph — Wielding Items

> Wielding = holding in the number of hands needed to use effectively. Being wielded ≠ just carrying.

- REQ: Distinguish between held, wielded, and worn item states; abilities may require one specific state

### Paragraph — Item Damage (Hardness, HP, BT)

> Every item has Hardness. Damage taken is reduced by Hardness; remainder reduces item HP. At or below Broken Threshold (BT) → broken condition. At 0 HP → destroyed (can't be Repaired).
> Broken: can't be used normally, no bonuses granted — EXCEPT broken armor still grants item bonus to AC but adds status penalty (light: –1; medium: –2; heavy: –3). Broken armor still imposes its Dex cap, check penalty, etc.
> An effect that automatically breaks an item reduces HP to BT if it had more HP.

- REQ: Items must track Hardness, current HP, Broken Threshold
- REQ: Damage to item = max(0, damage – Hardness) subtracted from item HP
- REQ: Item at HP ≤ BT gains broken condition; item at HP = 0 is destroyed
- REQ: Broken items cannot be used normally and grant no bonuses
- REQ: Broken armor exception: still grants AC bonus, but adds status penalty (–1/–2/–3 by category)
- REQ: Broken armor still imposes all armor penalties (Dex cap, check penalty, speed penalty)
- REQ: Characters normally don't take item damage from being hit; exceptions: Shield Block, special monster abilities

### Paragraph — Object Immunities

> Inanimate objects immune to: bleed, death effects, disease, healing, mental effects, necromancy, nonlethal attacks, poison; and conditions: doomed, drained, fatigued, paralyzed, sickened, unconscious. Objects with a mind are not immune to mental effects.

- REQ: Objects must be immune to the listed damage types, effects, and conditions by default
- REQ: GM may grant additional object immunities case-by-case

### Paragraph — Shoddy Items

> Shoddy items are improvised or poor quality. Not available for purchase except in desperate communities; cost half Price, can't be sold. –2 item penalty to attacks and checks; –2 worsens armor check penalty; HP and BT each halved.

- REQ: Shoddy item quality flag: –2 item penalty to all attacks/checks; armor check penalty worsened by –2
- REQ: Shoddy item HP and BT = half normal

### Paragraph — Item Levels

> All items have a level (0 if not listed). Characters can't Craft items higher level than their own. All items in Ch.6 are level 0 unless noted. GMs should be cautious about giving characters access to items far above their level.

- REQ: Every item must have an item level; default = 0 if not listed
- REQ: Craft action: item level must be ≤ character level
- REQ: Item level gates access through GM guidance even when not crafted

### Paragraph — Items and Sizes (Table 6-19/6-20)

> Bulk limits scale by creature size. Large = ×2 limits; Huge = ×4; Gargantuan = ×8; Tiny = ÷2.
> Large treats 1 Bulk items as Light; Huge treats Light as Negligible.
> Items sized for other creatures have different Bulk and Price (×2/×4/×8 by size tier; Tiny: half Bulk, same Price).

- REQ: Bulk limits must scale with creature size per table 6-19
- REQ: Item Bulk and Price scale with size of creature they're made for (table 6-20)
- REQ: Small/Medium creatures wielding Large weapons: clumsy 1 (no extra damage benefit)
- REQ: Large armor cannot be worn by Small/Medium creatures

### Paragraph — Wearing Tools

> Tools (alchemist's tools, healer's tools, etc.) can be worn. Worn tools: draw and replace as part of the action using them. Can wear up to 2 Bulk of tools; excess must be stowed/drawn separately.

- REQ: Tool sets can be worn; worn tools are accessed for free as part of their use action
- REQ: Worn tool Bulk limit: 2 Bulk total

---

## SECTION: Armor

### Paragraph — Armor Class Formula

> AC = 10 + Dexterity modifier (up to Dex Cap) + proficiency bonus + armor item bonus to AC + other bonuses + penalties.
> Use proficiency in the category (light/medium/heavy) or specific armor type worn. No armor → use unarmored defense proficiency.

- REQ: AC formula: 10 + min(Dex mod, Dex Cap) + proficiency bonus + item bonus + other bonuses + penalties
- REQ: Armor proficiency is tracked by category (unarmored/light/medium/heavy)
- REQ: Dex Cap caps the Dexterity bonus applied to AC

### Paragraph — Donning and Removing Armor

> Donning/removing armor is an activity of many Interact actions: light armor = 1 minute; medium/heavy = 5 minutes; remove any = 1 minute.

- REQ: Donning light armor: 1 minute; medium/heavy: 5 minutes; removing: 1 minute
- REQ: Characters caught without armor are vulnerable until the full donning time is spent

### Paragraph — Armor Statistics

> **Check Penalty**: Str- and Dex-based skill checks (except attack trait); negated if Str ≥ Strength threshold.
> **Speed Penalty**: Applies to all Speeds; reduced by 5 ft if Str ≥ threshold (removing –5 ft penalty entirely if threshold met).
> **Strength threshold**: If Str ≥ value → no check penalty and Speed penalty reduced by 5 ft.
> **Bulk**: Bulk when worn; +1 Bulk if carried (or 1 Bulk total if armor is light Bulk).
> **Group**: Armor group determines armor specialization effects.
> **Dex Cap**: Maximum Dex bonus to AC.
> **AC Bonus**: Item bonus added to AC when worn.

- REQ: Armor must track: Price, AC bonus, Dex Cap, check penalty, speed penalty, Strength threshold, Bulk, Group, Traits
- REQ: Check penalty exempted for actions with attack trait
- REQ: Meeting Strength threshold: removes check penalty + reduces speed penalty by 5 ft

### Paragraph — Armor Table (6-3 and 6-4)

> **Unarmored Defense:**
> - No armor: +0 AC, no Dex Cap, no penalties, — Bulk
> - Explorer's clothing: +0 AC, +5 Dex Cap, L Bulk, Cloth group, Comfort trait (can have potency rune)

> **Light Armor:**
> | Armor | Price | AC | Dex Cap | Check | Speed | Str | Bulk | Group | Traits |
> |-------|-------|----|---------|-------|-------|-----|------|-------|--------|
> | Padded | 2 sp | +1 | +3 | 0 | 0 | 10 | L | Cloth | Comfort |
> | Leather | 2 gp | +1 | +4 | –1 | 0 | 10 | 1 | Leather | — |
> | Studded leather | 3 gp | +2 | +3 | –1 | 0 | 12 | 1 | Leather | — |
> | Chain shirt | 5 gp | +2 | +3 | –1 | 0 | 12 | 1 | Chain | Flexible, noisy |

> **Medium Armor:**
> | Armor | Price | AC | Dex Cap | Check | Speed | Str | Bulk | Group | Traits |
> |-------|-------|----|---------|-------|-------|-----|------|-------|--------|
> | Hide | 2 gp | +3 | +2 | –2 | –5 ft | 14 | 2 | Leather | — |
> | Scale mail | 4 gp | +3 | +2 | –2 | –5 ft | 14 | 2 | Composite | — |
> | Chain mail | 6 gp | +4 | +1 | –2 | –5 ft | 16 | 2 | Chain | Flexible, noisy |
> | Breastplate | 8 gp | +4 | +1 | –2 | –5 ft | 16 | 2 | Plate | — |

> **Heavy Armor:**
> | Armor | Price | AC | Dex Cap | Check | Speed | Str | Bulk | Group | Traits |
> |-------|-------|----|---------|-------|-------|-----|------|-------|--------|
> | Splint mail | 13 gp | +5 | +1 | –3 | –10 ft | 16 | 3 | Composite | — |
> | Half plate | 18 gp | +5 | +1 | –3 | –10 ft | 16 | 3 | Plate | — |
> | Full plate | 30 gp | +6 | +0 | –3 | –10 ft | 18 | 4 | Plate | Bulwark |

- REQ: System must implement all 11 standard armors + unarmored/explorer's clothing with listed statistics
- REQ: Full plate and half plate include undercoat padded armor and gauntlets in price

### Paragraph — Armor Traits

> **Bulwark**: On Reflex saves vs damaging effects, add +3 modifier instead of Dex modifier.
> **Comfort**: Can rest normally while wearing; suitable for sleep.
> **Flexible**: Check penalty doesn't apply to Acrobatics or Athletics checks.
> **Noisy**: Check penalty applies to Stealth even if Strength threshold is met.

- REQ: System must implement all 4 armor traits with listed behaviors

### Paragraph — Armor Specialization Effects (Medium/Heavy Only)

> Certain class features grant armor specialization effects based on the armor's group:
> **Chain**: Critical hits reduce damage by 4 + potency rune value (medium) or 6 + potency rune value (heavy); minimum = pre-doubling damage.
> **Composite**: Resistance to piercing = 1 + potency rune value (medium) or 2 + potency rune value (heavy).
> **Leather**: Resistance to bludgeoning = 1 + potency rune value (medium) or 2 + potency rune value (heavy).
> **Plate**: Resistance to slashing = 1 + potency rune value (medium) or 2 + potency rune value (heavy).

- REQ: Armor specialization effects are gated behind class features; not automatically applied
- REQ: All four group effects scale with potency rune value on the armor
- REQ: Chain specialization is crit-damage reduction; Composite/Leather/Plate are persistent resistances
- REQ: Chain reduction floor = pre-doubling roll (damage can't be reduced below what was rolled before crit doubling)

### Paragraph — Armor Hardness (Damage Materials)

> | Material | Hardness | HP | BT |
> |----------|----------|----|----|
> | Cloth (explorer's clothing, padded) | 1 | 4 | 2 |
> | Leather (hide, leather, studded leather) | 4 | 16 | 8 |
> | Metal (chain shirt, breastplate, chain mail, full plate, half plate, scale mail, splint mail) | 9 | 36 | 18 |

- REQ: Armor HP/Hardness/BT must match material type

---

## SECTION: Shields

### Paragraph — Shield Rules

> Must be wielded (one hand) to benefit. Use Raise a Shield action to gain AC bonus as circumstance bonus until start of next turn. Speed penalty applies while holding (whether raised or not).
> Buckler: strapped to forearm; can Raise Shield as long as hand is free (or holding light non-weapon object).
> Tower shield: Raise Shield + Take Cover → +4 circumstance AC. Provides standard cover (not lesser) when raised.
> Shield Block reaction: if available, reduce damage by Hardness; both character and shield take remaining damage.

- REQ: Shield bonus is circumstance bonus to AC (not item bonus); only when raised via Raise a Shield action
- REQ: Speed penalty from shield applies whenever held, not just when raised
- REQ: Buckler: strapped to forearm; doesn't occupy hand; can Raise Shield with hand free or holding light non-weapon
- REQ: Tower shield + Take Cover: AC bonus increases to +4; provides standard cover to nearby allies
- REQ: Shield Block: reduce damage by shield's Hardness; remainder damages both character and shield

### Paragraph — Shield Table (6-5)

> | Shield | Price | AC Bonus | Speed Penalty | Bulk | Hardness | HP (BT) |
> |--------|-------|----------|---------------|------|----------|---------|
> | Buckler | 1 gp | +1 | — | L | 3 | 6 (3) |
> | Wooden shield | 1 gp | +2 | — | 1 | 3 | 12 (6) |
> | Steel shield | 2 gp | +2 | — | 1 | 5 | 20 (10) |
> | Tower shield | 10 gp | +2/+4* | –5 ft | 4 | 5 | 20 (10) |
> *Tower shield: +4 AC requires Raise Shield + Take Cover.

- REQ: System must implement all 4 shield types with listed statistics
- REQ: Shield attacks (shield bash/boss/spikes) use weapon rules; shields can't have runes (boss/spikes can)

---

## SECTION: Weapons

### Paragraph — Attack Roll Formulas

> Melee attack modifier = Strength modifier (or Dexterity for finesse weapons) + proficiency bonus + other bonuses + penalties.
> Ranged attack modifier = Dexterity modifier + proficiency bonus + other bonuses + penalties.
> Potency runes add item bonus to attack rolls.

- REQ: Melee attacks use Str mod (or Dex mod for finesse weapons)
- REQ: Ranged attacks use Dex mod
- REQ: Thrown weapons use Str mod for damage (like melee)
- REQ: Propulsive ranged weapons add half Str mod to damage (full if negative)

### Paragraph — Multiple Attack Penalty

> Second attack on same turn: –5. Third and beyond: –10. Does not apply to reactions (not your turn). Agile weapons: –4/–8 instead.

- REQ: Multiple attack penalty (MAP): 2nd attack –5; 3rd+ –10
- REQ: MAP does not apply to off-turn attacks (reactions)
- REQ: Agile weapon trait reduces MAP to –4/–8

### Paragraph — Damage Rolls

> Hit if attack roll ≥ target's AC. Melee damage = damage die + Str modifier + bonuses. Ranged damage = damage die (no Str mod unless thrown or propulsive).
> Striking runes add extra damage dice (same die size). Weapon specialization adds flat damage.
> Critical hit: natural 20 on die, or exceeds AC by 10 → double all damage.

- REQ: Damage roll = damage die + applicable ability modifier + bonuses + penalties
- REQ: Critical hit = double damage (all components)
- REQ: Striking/greater/major striking runes add 1/2/3 additional weapon damage dice

### Paragraph — Unarmed Attacks

> Fist: 1d4 B, L Bulk, 1 hand, Brawling group, traits: agile, finesse, nonlethal, unarmed. Almost all characters start trained in unarmed attacks. Unarmed attacks are NOT weapons — weapon effects don't apply unless they specifically say so.

- REQ: Unarmed attacks are a distinct category from weapons; weapon-specific effects don't apply to unarmed by default
- REQ: Default unarmed (fist): 1d4 B, agile, finesse, nonlethal, unarmed traits, Brawling group

### Paragraph — Improvised Weapons

> Simple weapons. –2 item penalty to attack rolls. GM determines damage and damage type.

- REQ: Improvised weapons: simple category, –2 item penalty to attacks, GM-adjudicated damage

### Paragraph — Weapon Statistics Columns

> Damage die + type (B/P/S). Range increment (ranged/thrown; –2 per increment beyond first; max 6 increments). Reload (Interact actions to reload; 0 = draw+fire same action; — = throw). Bulk. Hands (1/2/1+). Group. Traits.

- REQ: Ranged attacks beyond 1st range increment: –2 per additional increment; impossible beyond 6th
- REQ: Reload value = number of Interact actions to reload (0 = combined draw+fire)
- REQ: 1+ Hands: hold in one hand but require second hand free to fire/use

### Paragraph — Damage Die Scaling

> Die progression: 1d4 → 1d6 → 1d8 → 1d10 → 1d12 (max). Can't increase beyond 1d12. Can't increase more than once.

- REQ: Damage die increases follow the progression: d4→d6→d8→d10→d12
- REQ: Maximum die size: d12; only one increase allowed

### Paragraph — Weapon Categories

> Simple (lowest damage, anyone proficient), Martial (more damage, trained fighters), Advanced (best traits, rare). Uncommon weapons require access.

- REQ: Three weapon categories: Simple, Martial, Advanced
- REQ: Common vs Uncommon rarity applies to weapons

### Paragraph — Weapon Table 6-6: Unarmed

> Fist: Price —, 1d4 B, L Bulk, 1 hand, Brawling group, agile/finesse/nonlethal/unarmed

### Paragraph — Weapon Table 6-7: Melee Weapons (Summary)

> **Simple Melee:** Club (1d6B), Dagger (1d4P), Gauntlet (1d4B), Light mace (1d4B), Longspear (1d8P), Mace (1d6B), Morningstar (1d6B), Sickle (1d4S), Spear (1d6P), Spiked gauntlet (1d4P), Staff (1d4B)
> **Martial Melee (selection):** Bastard sword (1d8S), Battle axe (1d8S), Bo staff (1d8B), Falchion (1d10S), Flail (1d6B), Glaive (1d8S), Greataxe (1d12S), Greatclub (1d10B), Greatpick (1d10P), Greatsword (1d12S), Guisarme (1d10S), Halberd (1d10P), Hatchet (1d6S), Lance (1d8P), Light hammer (1d6B), Light pick (1d4P), Longsword (1d8S), Main-gauche (1d4P), Maul (1d12B), Pick (1d6P), Ranseur (1d10P), Rapier (1d6P), Sap (1d6B), Scimitar (1d6S), Scythe (1d10S), Shortsword (1d6P), Starknife (1d4P), Trident (1d8P), War flail (1d10B), Warhammer (1d8B), Whip (1d4S)

- REQ: System must implement all listed melee weapons with correct damage dice, Bulk, group, and traits

### Paragraph — Weapon Table 6-8: Ranged Weapons (Summary)

> **Simple Ranged:** Blowgun (1P, 20 ft, reload 1), Crossbow (1d8P, 120 ft, reload 1), Dart (1d4P, 20 ft, thrown), Hand crossbow (1d6P, 60 ft, reload 1), Heavy crossbow (1d10P, 120 ft, reload 2), Javelin (1d6P, 30 ft, thrown), Sling (1d6B, 50 ft, reload 1)
> **Martial Ranged:** Composite longbow (1d8P, 100 ft, reload 0), Composite shortbow (1d6P, 60 ft, reload 0), Longbow (1d8P, 100 ft, reload 0), Shortbow (1d6P, 60 ft, reload 0), Alchemical bomb (varies, 20 ft, reload 0)

- REQ: Bows use 1+ hand notation: held in one hand, second hand free required to fire

### Paragraph — Weapon Traits (All)

> All weapon traits and their mechanical effects:

- REQ: **Agile** — MAP is –4/–8 instead of –5/–10
- REQ: **Attached** — must be combined with another piece of gear; can't be used without it
- REQ: **Backstabber** — +1 precision damage vs flat-footed targets (+2 if weapon is +3)
- REQ: **Backswing** — after missing: +1 circumstance to next attack with this weapon this turn
- REQ: **Deadly [die]** — critical hit: add listed die of damage after doubling (2 dice if greater striking; 3 if major)
- REQ: **Disarm** — can use weapon to Disarm even without free hand; uses weapon reach + item bonus; crit fail = drop weapon to treat as fail
- REQ: **Dwarf/Elf/Gnome/Goblin/Halfling/Monk/Orc** — ancestry/culture affiliation traits (no mechanical effect unless class feature references them)
- REQ: **Fatal [die]** — crit: weapon damage die increases to listed die size + one additional die of listed size
- REQ: **Finesse** — use Dex modifier instead of Str for melee attack rolls (still use Str for damage)
- REQ: **Forceful** — 2nd attack with this weapon this turn: bonus to damage = number of weapon damage dice; subsequent attacks: 2× weapon damage dice bonus
- REQ: **Free-Hand** — doesn't occupy hand; can hold other items in same hand; can't attack with if hand is occupied; can't be Disarmed; counts as free hand for free-hand requirements
- REQ: **Grapple** — can Grapple without free hand using weapon; uses weapon reach + item bonus; crit fail = drop weapon for fail
- REQ: **Jousting [die]** — mounted + moved ≥10 ft before attack: +circumstance to damage = number of damage dice; may be wielded one-handed while mounted (changes die to listed value)
- REQ: **Nonlethal** — attacks are nonlethal by default; can make lethal attack at –2 circumstance penalty
- REQ: **Parry** — spend 1 action while wielding (trained+): +1 circumstance to AC until start of next turn
- REQ: **Propulsive** — add half Str modifier (positive) or full Str modifier (negative) to damage
- REQ: **Reach** — attack creatures up to 10 ft away instead of adjacent; if creature already has reach, adds 5 ft
- REQ: **Shove** — can Shove without free hand using weapon; uses weapon reach + item bonus; crit fail = drop weapon for fail
- REQ: **Sweep** — +1 circumstance to attack if already attacked a different target this turn with this weapon
- REQ: **Thrown [distance]** — can throw as ranged attack; add full Str mod to damage; range increment = listed
- REQ: **Trip** — can Trip without free hand using weapon; uses weapon reach + item bonus; crit fail = drop weapon for fail
- REQ: **Twin** — +circumstance to damage = number of damage dice if you attacked with a different weapon of same type this turn
- REQ: **Two-Hand [die]** — wielding two-handed changes weapon damage die to listed size
- REQ: **Unarmed** — part of body; can't be Disarmed; doesn't occupy hand; works like free-hand weapon
- REQ: **Versatile [type]** — choose alternate damage type each attack
- REQ: **Volley [distance]** — –2 penalty to attacks against targets within listed range

### Paragraph — Critical Specialization Effects

> Class features can grant additional effects on weapon critical hits based on weapon group:
> **Axe**: Hit one adjacent creature if its AC < attack roll result; deal weapon damage die result (not doubled, no bonuses).
> **Bomb**: Splash radius increases to 10 ft.
> **Bow**: Target adjacent to a surface becomes pinned (immobilized; DC 10 Athletics to free, Interact action).
> **Brawling**: Target must succeed Fort save vs class DC or slowed 1 until end of next turn.
> **Club**: Push target up to 10 ft (forced movement, player chooses distance).
> **Dart**: Target takes 1d6 persistent bleed damage (+ item bonus from potency rune).
> **Flail**: Target knocked prone.
> **Hammer**: Target knocked prone.
> **Knife**: Target takes 1d6 persistent bleed damage (+ item bonus from potency rune).
> **Pick**: Target takes 2 additional damage per weapon damage die.
> **Polearm**: Target moved 5 ft in direction of attacker's choice (forced movement).
> **Shield**: Target knocked 5 ft away (forced movement).
> **Sling**: Target must succeed Fort save vs class DC or stunned 1.
> **Spear**: Target becomes clumsy 1 until start of attacker's next turn.
> **Sword**: Target becomes flat-footed until start of attacker's next turn.

- REQ: Critical specialization effects are gated behind class features; can always choose not to apply
- REQ: All 14 weapon group crit effects must be implemented per group

---

## SECTION: Adventuring Gear

### Paragraph — Key Gear Items and Rules

> Items requiring specific rules notes:

- REQ: **Adventurer's Pack** (15 sp, 1 Bulk): backpack + bedroll + 10 chalk + flint&steel + 50 ft rope + 2 weeks rations + soap + 5 torches + waterskin
- REQ: **Alchemist's Lab** (5 gp, 6 Bulk): required for Crafting alchemical items in downtime. Expanded version (+1 item bonus)
- REQ: **Artisan's Tools** (4 gp, 2 Bulk): required for Craft skill. Sterling version: +1 item bonus. Different sets for different crafts
- REQ: **Backpack** (1 sp, — Bulk when worn): holds 4 Bulk; first 2 Bulk doesn't count against limits. If carried/stowed: L Bulk
- REQ: **Basic Crafter's Book** (1 sp): contains formulas for all 0-level common items in Ch.6
- REQ: **Caltrops** (3 sp, L Bulk): scatter in adjacent square (Interact). First creature entering must succeed DC 14 Acrobatics or take 1d4 P + 1 persistent bleed + –5 ft Speed. Interact to remove = lower bleed DC. One-time use per deployment
- REQ: **Candle**: dim light 10 ft radius for 8 hours
- REQ: **Climbing Kit** (5 sp): allows attaching to wall (half Speed, min 5 ft); DC 5 flat check to prevent fall on crit fail. Extreme kit: +1 item bonus to Climb
- REQ: **Compass** (1 gp): no bonus; without compass –2 penalty to Sense Direction. Lensatic: +1 item bonus
- REQ: **Crowbar** (5 sp): without crowbar, –2 item penalty to Force Open. Levered crowbar: +1 item bonus
- REQ: **Disguise Kit** (2 gp): required for Impersonate. Elite kit: +1 item bonus. Can be worn
- REQ: **Flint and Steel**: lighting fire requires ≥3 actions (too slow for encounter use)
- REQ: **Formula Book** (1 gp): holds up to 100 formulas. Blank; formulas can also be kept on parchment/tablets
- REQ: **Grappling Hook** (1 sp): throw + attack roll (secret, typically DC 20) to anchor; crit fail appears to hold but falls partway up
- REQ: **Healer's Tools** (5 gp): required for Administer First Aid, Treat Disease, Treat Poison, Treat Wounds. Expanded: +1 item bonus. Can be worn
- REQ: **Lantern — Bull's-eye** (1 gp): bright 60 ft cone + dim next 60 ft; 1 pint oil/6 hours
- REQ: **Lantern — Hooded** (7 sp): bright 30 ft radius + dim next 30 ft; 1 pint oil/6 hours; shutters = Interact to open/close
- REQ: **Lock**: DC and successes required by quality:
  - Poor (2 sp): 2 successes at DC 15
  - Simple (2 gp): 3 successes at DC 20
  - Average (15 gp): 4 successes at DC 25
  - Good (200 gp): 5 successes at DC 30
  - Superior (4,500 gp): 6 successes at DC 40
- REQ: **Manacles**: PC manacled at legs: –15 ft Speed; wrists bound: DC 5 flat check on manipulate actions (fail = action lost). Escape DCs per quality:
  - Poor: 2 successes at DC 17; Simple: DC 22; Average: DC 27; Good: DC 32; Superior: DC 42
- REQ: **Material Component Pouch** (5 sp): holds components for spells requiring them; refilled during daily prep
- REQ: **Oil** (1 cp): fuel for lanterns (6 hours/pint); can be thrown as bomb (Interact to prep + ranged attack; DC 10 flat check to ignite; 1d6 fire damage)
- REQ: **Repair Kit** (2 gp): required to Repair items. Superb: +1 item bonus. Can be worn
- REQ: **Religious Symbol** (1 sp wooden / 2 gp silver): divine focus for divine spellcasters; must be held in hand
- REQ: **Rope** (50 ft, 5 sp, L Bulk): standard climbing aid
- REQ: **Snare Kit** (5 gp): required to Craft snares. Specialist kit: +1 item bonus
- REQ: **Spellbook** (1 gp blank): holds up to 100 spells; required by wizards
- REQ: **Thieves' Tools** (3 gp): required for Pick Locks, Disable Devices. Infiltrator: +1 item bonus. Can be worn; broken tools repaired by replacing picks (no Repair action needed)
- REQ: **Torch** (1 cp, L Bulk): bright light 20 ft radius + dim next 20 ft for 1 hour; improvised weapon: 1d4 B + 1 fire

---

## SECTION: Alchemical Gear (1st-Level Access)

### Paragraph — Alchemical Bombs

> All bombs: 3 gp, L Bulk, 20 ft range. Thrown ranged attacks.
> - Acid Flask (lesser): 1 acid + 1d6 persistent acid + 1 acid splash
> - Alchemist's Fire (lesser): 1d8 fire + 1 persistent fire + 1 fire splash
> - Bottled Lightning (lesser): 1d6 electricity + 1 electricity splash + target flat-footed
> - Frost Vial (lesser): 1d6 cold + 1 cold splash + –5 ft Speed to target until end of next turn
> - Tanglefoot Bag (lesser): –10 ft Speed for 1 minute; crit hit = immobilized (Escape to free)
> - Thunderstone (lesser): 1d4 sonic + 1 sonic splash + DC 17 Fort or deafened until end of turn (all creatures within 10 ft)

- REQ: All bombs are consumable weapons with splash damage rules
- REQ: Alchemical bombs use 20 ft range; thrown as ranged attack; Bomb weapon group

### Paragraph — Elixirs

> Consumable items that are drunk for effects.
> - Antidote (lesser): +2 item bonus to Fort saves vs poison for 6 hours (3 gp)
> - Antiplague (lesser): +2 item bonus to Fort saves vs disease for 24 hours, including progression saves (3 gp)
> - Elixir of Life (minor): restore 1d6 HP + +1 item bonus to saves vs disease/poison for 10 minutes (3 gp)

- REQ: Elixirs are consumable; must be drunk (action cost varies by item)

### Paragraph — Alchemical Tools

> - Smokestick (lesser): 5-ft radius smokescreen for 1 minute (3 gp)
> - Sunrod (3 gp): strike on hard surface (Interact) → bright 20 ft radius + dim next 20 ft for 6 hours
> - Tindertwig (2 sp/10): ignite flammable object with 1 Interact action (faster than flint+steel)

### Paragraph — Holy and Unholy Water

> Holy water (3 gp): thrown like bomb; 1d6 good damage to fiends, undead, creatures with weakness to good.
> Unholy water (3 gp): thrown like bomb; 1d6 evil damage to celestials, creatures with weakness to evil.

- REQ: Holy/unholy water are consumable magic items available in appropriate settlements

---

## SECTION: Formulas

### Paragraph — Formula Rules

> Formulas are instructions for Crafting items. Typically need to read the language. Can buy common formulas at listed prices. Copying a formula into a formula book takes 1 hour. Can reverse-engineer an item (disassemble + Craft activity vs same DC; success = gain formula at full cost; fail = raw materials; crit fail = 10% material loss).

- REQ: Formulas must be purchased, copied, or reverse-engineered
- REQ: Reverse engineering: disassemble item (worth half Price in materials) + Craft check vs item DC
- REQ: Formula prices by item level (Table 6-13):

| Level | Price | Level | Price |
|-------|-------|-------|-------|
| 0 | 5 sp (via crafter's book) | 11 | 70 gp |
| 1 | 1 gp | 12 | 100 gp |
| 2 | 2 gp | 13 | 150 gp |
| 3 | 3 gp | 14 | 225 gp |
| 4 | 5 gp | 15 | 325 gp |
| 5 | 8 gp | 16 | 500 gp |
| 6 | 13 gp | 17 | 750 gp |
| 7 | 18 gp | 18 | 1,200 gp |
| 8 | 25 gp | 19 | 2,000 gp |
| 9 | 35 gp | 20 | 3,500 gp |
| 10 | 50 gp | | |

---

## SECTION: Magical Gear (1st-Level Access)

### Paragraph — Potions

> Minor Healing Potion (4 gp, L Bulk): drink to regain 1d8 HP.

- REQ: Potions are consumable magic items; drinking is the activation method

### Paragraph — Scrolls

> Scrolls contain a spell; activating casts the spell without using a spell slot. Destroyed upon use.

- REQ: Scrolls are consumable; single-use spell container
- REQ: Common 1st-level scroll: 4 gp

### Paragraph — Talismans

> Single-use items affixed to armor/weapon/gear; activate for special effect.
> Potency Crystal (4 gp): affix to weapon; activate → +1 item bonus to attacks + second weapon damage die for rest of current turn.

- REQ: Talismans require affixing to a specific gear slot; single-use activation

---

## SECTION: Services and Economy

### Paragraph — Services Table

> Basic services (Table 6-14):
> - Hireling unskilled: 1 sp/day (+0 modifier); skilled: 5 sp/day (+4 modifier in specialty); double rate for adventuring
> - Lodging: floor 3 cp; bed 1 sp; private room 8 sp; extravagant suite 10 gp/day
> - Meals: poor 1 cp; square 3 cp; fine dining 1 gp
> - Transportation per 5 miles: caravan 3 cp; carriage 2 sp; ferry 4 cp; sailing ship 6 cp
> - Stabling: 2 cp/day

- REQ: Hirelings are level 0; unskilled = +0 all skills; skilled = +4 in specialty, +0 otherwise
- REQ: Hireling rates double when adventuring into danger

### Paragraph — Spellcasting Services

> Spellcasting services (uncommon): requires finding a willing spellcaster who knows the spell.
> Cost by spell level (Table 6-15): 1st=3gp; 2nd=7gp; 3rd=18gp; 4th=40gp; 5th=80gp; 6th=160gp; 7th=360gp; 8th=720gp; 9th=1,800gp. Plus any material costs. Uncommon spells ≥+100%. Spells >1 minute to cast: +25%.

- REQ: Spellcasting services are uncommon; harder to find at higher levels
- REQ: Service cost = table price + spell material costs; surcharges apply for uncommon or long-cast spells

### Paragraph — Cost of Living

> Table 6-16:
> | Standard | Week | Month | Year |
> |----------|------|-------|------|
> | Subsistence | 4 sp | 2 gp | 24 gp |
> | Comfortable | 1 gp | 4 gp | 52 gp |
> | Fine | 30 gp | 130 gp | 1,600 gp |
> | Extravagant | 100 gp | 430 gp | 5,200 gp |

- REQ: Subsistence standard can be fulfilled via Subsist action instead of coin cost

---

## SECTION: Animals and Mounts

### Paragraph — Animal Rules

> Most animals become frightened 4 + fleeing when combat begins. Command an Animal (Nature) can prevent fleeing (but doesn't remove frightened). If attacked/damaged: return to frightened 4 + fleeing. Warhorses and warponies are combat-trained and immune to this panic.

- REQ: Non-combat-trained animals panic in combat (frightened 4 + fleeing)
- REQ: Combat-trained animals (warhorse, warpony) do not panic
- REQ: Animals have purchase and rental prices (Table 6-17)

### Paragraph — Animal Prices

> | Animal | Rental/day | Purchase |
> |--------|------------|---------|
> | Dog | 1 cp | 2 sp |
> | Guard dog | 6 cp | 4 gp |
> | Riding dog | — | — |
> | Riding horse | 1 sp | 8 gp |
> | Warhorse (level 2) | 1 gp | 30 gp |
> | Pack animal | 2 cp | 2 gp |
> | Riding pony | 8 cp | 7 gp |
> | Warpony (level 2) | 8 sp | 24 gp |

### Paragraph — Barding

> Special armor for animals. All animals: trained in light barding. Combat-trained: trained in heavy barding.
> Strength listed as modifier (not score). Cannot have magic runes etched. Price and Bulk scale by animal size.
> Light barding (small/medium): 10 gp, AC +1, Dex +5, Check –1, Speed –5, 2 Bulk, Str +3
> Heavy barding (small/medium, level 2): 25 gp, AC +3, Dex +3, Check –3, Speed –10, 4 Bulk, Str +5

- REQ: Barding uses armor rules except: Strength is modifier-based; can't have runes; Price/Bulk scale by size
- REQ: All animals trained in light barding; combat-trained also trained in heavy barding

---

## SECTION: Class Starting Kits (Reference)

### Paragraph — Suggested Starting Gear by Class

> Starting money: 15 gp. Each class has a recommended starting kit:
> - Alchemist: studded leather, dagger, sling+20 bullets, adventurer's pack, alchemist's tools, crafter's book, 2 caltrops (~8 gp)
> - Barbarian: hide armor, 4 javelins, adventurer's pack, grappling hook (~4 gp; weapon choice from greataxe/greatclub/greatsword/axe+shield)
> - Bard: studded leather, dagger, rapier, sling+20 bullets, adventurer's pack, handheld instrument (~7.5 gp)
> - Champion: hide armor, dagger, 4 javelins, adventurer's pack, crowbar, grappling hook (~4.7 gp)
> - Cleric: adventurer's pack, 2 caltrops, wooden religious symbol (~2.2 gp)
> - Druid: leather armor, 4 javelins, longspear, adventurer's pack, holly and mistletoe (~4.4 gp)
> - Fighter: hide armor, dagger, adventurer's pack, grappling hook (~3.8 gp; weapon from greatsword/longbow/longsword+shield)
> - Monk: 10 darts, adventurer's pack, climbing kit, grappling hook, lesser smokestick (~5.3 gp)
> - Ranger: leather armor, dagger, adventurer's pack (~3.7 gp; weapon from longbow/longsword+shield/2 shortswords/snare kit)
> - Rogue: leather armor, dagger, rapier, adventurer's pack, climbing kit (~6.2 gp)
> - Sorcerer: dagger, sling+20 bullets, adventurer's pack, 2 caltrops (~2.3 gp)
> - Wizard: staff, adventurer's pack, material component pouch, writing set (~3 gp)

- REQ: System should support character creation starting kits as pre-configured bundles

---
