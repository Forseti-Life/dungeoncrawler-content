# Test Plan: dc-cr-class-rogue

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Rogue Class — identity/stats, Racket (Ruffian/Scoundrel/Thief), Sneak Attack, Surprise Attack, Debilitations, feat progression
**KB reference:** none found (first Rogue class feature)
**Dependency note:** dc-cr-conditions (in-progress Release B) — 3 TCs deferred (flat-footed condition state management)

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Rogue class business logic, stat derivation, Racket mechanics, Sneak Attack, Surprise Attack, Debilitations, feat/boost gating |
| `role-url-audit` | HTTP role audit | ACL regression — no new Rogue-specific routes; existing character routes only |

---

## Test Cases

### TC-ROG-01 — Rogue class selectable at character creation
- **Suite:** module-test-suite
- **Description:** Rogue appears in the class selection list during character creation.
- **Expected:** Rogue is a valid selectable class in the character creation flow.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-02 — Default key ability: DEX
- **Suite:** module-test-suite
- **Description:** Without a Racket that overrides it, Rogue key ability = DEX at level 1.
- **Expected:** Key ability defaults to DEX; DEX mod applied to class DC.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-03 — Key ability override blocked without qualifying racket
- **Suite:** module-test-suite
- **Description:** Failure mode — player cannot select STR or CHA as key ability unless their chosen Racket permits it (Ruffian = STR, Scoundrel = CHA).
- **Expected:** STR key ability only available with Ruffian; CHA key ability only available with Scoundrel; Thief forces DEX.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-04 — HP per level: 8 + CON modifier
- **Suite:** module-test-suite
- **Description:** At each level-up, Rogue gains exactly 8 + CON modifier HP.
- **Expected:** HP delta per level = 8 + CON mod (minimum 1 per level if CON is negative).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-05 — Initial proficiency: Trained Perception
- **Suite:** module-test-suite
- **Description:** Rogue starts with Trained (not Expert) in Perception at level 1.
- **Expected:** Perception = Trained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-06 — Initial proficiencies: Expert Reflex AND Expert Will at level 1
- **Suite:** module-test-suite
- **Description:** Rogue uniquely starts with Expert rank in both Reflex and Will saves at level 1; Fortitude is Trained.
- **Expected:** Reflex = Expert, Will = Expert, Fortitude = Trained at level 1. (Unique — most classes start all saves at Trained.)
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-07 — Initial proficiencies: specific weapon list
- **Suite:** module-test-suite
- **Description:** Rogue starts Trained in simple weapons, hand crossbow, rapier, sap, shortbow, shortsword (specific named weapons beyond simple set).
- **Expected:** All listed weapons Trained at level 1; martial weapons other than the named ones = Untrained.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-08 — Initial proficiencies: Trained light armor only
- **Suite:** module-test-suite
- **Description:** Rogue starts Trained in light armor; medium armor Untrained (unless Ruffian racket).
- **Expected:** Light armor = Trained; medium armor = Untrained for non-Ruffian; heavy armor = Untrained.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-09 — Initial skills: Stealth + racket skills + 7 + INT modifier
- **Suite:** module-test-suite
- **Description:** Rogue starts with Trained in Stealth, their racket's required skill(s), plus 7 additional skills of choice, plus INT modifier additional skills.
- **Expected:** Total trained skills = 1 (Stealth) + racket skills + 7 + INT mod (min 0 additional).
- **Notes to PM:** Racket skill counts: Ruffian adds Intimidation (1); Scoundrel adds Deception + Diplomacy (2); Thief adds Thievery (1). Need confirmation this is correct enumeration before fully parameterizing TC-ROG-09 count assertions.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (skill count PM flag noted)

### TC-ROG-10 — Skill feat every level (not just even levels)
- **Suite:** module-test-suite
- **Description:** Rogue receives a skill feat slot at every level (1, 2, 3, 4 … 20), unlike most classes which receive skill feats only at even levels.
- **Expected:** Skill feat slot present at all 20 levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-11 — Skill increases every level from 2nd onward
- **Suite:** module-test-suite
- **Description:** Rogue receives a skill proficiency increase at every level from level 2 onward.
- **Expected:** Skill increase slot at levels 2–20 (19 total opportunities).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-12 — Racket selection required at level 1
- **Suite:** module-test-suite
- **Description:** Player must select exactly one Racket from {Ruffian, Scoundrel, Thief} at level 1; no Rogue advances without a Racket.
- **Expected:** Exactly one Racket recorded on character; level advancement gated on selection.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-13 — Ruffian: sneak attack with any simple weapon
- **Suite:** module-test-suite
- **Description:** Ruffian allows sneak attack precision damage with any simple weapon, not only agile or finesse weapons.
- **Expected:** Sneak attack applies with non-agile, non-finesse simple weapon (e.g., club) when target is flat-footed.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-14 — Ruffian: crit specialization on crit vs flat-footed (≤d8 simple weapons)
- **Suite:** module-test-suite
- **Description:** With Ruffian, a critical hit against a flat-footed target with a simple weapon of die ≤d8 also applies the weapon's critical specialization effect.
- **Expected:** Crit + flat-footed + simple ≤d8 → crit specialization applied. No crit specialization on non-flat-footed crit.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-15 — Ruffian: d10/d12 simple weapons do NOT get crit specialization
- **Suite:** module-test-suite
- **Description:** Edge case — large-die simple weapons (e.g., greatclub d10) do not qualify for the Ruffian crit specialization bonus.
- **Expected:** Crit + flat-footed + greatclub (d10) → no crit specialization effect (sneak attack still applies normally).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-16 — Ruffian: STR key ability available
- **Suite:** module-test-suite
- **Description:** With Ruffian racket selected, player may choose STR as key ability boost.
- **Expected:** STR available in key ability selection when Racket = Ruffian.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-17 — Ruffian: Trained medium armor
- **Suite:** module-test-suite
- **Description:** Ruffian grants Trained in medium armor at level 1.
- **Expected:** Medium armor = Trained for Ruffian Rogues at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-18 — Scoundrel: successful Feint → target flat-footed to rogue melee until end of next turn
- **Suite:** module-test-suite
- **Description:** With Scoundrel, a successful (non-critical) Feint makes the target flat-footed against the Rogue's melee attacks until end of the Rogue's next turn.
- **Expected:** Target gains flat-footed condition vs Rogue melee attacks; condition expires at end of Rogue's next turn.
- **Dependency note:** TC depends on `dc-cr-conditions` for flat-footed condition state management (in-progress Release B). Activate at Stage 0 only when `dc-cr-conditions` ships.
- **Roles covered:** authenticated player
- **Status:** conditional — pending `dc-cr-conditions`

### TC-ROG-19 — Scoundrel: critical success Feint → flat-footed to ALL melee (not just Rogue)
- **Suite:** module-test-suite
- **Description:** With Scoundrel, a critical success on Feint makes the target flat-footed against ALL melee attacks (not only the Rogue's) until end of the Rogue's next turn.
- **Expected:** On critical Feint: target flat-footed to all melee attackers; on regular success: only flat-footed to Rogue melee.
- **Dependency note:** TC depends on `dc-cr-conditions` for flat-footed condition state management (in-progress Release B).
- **Roles covered:** authenticated player
- **Status:** conditional — pending `dc-cr-conditions`

### TC-ROG-20 — Scoundrel: Feint flat-footed expires correctly
- **Suite:** module-test-suite
- **Description:** Edge case — flat-footed status from Scoundrel Feint expires at the end of the Rogue's next turn; does not persist longer.
- **Expected:** After Rogue's next turn ends, target is no longer flat-footed from this Feint (barring other sources).
- **Dependency note:** TC depends on `dc-cr-conditions` (in-progress Release B).
- **Roles covered:** authenticated player
- **Status:** conditional — pending `dc-cr-conditions`

### TC-ROG-21 — Scoundrel: CHA key ability available
- **Suite:** module-test-suite
- **Description:** With Scoundrel racket selected, player may choose CHA as key ability boost.
- **Expected:** CHA available in key ability selection when Racket = Scoundrel.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-22 — Scoundrel: Trained Deception and Diplomacy
- **Suite:** module-test-suite
- **Description:** Scoundrel grants Trained in both Deception and Diplomacy as racket skills.
- **Expected:** Deception = Trained, Diplomacy = Trained for Scoundrel Rogues (both count toward skill total).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-23 — Thief: DEX to damage on finesse melee weapons
- **Suite:** module-test-suite
- **Description:** With Thief racket, finesse melee weapon attacks use DEX modifier for damage instead of STR.
- **Expected:** Damage roll for finesse melee = weapon dice + DEX mod (not STR mod) for Thief Rogues.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-24 — Thief: DEX-to-damage restricted to finesse melee only
- **Suite:** module-test-suite
- **Description:** Edge case — Thief DEX-to-damage does not apply to ranged weapons or non-finesse melee weapons.
- **Expected:** Ranged and non-finesse melee still use STR mod for damage even with Thief racket.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-25 — Thief: Trained Thievery
- **Suite:** module-test-suite
- **Description:** Thief racket grants Trained in Thievery as the racket skill.
- **Expected:** Thievery = Trained for Thief Rogues at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-26 — Sneak Attack: precision damage only vs flat-footed target
- **Suite:** module-test-suite
- **Description:** Sneak attack precision damage applies only when the target has the flat-footed condition; not on normal targets.
- **Expected:** Hit vs flat-footed target = base damage + sneak attack dice; hit vs non-flat-footed = base damage only.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-27 — Sneak Attack without flat-footed: no precision damage
- **Suite:** module-test-suite
- **Description:** Failure mode — sneak attack precision dice NOT added when target is not flat-footed.
- **Expected:** No additional precision damage on a hit against a non-flat-footed target.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-28 — Sneak Attack: immune targets (no vital anatomy)
- **Suite:** module-test-suite
- **Description:** Sneak attack precision damage is suppressed against creatures without vital anatomy (oozes, constructs, undead, elementals, etc.).
- **Expected:** Zero sneak attack dice added vs immune creature type, even when flat-footed.
- **Notes to PM:** Exact creature-type immunity list should be enumerated in the creature data model. Confirm which creature types are in dc-cr scope before full parameterization.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (creature type list needs PM/Dev enumeration)

### TC-ROG-29 — Sneak Attack dice scaling by level
- **Suite:** module-test-suite
- **Description:** Sneak attack damage scales: 1d6 at levels 1–4, 2d6 at levels 5–8, 3d6 at levels 9–12, 4d6 at levels 13–16, 5d6 at levels 17–20.
- **Expected:** Correct die count at each band (verified via character stat sheet and damage calculation).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-30 — Surprise Attack: Deception initiative → pre-acting creatures flat-footed (round 1)
- **Suite:** module-test-suite
- **Description:** When Rogue uses Deception to roll initiative, any creature that has not yet acted in round 1 is flat-footed against the Rogue's attacks that round.
- **Expected:** Creatures with higher initiative not yet taken their turn in round 1 = flat-footed vs Rogue; creatures who have already acted = not flat-footed from Surprise Attack.
- **Notes to PM:** Requires initiative tracking to be implemented (dc-cr-combat or equivalent); confirm which release that system lands in for full activation timing.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (initiative system dependency noted; TC can be written against stub if combat module ships before Rogue)

### TC-ROG-31 — Surprise Attack: Stealth initiative → same flat-footed behavior
- **Suite:** module-test-suite
- **Description:** Same as TC-ROG-30 but triggered by Stealth initiative roll.
- **Expected:** Identical flat-footed behavior when Stealth used for initiative roll.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-32 — Debilitation: mutually exclusive — new replaces previous
- **Suite:** module-test-suite
- **Description:** Applying a new Debilitation immediately ends the previous one; no two Debilitations active simultaneously.
- **Expected:** After applying Debilitation B, Debilitation A is removed; target has only Debilitation B.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-33 — Feat progression: class feat at level 1 and every even level
- **Suite:** module-test-suite
- **Description:** Rogue receives class feat slots at levels 1, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20.
- **Expected:** Class feat slot at each of those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-34 — Feat progression: general feats at levels 3, 7, 11, 15, 19
- **Suite:** module-test-suite
- **Description:** Rogue receives general feat slots at levels 3, 7, 11, 15, 19 only.
- **Expected:** General feat slots at exactly those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-35 — Feat progression: ancestry feats at levels 5, 9, 13, 17
- **Suite:** module-test-suite
- **Description:** Rogue receives ancestry feat slots at levels 5, 9, 13, 17 only.
- **Expected:** Ancestry feat slots at exactly those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-36 — Ability boosts at levels 5, 10, 15, 20
- **Suite:** module-test-suite
- **Description:** Rogue receives four ability boosts at levels 5, 10, 15, 20.
- **Expected:** Four ability boost choices available at each of those levels; none at other levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-37 — Level-gated features do not appear before required level
- **Suite:** module-test-suite
- **Description:** Failure mode — class abilities and advanced feats are not accessible or displayed below their minimum level.
- **Expected:** Feature unlock list at level N contains only features with minimum_level ≤ N.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ROG-38 — ACL regression: no new routes introduced by Rogue
- **Suite:** role-url-audit
- **Description:** Rogue class implementation adds no new HTTP routes; existing character creation/leveling routes remain accessible per their existing ACL.
- **Expected:** HTTP 200 for authenticated player on existing character routes; HTTP 403 for anonymous on auth-required routes (no change from baseline).
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Deferred dependency summary

| TC | Dependency feature | Reason deferred |
|---|---|---|
| TC-ROG-18 | `dc-cr-conditions` (Release B) | Flat-footed condition state required for Scoundrel Feint success tracking |
| TC-ROG-19 | `dc-cr-conditions` (Release B) | Critical Feint flat-footed (all melee) requires condition system |
| TC-ROG-20 | `dc-cr-conditions` (Release B) | Feint flat-footed expiry requires condition turn tracking |

35 TCs are immediately activatable at Stage 0. 3 TCs (TC-ROG-18/19/20) wait for `dc-cr-conditions`.

---

## Notes to PM

1. **TC-ROG-09 (skill count):** Confirm racket skill counts: Ruffian (Intimidation = 1 extra), Scoundrel (Deception + Diplomacy = 2 extra), Thief (Thievery = 1 extra). Total trained skills formula = 1 (Stealth) + racket_count + 7 + INT mod. If any racket grants skills already covered by Stealth or the base list, the net count may differ.
2. **TC-ROG-28 (sneak attack immunity list):** Enumerate which creature types are immune to precision/sneak damage in the dc-cr scope (oozes, constructs, undead, etc.). This list drives the parameterization of immunity assertions.
3. **TC-ROG-30/31 (Surprise Attack + initiative system):** Confirm which release the initiative/combat-turn-order system lands in. If it ships before Rogue, activate TC-ROG-30/31 immediately; otherwise defer alongside conditions.
4. **Debilitations (TC-ROG-32):** The AC notes debilitations are feat-gated. TC-ROG-32 tests the mutual-exclusivity rule but the specific debilitation types need feat implementation before sub-type assertions can be added.
