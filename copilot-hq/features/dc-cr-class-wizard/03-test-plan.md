# Test Plan: dc-cr-class-wizard

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Wizard Class — identity/stats, arcane spellbook, Arcane Bond, Arcane Thesis (4 options), Arcane Schools, feat progression
**KB reference:** spellcasting pattern referenced from dc-cr-class-cleric/03-test-plan.md and dc-cr-class-sorcerer/03-test-plan.md (prepared vs spontaneous distinction; spellcasting module dependency)
**Dependency note:** dc-cr-spellcasting (deferred) — 20 TCs deferred; 16 TCs immediately activatable at Stage 0

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Wizard class business logic, stat derivation, spellbook schema, Arcane Thesis selection, Arcane Bond, feat/boost gating |
| `role-url-audit` | HTTP role audit | ACL regression — no new Wizard-specific routes; existing character routes only |

---

## Test Cases

### TC-WIZ-01 — Wizard class selectable at character creation
- **Suite:** module-test-suite
- **Description:** Wizard appears in the class selection list during character creation.
- **Expected:** Wizard is a valid selectable class in the character creation flow.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-02 — Key ability: INT (fixed, no override)
- **Suite:** module-test-suite
- **Description:** Wizard key ability is fixed as INT at level 1; no alternative key ability selection offered.
- **Expected:** Key ability = INT; no STR/DEX/CHA/WIS key ability option for Wizard.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-03 — HP per level: 6 + CON modifier
- **Suite:** module-test-suite
- **Description:** At each level-up, Wizard gains exactly 6 + CON modifier HP (tied lowest with Sorcerer).
- **Expected:** HP delta per level = 6 + CON mod (minimum 1 per level if CON is negative).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-04 — Initial proficiency: Trained Perception
- **Suite:** module-test-suite
- **Description:** Wizard starts with Trained (not Expert) in Perception at level 1.
- **Expected:** Perception = Trained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-05 — Initial proficiencies: Expert Will, Trained Fortitude + Reflex
- **Suite:** module-test-suite
- **Description:** Wizard starts with Expert Will and Trained in both Fortitude and Reflex.
- **Expected:** Will = Expert, Fortitude = Trained, Reflex = Trained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-06 — Initial proficiencies: Untrained all armor
- **Suite:** module-test-suite
- **Description:** Wizard starts Untrained in all armor types (light, medium, heavy).
- **Expected:** All armor categories = Untrained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-07 — Initial proficiencies: specific weapon list
- **Suite:** module-test-suite
- **Description:** Wizard starts Trained in club, crossbow, dagger, heavy crossbow, staff, and unarmed attacks; all other weapons Untrained.
- **Expected:** Each listed weapon/attack = Trained; martial/advanced weapons = Untrained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-08 — Initial proficiencies: Trained arcane spell attacks/DCs (INT-based)
- **Suite:** module-test-suite
- **Description:** Wizard starts Trained in arcane spell attack rolls and arcane spell DCs; INT is the spellcasting ability.
- **Expected:** Arcane spell attack = Trained; arcane spell DC = Trained; spellcasting_ability = INT on character record.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-09 — Spellbook created at character creation with 10 cantrips + 10 level-1 spells
- **Suite:** module-test-suite
- **Description:** At level 1, a Wizard's personal spellbook is initialized with exactly 10 cantrips and 10 first-level arcane spells.
- **Expected:** spellbook record created for character; cantrip_count = 10; level_1_count = 10 at character level 1.
- **Notes to PM:** Confirm whether the 10 cantrips/10 level-1 spells are freely chosen from the arcane list or restricted to a starting set. This affects full parameterization of TC-WIZ-09.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (spell selection method needs PM clarification)

### TC-WIZ-10 — Arcane Thesis selection required at level 1
- **Suite:** module-test-suite
- **Description:** Player must select exactly one Arcane Thesis from {Improved Familiar Attunement, Metamagical Experimentation, Spell Blending, Spell Substitution} at level 1; no Wizard advances to level 2 without a Thesis.
- **Expected:** arcane_thesis field set on character; level advancement gated on selection.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-11 — Arcane Thesis: Improved Familiar Attunement grants Familiar feat
- **Suite:** module-test-suite
- **Description:** With Improved Familiar Attunement, Wizard gains the Familiar feat at level 1 and Drain Bonded Item draws from the familiar instead of a bonded item.
- **Expected:** Familiar feat added to character; drain_source = familiar (not bonded_item) for this thesis.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (familiar data model may need `dc-cr-familiars` — flag if unimplemented)

### TC-WIZ-12 — Arcane Thesis: Metamagical Experimentation grants one 1st-level metamagic feat at level 1
- **Suite:** module-test-suite
- **Description:** With Metamagical Experimentation, Wizard receives one 1st-level metamagic wizard feat at character level 1 as a bonus.
- **Expected:** One metamagic wizard feat (level requirement ≤ 1) added to character at creation.
- **Notes to PM:** Confirm the metamagic feat list in scope — this TC requires the feat catalog to include metamagic feats with level ≤ 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (metamagic feat catalog needed)

### TC-WIZ-13 — Arcane Thesis: Spell Substitution allows 10-minute slot replacement
- **Suite:** module-test-suite
- **Description:** With Spell Substitution, Wizard can spend 10 uninterrupted minutes to swap one prepared spell slot with a different spell from the spellbook.
- **Expected:** Spell substitution action available; replaces prepared spell with a spellbook spell of the same level; 10-minute downtime action type recorded.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (schema-level action type; does not require full spellcasting system)

### TC-WIZ-14 — Arcane Bond: Drain Bonded Item once per day
- **Suite:** module-test-suite
- **Description:** Arcane Bond grants one Drain Bonded Item use per day; attempting a second use the same day is blocked (without Superior Bond feat).
- **Expected:** drain_bonded_item_uses_today starts at 0; one use succeeds; second use = blocked (returns error).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-15 — Feat progression: all standard slots (class/general/skill/ancestry + ability boosts)
- **Suite:** module-test-suite
- **Description:** Wizard receives class feats at levels 1+even, general feats at 3/7/11/15/19, skill feats at even levels, ancestry feats at 5/9/13/17, ability boosts at 5/10/15/20.
- **Expected:** All feat/boost slots present at correct levels; none at incorrect levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-16 — Level-gated features do not appear before required level
- **Suite:** module-test-suite
- **Description:** Failure mode — class abilities and advanced features unavailable below their minimum level.
- **Expected:** Feature unlock list at level N contains only features with minimum_level ≤ N.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-WIZ-17 — ACL regression: no new routes introduced by Wizard
- **Suite:** role-url-audit
- **Description:** Wizard class implementation adds no new HTTP routes; existing character creation/leveling routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing character routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Deferred TCs — depend on dc-cr-spellcasting

### TC-WIZ-18 — Prepared casting: daily preparation from spellbook required
- **Suite:** module-test-suite
- **Description:** Wizard must prepare spells each day from the spellbook; cannot cast an arcane spell without having prepared it that day.
- **Expected:** Cast attempt for unprepared spell = blocked; slot consumption only after preparation.
- **Dependency note:** Requires dc-cr-spellcasting daily preparation system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-19 — Casting unprepared spell: blocked (unlike spontaneous casters)
- **Suite:** module-test-suite
- **Description:** Failure mode — attempting to cast a spellbook spell that was not prepared today returns an error; no slot consumed.
- **Expected:** Cast blocked; error returned; slot count unchanged.
- **Dependency note:** Requires dc-cr-spellcasting casting resolution system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-20 — Prepared spell heightening: cast in higher-level slot
- **Suite:** module-test-suite
- **Description:** A prepared spell can be cast using a slot of higher level than the spell's level; the spell heightens to the slot level.
- **Expected:** Spell prepared at level N can consume a level-M slot (M > N); spell effect scales to level M.
- **Dependency note:** Requires dc-cr-spellcasting prepared heightening system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-21 — Spellbook: new spells added via scribing (Arcana/Occultism check or cost)
- **Suite:** module-test-suite
- **Description:** A Wizard can add spells to the spellbook by paying the scribing cost or making the appropriate skill check; added spell is then preparable.
- **Expected:** After successful scribing, spell appears in spellbook; becomes preparable the next daily prep.
- **Dependency note:** Requires dc-cr-spellcasting spellbook management system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-22 — Spellbook spell not yet learned: cannot be prepared
- **Suite:** module-test-suite
- **Description:** Failure mode — a spell not in the spellbook cannot be prepared even if it is on the arcane list.
- **Expected:** Preparation attempt for a non-spellbook arcane spell returns error; slot unaffected.
- **Dependency note:** Requires dc-cr-spellcasting preparation system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-23 — Universalist: one free recall per spell level per day
- **Suite:** module-test-suite
- **Description:** Universalist Wizard may recall and recast one prepared spell per spell level (not just one total) per day for free.
- **Expected:** Recall use tracker has one charge per accessible spell level; each level's charge independent.
- **Notes to PM:** Confirm "Universalist" is the no-school option vs a named school — AC implies it is the generalist path. Also confirm this is an alternative to school selection (not additive).
- **Dependency note:** Requires dc-cr-spellcasting recall mechanic.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-24 — Drain Bonded Item: recalled spell must have been cast today
- **Suite:** module-test-suite
- **Description:** Edge case — Drain Bonded Item can only recall a spell that the Wizard has already cast at least once today; cannot recall a prepared-but-uncast spell.
- **Expected:** drain_bonded_item action fails if no matching cast-today record exists for the target spell.
- **Dependency note:** Requires dc-cr-spellcasting cast-history tracking.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-25 — Drain Bonded Item: recalled spell costs no spell slot
- **Suite:** module-test-suite
- **Description:** Using Drain Bonded Item to recall and cast a spell consumes no spell slot; the spell is cast from the bonded item's stored energy.
- **Expected:** After Drain Bonded Item cast, spell slot count unchanged; bonded_item_uses_today increments.
- **Dependency note:** Requires dc-cr-spellcasting slot tracking.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-26 — Arcane School: specialist gets extra school slot per level (school-only)
- **Suite:** module-test-suite
- **Description:** Specialist Wizard gets one additional spell slot per accessible spell level; that slot can only hold school spells.
- **Expected:** slot_count[level] = base + 1 (school slot) for specialist; school slot locked to school spell list.
- **Dependency note:** Requires dc-cr-spellcasting slot management and school spell list filtering.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-27 — Arcane School: specialist extra slot blocked for non-school spells
- **Suite:** module-test-suite
- **Description:** Failure mode — attempting to prepare a non-school spell in the specialist's bonus school slot is rejected.
- **Expected:** Preparation of non-school spell in bonus slot returns error; slot unchanged.
- **Dependency note:** Requires dc-cr-spellcasting preparation system with slot-type enforcement.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-28 — Arcane School: extra cantrip slot
- **Suite:** module-test-suite
- **Description:** Specialist Wizard receives one additional cantrip slot from their school.
- **Expected:** cantrip_slot_count = base + 1 for specialist; extra slot available for any arcane cantrip (school-restricted or general, per PM clarification).
- **Notes to PM:** Confirm whether the specialist extra cantrip slot is school-restricted or open to any arcane cantrip.
- **Dependency note:** Requires dc-cr-spellcasting cantrip slot management.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-29 — Arcane School: one extra school spell added to spellbook at level 1
- **Suite:** module-test-suite
- **Description:** Specialist starts with one additional school spell in their spellbook at level 1.
- **Expected:** Spellbook at level 1 contains 10 cantrips + 10 level-1 arcane spells + 1 school spell.
- **Dependency note:** Requires dc-cr-spellcasting spellbook initialization.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-30 — Metamagical Experimentation: daily prep grants temporary metamagic feat from level 4
- **Suite:** module-test-suite
- **Description:** From character level 4, Metamagical Experimentation grants one temporary metamagic wizard feat (level ≤ half character level) during each daily preparation; feat expires at next prep.
- **Expected:** At levels 4+, temp_metamagic_feat field set during prep; field expires/clears at next prep.
- **Dependency note:** Requires dc-cr-spellcasting daily preparation system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-31 — Spell Blending: trade two same-level slots for one higher-level slot during prep
- **Suite:** module-test-suite
- **Description:** During daily preparation, Spell Blending Wizard may sacrifice two slots of the same level to gain one slot up to 2 levels higher; each level of trade must be distinct.
- **Expected:** Slot trade valid when: source level ≥ 1; destination level ≤ source + 2; destination level ≤ highest accessible slot level; each trade combination unique per prep.
- **Dependency note:** Requires dc-cr-spellcasting preparation and slot management.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-32 — Spell Blending: bonus slot capped at highest castable level
- **Suite:** module-test-suite
- **Description:** Edge case — Spell Blending cannot create a slot above the Wizard's current maximum castable spell level.
- **Expected:** Trade attempt that would produce a slot above max level returns error; original slots unaffected.
- **Dependency note:** Requires dc-cr-spellcasting slot management.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-33 — Spell Blending: trade one slot for two additional cantrips (once per prep)
- **Suite:** module-test-suite
- **Description:** Alternatively, Spell Blending Wizard may trade one spell slot for two additional cantrip preparations; this cantrip trade is available once per daily prep.
- **Expected:** cantrip_slot_count += 2; one non-cantrip slot consumed; cantrip_trade_used = true for that prep session.
- **Dependency note:** Requires dc-cr-spellcasting preparation system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-34 — Counterspell: requires specific spell prepared (not just in spellbook)
- **Suite:** module-test-suite
- **Description:** Edge case — Wizard Counterspell requires the specific spell being countered to be currently prepared; having it in the spellbook but unprepared is insufficient (without Clever Counterspell feat).
- **Expected:** Counterspell attempt with unprepared-but-spellbook spell = blocked; prepared spell = succeeds (slot consumed).
- **Dependency note:** Requires dc-cr-spellcasting casting and counterspell system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-35 — Metamagic timing: intervening action wastes benefit (same as Sorcerer)
- **Suite:** module-test-suite
- **Description:** Edge case — if any action occurs between metamagic and Cast a Spell, the metamagic benefit is lost (same rule as Sorcerer TC-SOR-35).
- **Expected:** Intervening action after metamagic → metamagic expires before cast resolves.
- **Dependency note:** Requires dc-cr-spellcasting action queue/metamagic tracking.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-WIZ-36 — Improved Familiar Attunement: familiar extra abilities at levels 6, 12, 18
- **Suite:** module-test-suite
- **Description:** Wizard with Improved Familiar Attunement thesis gains +1 familiar ability at levels 6, 12, and 18 beyond the standard familiar ability count.
- **Expected:** familiar_extra_abilities = +1 at level 6; +2 at level 12; +3 at level 18.
- **Dependency note:** Requires dc-cr-familiars system (separate from spellcasting).
- **Status:** deferred — pending `dc-cr-familiars` (separate dependency from dc-cr-spellcasting)

### TC-WIZ-37 — Advanced school spell unlocked via feat at level 8
- **Suite:** module-test-suite
- **Description:** Specialist Wizard can unlock their school's advanced school spell via a specific feat at level 8; the spell is added to the spellbook and becomes preparable.
- **Expected:** advanced_school_spell available in feat selection at level 8; on selection, spell added to spellbook.
- **Dependency note:** Requires dc-cr-spellcasting spellbook management.
- **Status:** deferred — pending `dc-cr-spellcasting`

---

## Deferred dependency summary

| TC | Dependency feature | Reason deferred |
|---|---|---|
| TC-WIZ-18 | `dc-cr-spellcasting` | Daily preparation system |
| TC-WIZ-19 | `dc-cr-spellcasting` | Unprepared spell block |
| TC-WIZ-20 | `dc-cr-spellcasting` | Prepared heightening |
| TC-WIZ-21 | `dc-cr-spellcasting` | Spellbook scribing |
| TC-WIZ-22 | `dc-cr-spellcasting` | Spellbook preparation guard |
| TC-WIZ-23 | `dc-cr-spellcasting` | Universalist recall per spell level |
| TC-WIZ-24 | `dc-cr-spellcasting` | Drain Bonded Item cast-today check |
| TC-WIZ-25 | `dc-cr-spellcasting` | Drain Bonded Item slot-free cast |
| TC-WIZ-26 | `dc-cr-spellcasting` | Specialist extra school slot |
| TC-WIZ-27 | `dc-cr-spellcasting` | Specialist bonus slot school-only enforcement |
| TC-WIZ-28 | `dc-cr-spellcasting` | Specialist extra cantrip slot |
| TC-WIZ-29 | `dc-cr-spellcasting` | Specialist initial school spell in spellbook |
| TC-WIZ-30 | `dc-cr-spellcasting` | Metamagical Experimentation temp feat (level 4+) |
| TC-WIZ-31 | `dc-cr-spellcasting` | Spell Blending slot trade |
| TC-WIZ-32 | `dc-cr-spellcasting` | Spell Blending cap enforcement |
| TC-WIZ-33 | `dc-cr-spellcasting` | Spell Blending cantrip trade |
| TC-WIZ-34 | `dc-cr-spellcasting` | Counterspell prepared-only enforcement |
| TC-WIZ-35 | `dc-cr-spellcasting` | Metamagic timing |
| TC-WIZ-36 | `dc-cr-familiars` | Improved Familiar Attunement level scaling |
| TC-WIZ-37 | `dc-cr-spellcasting` | Advanced school spell feat |

16 TCs immediately activatable at Stage 0.
19 TCs deferred pending `dc-cr-spellcasting`.
1 TC (TC-WIZ-36) deferred pending `dc-cr-familiars` (separate from spellcasting).

---

## Notes to PM

1. **TC-WIZ-09 (spellbook initialization):** Confirm whether the 10 cantrips and 10 level-1 spells at character creation are freely chosen from the full arcane list or restricted to a GM/setting-determined starting set. This drives the parameterization of the spellbook init assertion.
2. **TC-WIZ-12 (metamagic feat catalog):** Metamagical Experimentation grants a 1st-level metamagic wizard feat at level 1. The feat catalog in dc-cr scope needs at least one metamagic feat with minimum level ≤ 1 for this TC to be testable. Confirm which metamagic feats are in scope.
3. **TC-WIZ-23 (Universalist):** Confirm Universalist is the "no school selected" path (not a school itself) and that free recall charges are per-spell-level (not a single global charge). This significantly affects the data model.
4. **TC-WIZ-28 (school extra cantrip):** Confirm whether the specialist's extra cantrip slot is restricted to school cantrips or open to any arcane cantrip.
5. **TC-WIZ-36 (Improved Familiar Attunement):** This TC depends on `dc-cr-familiars` which is a separate system from `dc-cr-spellcasting`. If familiars ship in a different release than spellcasting, this TC activates independently.
6. **Activation sequencing:** Like Sorcerer, Wizard should NOT enter full release scope until `dc-cr-spellcasting` ships. The 16 identity/thesis-selection/Arcane Bond TCs can activate immediately; the 19 spellcasting TCs wait. Wizard has more complex spellcasting than Sorcerer (spellbook + school slots + Drain Bonded Item cast-today tracking) — recommend spellcasting module implementation notes explicitly address the prepared-casting data model before Wizard is scheduled.
