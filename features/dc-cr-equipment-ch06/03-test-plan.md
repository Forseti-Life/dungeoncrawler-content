# Test Plan: dc-cr-equipment-ch06

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Chapter 6 equipment system — currency, bulk/carrying, item damage, armor, shields, weapons, adventuring gear, alchemical/magical gear, formulas, services, animals, starting kits
**KB reference:** None found specific to ch06 equipment. Healer's tools gate pattern is consistent with dc-cr-skills-medicine-actions and dc-cr-feats-ch05 (TC-MED-03, TC-FEAT-17). Thieves' tools gate (standard/infiltrator/improvised) is the canonical source for the tri-state model used in dc-cr-skills-thievery-disable-pick-lock (TC-THI-07/08/15/16) — this feature defines the authoritative tool entity. Armor check penalty / attack-trait exclusion aligns with dc-cr-skills-calculator-hardening (TC-CALC-09/11). Shield bonus type (circumstance, not item) is a critical type-separation assertion that impacts any future bonus-stacking validation.
**Dependency note:** dc-cr-skill-system ✓ (in scope). dc-cr-character-leveling provides the character level field and Strength modifier used for bulk limits and craft-level gate — TCs asserting those are conditional. Most item-entity data TCs (stat validation for specific armor/weapon entries) are immediately activatable as data integrity checks once the equipment system exists.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All equipment system business logic: currency exchange, buy/sell pricing, rarity gates, bulk calculation, encumbrance/hard-cap, item states, item HP/Hardness/BT, broken conditions, armor AC formula, armor proficiency, donning time, shield bonus type/timing, MAP, crit hit doubling, weapon ability modifier routing, adventuring gear gates, consumable activation, formula system, services, animal panic |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes per security AC exemption; existing character creation, inventory, and encounter form routes only |

---

## Test Cases

### Currency and Economy

### TC-EQ-01 — Currency: cp/sp/gp/pp with standard exchange rates
- **Suite:** module-test-suite
- **Description:** Currency supports four denominations: copper (cp), silver (sp), gold (gp), platinum (pp). Exchange rates: 10 cp = 1 sp; 10 sp = 1 gp; 10 gp = 1 pp.
- **Expected:** convert(10, cp) = 1 sp; convert(10, sp) = 1 gp; convert(10, gp) = 1 pp.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-02 — Sell price: half purchase price for standard items
- **Suite:** module-test-suite
- **Description:** Standard items sell for half their purchase price. Exceptions (coins, gems, art objects, raw materials) sell at full purchase price.
- **Expected:** item.type = standard → sell_price = purchase_price / 2; item.type ∈ {coin, gem, art_object, raw_material} → sell_price = purchase_price.
- **Notes to PM:** Confirm the exception type taxonomy (enum values for coin/gem/art_object/raw_material). Automation needs deterministic item.type values.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-03 — Starting wealth: new character = 15 gp (150 sp)
- **Suite:** module-test-suite
- **Description:** A newly created character starts with 15 gp (equivalent to 150 sp). Character creation initializes wallet to this amount.
- **Expected:** new_character.wallet = 15 gp (or 150 sp equivalent).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character creation event)

### TC-EQ-04 — Price "—" items cannot be purchased; Price 0 items are free
- **Suite:** module-test-suite
- **Description:** Items with Price "—" are not purchasable through normal channels — the system must block the purchase. Items with Price 0 are free to acquire.
- **Expected:** item.price = null/dash → purchase blocked; item.price = 0 → purchase allowed at no cost.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-05 — Rarity gate: uncommon items require explicit access grant
- **Suite:** module-test-suite
- **Description:** Uncommon items cannot be purchased or added to inventory without an explicit access grant on the character (from character creation abilities, GM override, or class feature).
- **Expected:** item.rarity = uncommon AND character.has_access_grant(item) = false → purchase blocked; character.has_access_grant(item) = true → allowed.
- **Notes to PM:** Confirm how access grants are stored (flag on character entity, a list of granted item IDs/categories, or a class-feature-derived flag). Automation needs a deterministic grant mechanism.
- **Roles covered:** authenticated player (GM access grant: admin/GM)
- **Status:** immediately activatable

---

### Item States and Bulk

### TC-EQ-06 — Item states: held/worn/stowed tracked; abilities may require specific state
- **Suite:** module-test-suite
- **Description:** Each carried item has a state: held, worn, or stowed. Abilities that require a specific state (e.g., "weapon must be held") are blocked if the item is in the wrong state.
- **Expected:** item.state ∈ {held, worn, stowed}; ability requiring state X on item with state Y ≠ X → blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-07 — Bulk: numeric/L/negligible values; 10 L = 1 Bulk (floor); negligible don't count
- **Suite:** module-test-suite
- **Description:** Items have Bulk values: numeric (integer), L (Light), or negligible (—). Ten Light items equal 1 Bulk (fractional remainders are dropped). Negligible items contribute 0 Bulk.
- **Expected:** 10× L items → 1 Bulk; 9× L items → 0 Bulk (floor, not ceil); negligible items → 0 Bulk.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-08 — Carrying limit: 5 + Str modifier Bulk without penalty
- **Suite:** module-test-suite
- **Description:** A character can carry up to (5 + Strength modifier) Bulk without penalty. Carrying this amount or less imposes no encumbrance.
- **Expected:** character.bulk_carried ≤ (5 + Str_mod) → no encumbrance condition.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (Strength modifier field)

### TC-EQ-09 — Encumbered: clumsy 1 + –10 ft Speed (minimum 5 ft)
- **Suite:** module-test-suite
- **Description:** When bulk carried exceeds (5 + Str modifier), the character gains the encumbered condition: clumsy 1 AND –10 ft Speed. Speed minimum is 5 ft (not 0).
- **Expected:** bulk_carried > (5 + Str_mod) → character.condition = encumbered; clumsy 1 applied; Speed = max(5, base_speed – 10).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (Strength modifier)

### TC-EQ-10 — Hard cap: cannot carry more than 10 + Str modifier Bulk
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — characters cannot carry more than (10 + Strength modifier) Bulk. The system blocks adding items that would exceed this hard cap.
- **Expected:** bulk_carried + item.bulk > (10 + Str_mod) → item pickup blocked.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (Strength modifier)

### TC-EQ-11 — Coin bulk: 1,000 coins = 1 Bulk (floor)
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — coin bulk rounds down (floor). 999 coins = 0 Bulk; 1,000 coins = 1 Bulk; 1,999 coins = 1 Bulk.
- **Expected:** coins(999) = 0 Bulk; coins(1000) = 1 Bulk; coins(1999) = 1 Bulk; coins(2000) = 2 Bulk.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-12 — Small/Medium wielding Large weapon: clumsy 1, no extra damage
- **Suite:** module-test-suite
- **Description:** A Small or Medium creature wielding a Large weapon gains the clumsy 1 condition. They do NOT gain any extra damage for wielding an oversized weapon.
- **Expected:** character.size ∈ {Small, Medium} AND weapon.size = Large → character.condition includes clumsy 1; damage_dice = standard (not increased).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-13 — Large armor cannot be worn by Small/Medium creatures
- **Suite:** module-test-suite
- **Description:** A Small or Medium creature cannot wear Large-sized armor. The system blocks equipping it.
- **Expected:** character.size ∈ {Small, Medium} AND armor.size = Large → equip blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-14 — Worn tool sets: accessed free as part of use action; max 2 Bulk worn
- **Suite:** module-test-suite
- **Description:** Tool sets in the worn state are accessed for free as part of the action that uses them (no separate Interact action). A character can wear at most 2 Bulk of tool sets.
- **Expected:** tool.state = worn → access_action_cost = 0 (included in use action); total_worn_tool_bulk > 2 → additional tool cannot be worn.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Item Damage / Hardness / HP

### TC-EQ-15 — Item damage: max(0, damage – Hardness) subtracted from item HP
- **Suite:** module-test-suite
- **Description:** When an item takes damage, the effective damage is max(0, raw_damage – item.Hardness), subtracted from item.current_HP. Hardness cannot cause item to gain HP.
- **Expected:** item.hardness = 5 AND raw_damage = 3 → effective_damage = 0; item HP unchanged. item.hardness = 5 AND raw_damage = 8 → effective_damage = 3; item.current_HP -= 3.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-16 — Broken threshold: HP ≤ BT = broken; HP = 0 = destroyed
- **Suite:** module-test-suite
- **Description:** When item HP drops to or below the Broken Threshold (BT), the item gains the broken condition. When HP reaches 0, the item is destroyed.
- **Expected:** item.current_HP ≤ item.BT → item.condition = broken; item.current_HP = 0 → item.condition = destroyed.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-17 — Broken items: cannot be used normally, grant no bonuses
- **Suite:** module-test-suite
- **Description:** A broken item cannot function as its intended use. It does not provide item bonuses or other mechanical benefits.
- **Expected:** item.condition = broken → item.functional = false; item.item_bonus = 0 (suppressed).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-18 — Broken armor exception: AC bonus retained, status penalty applied
- **Suite:** module-test-suite
- **Description:** Edge case — broken armor is the one exception: it still grants its AC bonus (item bonus), but applies a status penalty to AC (–1 light / –2 medium / –3 heavy). All armor check and speed penalties still apply.
- **Expected:** armor.condition = broken → AC_item_bonus retained; AC_status_penalty = –1 (light) / –2 (medium) / –3 (heavy); check_penalty still applied; speed_penalty still applied.
- **Notes to PM:** Confirm the exact penalty values per armor weight category. Automation needs these as constants.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-19 — Shoddy quality: –2 item penalty to attacks/checks; ACP worsened –2; HP/BT halved
- **Suite:** module-test-suite
- **Description:** Shoddy items impose a –2 item penalty to all attack rolls and skill checks. Armor check penalty is worsened by an additional –2. Item HP and BT are each halved (floor).
- **Expected:** item.quality = shoddy → attack_rolls –2; skill_checks –2; armor_check_penalty += 2; item.HP = floor(base_HP / 2); item.BT = floor(base_BT / 2).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Armor

### TC-EQ-20 — AC formula: 10 + min(Dex mod, Dex Cap) + proficiency + item bonus + others + penalties
- **Suite:** module-test-suite
- **Description:** The AC calculation must use exactly: 10 + min(Dex mod, Dex Cap) + proficiency bonus + item bonus + other bonuses + penalties. Dex modifier is capped at Dex Cap.
- **Expected:** character.Dex_mod = 4, armor.Dex_cap = 2 → Dex contribution = 2 (not 4); AC = 10 + 2 + proficiency + item_bonus + others + penalties.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (Dex modifier field)

### TC-EQ-21 — Armor check penalty exempt for attack-trait actions
- **Suite:** module-test-suite
- **Description:** The armor check penalty does NOT apply to attack-trait actions (Grapple, Trip, Disarm when used as attack actions). This aligns with the dc-cr-skills-calculator-hardening AC.
- **Expected:** action.trait = attack → armor_check_penalty NOT applied to roll; action.trait ≠ attack → penalty applied.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-22 — Strength threshold: removes check penalty + reduces speed penalty by 5 ft
- **Suite:** module-test-suite
- **Description:** When a character's Strength modifier meets or exceeds the armor's Strength threshold, the armor check penalty is completely removed and the speed penalty is reduced by 5 ft.
- **Expected:** character.Str_mod ≥ armor.Str_threshold → check_penalty = 0; speed_penalty = max(0, base_speed_penalty – 5).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (Strength modifier)

### TC-EQ-23 — Donning time: light = 1 min; medium/heavy = 5 min; removing = 1 min
- **Suite:** module-test-suite
- **Description:** Donning light armor takes 1 minute. Donning medium or heavy armor takes 5 minutes. Removing any armor takes 1 minute.
- **Expected:** armor.category = light → don_time = 1 min; armor.category ∈ {medium, heavy} → don_time = 5 min; remove_time = 1 min for all categories.
- **Notes to PM:** Confirm how "minutes" is modeled as an action/time cost in the system (rounds, real-world timer, or a blocking action count). Automation needs a deterministic time assertion.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-24 — Armor specialization effects gated behind class features (not automatic)
- **Suite:** module-test-suite
- **Description:** Armor group specialization effects (chain, composite, leather, plate) are NOT applied automatically when wearing armor of that group. They require an explicit class feature that grants them.
- **Expected:** character wears chain armor WITHOUT specialization class feature → chain crit-reduction NOT applied; WITH class feature → applied.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-25 — Chain group specialization: crit damage reduction (floor = pre-doubling roll)
- **Suite:** module-test-suite
- **Description:** Chain group specialization reduces critical hit damage. The reduction amount is calculated from the pre-doubling roll (not after doubling). Floor applies.
- **Expected:** crit_hit: raw_roll = 10, doubled = 20; chain_reduction applied to pre-doubling value; damage = (raw_roll – reduction) × 2.
- **Notes to PM:** Provide the exact reduction formula/value for chain group specialization. Automation needs a constant to assert against.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (once PM provides reduction value)

---

### Shields

### TC-EQ-26 — Shield bonus: circumstance bonus to AC, only when Raise a Shield is active
- **Suite:** module-test-suite
- **Description:** The shield's AC bonus is a circumstance bonus (not an item bonus). It only applies when the character has actively used the Raise a Shield action this round.
- **Expected:** character.shield_raised = false → shield_circumstance_bonus NOT applied to AC; character.shield_raised = true → bonus applied.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-27 — Shield bonus is circumstance: does NOT stack with other circumstance bonuses to AC
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — circumstance bonuses don't stack. If the character has another source of circumstance bonus to AC (e.g., +1 circumstance from a spell), the shield's circumstance bonus and the other source don't stack — only the higher applies.
- **Expected:** character.raised_shield_bonus = +2 AND character.other_circumstance_ac_bonus = +3 → AC circumstance bonus = 3 (not 5).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-28 — Shield speed penalty: applies whenever held (not only when raised)
- **Suite:** module-test-suite
- **Description:** Any speed penalty from a shield applies whenever the shield is held (state = held), not only when the Raise a Shield action is active.
- **Expected:** shield.state = held AND shield.speed_penalty > 0 → character.speed reduced by shield.speed_penalty regardless of shield_raised state.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-29 — Buckler: strapped to forearm, doesn't occupy hand, can raise with hand holding light non-weapon
- **Suite:** module-test-suite
- **Description:** The Buckler is strapped to the forearm (not held in hand). It does not occupy the hand slot. Raise a Shield can be used when the hand is free OR holding a light non-weapon item.
- **Expected:** buckler.occupies_hand = false; Raise Shield allowed when: hand = empty OR hand holds light_non_weapon; Raise Shield blocked when: hand holds weapon.
- **Notes to PM:** Confirm how "light non-weapon" is defined in the item type taxonomy. Automation needs a deterministic item type check.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-30 — Shield Block: damage reduced by Hardness; remainder splits between character and shield
- **Suite:** module-test-suite
- **Description:** When Shield Block is used, the damage is first reduced by the shield's Hardness. The remaining damage is then dealt to both the character AND the shield.
- **Expected:** incoming_damage = 12; shield.hardness = 5 → blocked_damage = 5; remainder = 7; character.HP -= 7; shield.HP -= 7.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Weapons

### TC-EQ-31 — Melee ability modifier: Str standard; Dex for finesse; Str for thrown; half-Str for propulsive
- **Suite:** module-test-suite
- **Description:** Melee attacks use Str modifier by default. Finesse weapons may use Dex (higher of Str/Dex). Thrown weapons use full Str. Propulsive weapons use half Str if positive, full Str if negative.
- **Expected:** weapon.trait = finesse → damage uses max(Str_mod, Dex_mod); weapon.trait = thrown → damage uses full Str_mod; weapon.trait = propulsive → damage += (Str_mod > 0 ? floor(Str_mod/2) : Str_mod).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (Str/Dex modifier fields)

### TC-EQ-32 — MAP: –5 second attack, –10 third+; agile –4/–8; no MAP on reactions
- **Suite:** module-test-suite
- **Description:** Multiple Attack Penalty: 2nd attack in a turn = –5; 3rd and beyond = –10. Agile weapons reduce this to –4 and –8. MAP does not apply to off-turn attacks (reactions).
- **Expected:** attack 1 → penalty 0; attack 2 → penalty –5 (standard) / –4 (agile); attack 3 → penalty –10 (standard) / –8 (agile); reaction attack → penalty 0.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-33 — MAP agile reduction: only applies to that weapon's attacks
- **Suite:** module-test-suite
- **Description:** Edge case — the agile MAP reduction (–4/–8 instead of –5/–10) only applies to attacks made with the agile weapon itself, not to subsequent attacks with other non-agile weapons.
- **Expected:** attack 1 with agile weapon → MAP 0; attack 2 with non-agile weapon → MAP –5 (not –4).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-34 — Critical hit: double all damage components
- **Suite:** module-test-suite
- **Description:** A critical hit doubles all damage components: base weapon dice, ability modifier, and any flat damage bonuses. Striking rune adds 1 extra weapon die (Greater = 2, Major = 3).
- **Expected:** crit_hit: all_damage_components × 2; weapon with Striking rune → roll 2 weapon dice before doubling; Greater Striking → 3 dice; Major Striking → 4 dice.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-35 — Unarmed: fist = 1d4 B, agile, finesse, nonlethal, unarmed traits, Brawling group
- **Suite:** module-test-suite
- **Description:** The default unarmed attack (fist) has: damage 1d4 bludgeoning, traits: agile, finesse, nonlethal, unarmed; weapon group: Brawling. Unarmed attacks are distinct from weapons.
- **Expected:** fist.damage = 1d4; fist.damage_type = B; fist.traits contains {agile, finesse, nonlethal, unarmed}; fist.group = Brawling; fist.is_weapon = false.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-36 — Improvised weapon: simple category, –2 item penalty, not blocked
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — an improvised weapon attack applies a –2 item penalty to the attack roll but is NOT blocked. The attack proceeds with the penalty.
- **Expected:** weapon.type = improvised → attack_roll includes –2 item penalty; attack not blocked.
- **Notes to PM:** Confirm how improvised weapon damage is handled — is it a GM-adjudicated field on the item entity, a fixed fallback die, or flagged for manual resolution?
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-37 — Ranged range increments: –2 per increment beyond 1st; impossible at 7th+
- **Suite:** module-test-suite
- **Description:** Beyond the first range increment, each additional increment applies –2 to the attack roll. Beyond the 6th increment (7th+), the attack is impossible.
- **Expected:** distance = 1 increment → no penalty; 2nd increment → –2; 3rd → –4; 4th → –6; 5th → –8; 6th → –10; 7th+ → attack blocked (impossible).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-38 — Reload: Interact actions per reload value; 0 = draw+fire combined
- **Suite:** module-test-suite
- **Description:** A weapon's reload value specifies the number of Interact actions required to reload. Reload 0 means the weapon can be drawn and fired in a single attack action.
- **Expected:** weapon.reload = 2 → 2 Interact actions consumed before next shot; weapon.reload = 0 → no separate Interact required; draw+attack combined.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-39 — Weapon group crit specialization effects gated behind class features
- **Suite:** module-test-suite
- **Description:** The 14 weapon group critical specialization effects are NOT automatic. They require a class feature that explicitly grants critical specialization for that weapon group.
- **Expected:** character uses weapon in group X WITHOUT critical specialization class feature → specialization effect NOT applied on crit; WITH class feature → applied.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Adventuring Gear

### TC-EQ-40 — Healer's Tools: gates First Aid/Treat Disease/Treat Poison/Treat Wounds; worn = free access; expanded kit +1 item bonus
- **Suite:** module-test-suite
- **Description:** Healer's Tools are required to use First Aid, Treat Disease, Treat Poison, and Treat Wounds. When worn (state = worn), they are accessed for free as part of the action. The expanded kit variant grants an additional +1 item bonus.
- **Expected:** action ∈ {First Aid, Treat Disease, Treat Poison, Treat Wounds} AND character.has_healers_tools = false → blocked; tools.state = worn → no separate access action; tools.variant = expanded → +1 item bonus applied.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-41 — Thieves' Tools: gates Pick Lock/Disable Device; infiltrator +1 bonus; worn = free access; broken picks replaced without Repair
- **Suite:** module-test-suite
- **Description:** Thieves' Tools gate Pick Lock and Disable Device. Worn tools are accessed free. The infiltrator variant grants +1 item bonus. Broken picks (used on Critical Failure) are replaced from a supply without requiring a Repair action.
- **Expected:** tools.variant = infiltrator → +1 item bonus; tools.broken_picks = N after crit fail → picks restored automatically (no Repair required); tools.state = worn → free access.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-42 — Caltrops: DC 14 Acrobatics or 1d4 P + 1 persistent bleed + –5 ft Speed; Interact to remove
- **Suite:** module-test-suite
- **Description:** When a creature enters a square with deployed caltrops, it must succeed a DC 14 Acrobatics check. On failure: takes 1d4 piercing damage, gains 1 persistent bleed, and suffers –5 ft Speed. The caltrops can be removed with an Interact action.
- **Expected:** creature enters caltrop square → Acrobatics DC 14; failure → damage 1d4 P + persistent_bleed 1 + speed –5 ft; Interact action → caltrops removed from square.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-43 — Lock: DC and successes-required by quality tier
- **Suite:** module-test-suite
- **Description:** Locks have a DC and a required-successes count based on quality tier (simple, average, good, superior). This is the authoritative source for lock DC values referenced by Thievery (TC-THI-14).
- **Expected:** lock.quality = simple → dc = simple_dc AND successes_required = simple_successes; (likewise for average/good/superior).
- **Notes to PM:** Provide the authoritative DC and successes-required values per lock quality tier. This resolves the open PM question from dc-cr-skills-thievery-disable-pick-lock (TC-THI-14 Note to PM item 8).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (data integrity; exact values needed for assertions)

### TC-EQ-44 — Grappling Hook: attack roll (secret, DC 20); Crit Fail appears anchored but fails mid-way
- **Suite:** module-test-suite
- **Description:** Throwing a grappling hook uses an attack roll. The result is secret (DC 20). On Critical Failure, the hook appears to be anchored but falls when the character is mid-climb (not immediately obvious on throw).
- **Expected:** grappling_hook_throw: attack roll vs DC 20; result is secret; crit_fail → hook.appears_anchored = true AND hook.actual_secure = false (revealed on use).
- **Notes to PM:** This Crit Fail behavior mirrors the Recall Knowledge and Recognize Spell false-result patterns. Confirm how the deferred failure is surfaced to the player (at what point is the false anchor revealed?). Automation needs a deterministic trigger for the reveal.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (deferred-reveal fixture needs PM clarification)

### TC-EQ-45 — Manacles: leg = –15 ft Speed; wrist = DC 5 flat check on manipulate; escape DCs by quality
- **Suite:** module-test-suite
- **Description:** Leg manacles reduce Speed by 15 ft. Wrist manacles require a DC 5 flat check whenever the character attempts a manipulate action. Escape DCs vary by manacle quality.
- **Expected:** leg_manacles.equipped → Speed –15 ft; wrist_manacles.equipped → every manipulate action requires DC 5 flat check; escape_dc varies by quality.
- **Notes to PM:** Provide authoritative escape DC values per manacle quality. Automation needs exact values.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Alchemical and Magical Gear

### TC-EQ-46 — Alchemical bombs: consumable, 20 ft range, Bomb group, splash damage
- **Suite:** module-test-suite
- **Description:** Alchemical bombs are consumables. They have a 20 ft range. They belong to the Bomb weapon group. They deal splash damage to adjacent creatures on hit (and on miss per bomb rules).
- **Expected:** bomb.consumable = true; bomb.range = 20 ft; bomb.weapon_group = Bomb; bomb.splash_damage applied to adjacent squares on attack.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-47 — Scrolls: consumable, single-use, common 1st-level = 4 gp
- **Suite:** module-test-suite
- **Description:** Scrolls are consumable items holding a single spell. A common 1st-level scroll costs 4 gp. After activation, the scroll is consumed.
- **Expected:** scroll.consumable = true; scroll.uses = 1; scroll(rarity=common, spell_level=1).price = 4 gp; after activation → scroll destroyed from inventory.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-48 — Talismans: affixed to gear slot, single-use activation
- **Suite:** module-test-suite
- **Description:** Talismans are affixed to a specific gear slot (weapon, armor, etc.). They are single-use: after activation, the talisman is consumed.
- **Expected:** talisman.affixed_slot ≠ null; talisman.uses = 1; after activation → talisman removed from slot.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Formulas

### TC-EQ-49 — Formulas: purchase/copy/reverse-engineer; reverse = disassemble + Craft check vs item DC
- **Suite:** module-test-suite
- **Description:** Formulas can be obtained by purchase, copying from another source, or reverse engineering. Reverse engineering requires disassembling the item (costs half-price materials) and succeeding a Craft check against the item's DC.
- **Expected:** formula.acquisition_method ∈ {purchase, copy, reverse_engineer}; reverse_engineer: material_cost = item.price / 2; requires Craft_check vs item_dc.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-50 — Basic Crafter's Book: contains all 0-level common formulas
- **Suite:** module-test-suite
- **Description:** The Basic Crafter's Book grants access to all common formulas for 0-level items. A character with this book can craft any common level-0 item without acquiring the formula separately.
- **Expected:** character.has_basic_crafters_book = true → all common level-0 formulas accessible; character.has_basic_crafters_book = false → must acquire each formula individually.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-51 — Craft action gate: item level ≤ character level
- **Suite:** module-test-suite
- **Description:** The Craft action enforces that the item's level does not exceed the character's level. Attempting to craft an item above the character's level is blocked.
- **Expected:** item.level > character.level → Craft action blocked; item.level ≤ character.level → allowed (subject to formula and material requirements).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character level field)

---

### Services and Economy

### TC-EQ-52 — Hirelings: level 0; unskilled +0; skilled +4 specialty / +0 others; rates double in danger
- **Suite:** module-test-suite
- **Description:** Hirelings are level 0 NPCs. Unskilled hirelings have +0 to all skills. Skilled hirelings have +4 to their specialty skill and +0 to all others. Hire rates double when the hireling is in danger.
- **Expected:** hireling.level = 0; unskilled → all_skills = +0; skilled → specialty_skill = +4, other_skills = +0; in_danger = true → daily_rate × 2.
- **Roles covered:** authenticated player (admin/GM for danger flag)
- **Status:** immediately activatable

### TC-EQ-53 — Non-combat-trained animals: panic at frightened 4 + fleeing in combat
- **Suite:** module-test-suite
- **Description:** Animals not trained for combat automatically panic when combat starts: they gain frightened 4 AND the fleeing condition.
- **Expected:** animal.combat_trained = false AND combat_started = true → animal.condition includes frightened 4 AND fleeing.
- **Roles covered:** authenticated player (GM trigger)
- **Status:** immediately activatable

### TC-EQ-54 — Combat-trained animals: do not panic
- **Suite:** module-test-suite
- **Description:** Animals trained for combat (warhorse, warpony) do not gain the panic conditions when combat starts.
- **Expected:** animal.combat_trained = true AND combat_started = true → animal.condition does NOT include frightened/fleeing (from combat start alone).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Edge Cases

### TC-EQ-55 — Encumbered minimum Speed: 5 ft (not 0)
- **Suite:** module-test-suite
- **Description:** Edge case — the encumbered condition's –10 ft Speed penalty cannot reduce Speed below 5 ft. A character with base Speed 10 ft becomes encumbered → Speed = 5 ft, not 0.
- **Expected:** base_speed = 10 AND encumbered → Speed = 5 (not 0); base_speed = 5 AND encumbered → Speed = 5.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-EQ-56 — Broken armor: status penalty stacks with existing AC, check/speed penalties still apply
- **Suite:** module-test-suite
- **Description:** Edge case — broken armor applies a status penalty to AC in addition to retaining the AC bonus. The armor check penalty and speed penalty still apply (they are not removed by the broken condition).
- **Expected:** armor.broken = true → AC = 10 + Dex_contribution + proficiency + AC_bonus – status_penalty; check_penalty still active; speed_penalty still active.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### ACL Regression

### TC-EQ-57 — ACL regression: no new routes; existing character/inventory/encounter forms retain ACL
- **Suite:** role-url-audit
- **Description:** Per the security AC exemption, equipment system implementation adds no new HTTP routes. Existing character creation, inventory management, and encounter handler routes retain their ACL.
- **Expected:** existing routes: HTTP 200 for authenticated player; HTTP 403/redirect for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-EQ-03 | dc-cr-character-leveling | Starting wealth set on character creation event |
| TC-EQ-08 | dc-cr-character-leveling | Strength modifier for bulk limit |
| TC-EQ-09 | dc-cr-character-leveling | Strength modifier for encumbrance threshold |
| TC-EQ-10 | dc-cr-character-leveling | Strength modifier for hard cap |
| TC-EQ-20 | dc-cr-character-leveling | Dex modifier for AC formula |
| TC-EQ-22 | dc-cr-character-leveling | Strength modifier for threshold check |
| TC-EQ-31 | dc-cr-character-leveling | Str/Dex modifier for weapon damage routing |
| TC-EQ-51 | dc-cr-character-leveling | Character level for craft gate |

49 TCs immediately activatable.
8 TCs conditional on dc-cr-character-leveling.

---

## Notes to PM

1. **TC-EQ-02 (sell exception types):** Confirm enum values for exception item types (coin/gem/art_object/raw_material). Automation needs deterministic item.type values.
2. **TC-EQ-05 (access grant model):** Confirm how uncommon access grants are stored on the character entity. Automation needs a fixture mechanism to grant/revoke access.
3. **TC-EQ-18 (broken armor penalty values):** Confirm exact status penalty values per armor weight category (–1/–2/–3 assumed from AC; confirm values).
4. **TC-EQ-23 (donning time unit):** Confirm how "minutes" is modeled — action count, real timer, or round-based. Automation needs a deterministic time assertion.
5. **TC-EQ-25 (chain crit reduction formula):** Provide the exact reduction value for chain group specialization. This is a constant needed for the pre-doubling assertion.
6. **TC-EQ-29 (light non-weapon definition):** Confirm how "light non-weapon" is defined in the item type taxonomy for Buckler's Raise Shield condition.
7. **TC-EQ-36 (improvised weapon damage):** Confirm how GM-adjudicated improvised weapon damage is modeled (fallback die, GM field, or flagged for manual resolution).
8. **TC-EQ-43 (lock DC values):** Provide authoritative DC and successes-required per lock quality tier (simple/average/good/superior). This resolves the open question from dc-cr-skills-thievery (TC-THI-14 note).
9. **TC-EQ-44 (grappling hook deferred reveal):** Confirm at what point the false-anchor Crit Fail is revealed to the player (on first use attempt, on first Climb action, or immediately on the next action).
10. **TC-EQ-45 (manacle escape DCs):** Provide authoritative escape DC values per manacle quality tier.
11. **TC-EQ-52 (hireling danger flag):** Confirm how "in danger" is determined for hireling rate doubling — automatic in combat, GM-toggled, or a condition on the encounter.
