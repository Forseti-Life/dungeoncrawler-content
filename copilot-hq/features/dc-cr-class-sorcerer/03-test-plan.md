# Test Plan: dc-cr-class-sorcerer

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Sorcerer Class — identity/stats, Bloodline subclass, spontaneous spellcasting, spell repertoire, signature spells, feat progression
**KB reference:** none found (first Sorcerer class feature); spellcasting pattern referenced from dc-cr-class-cleric/03-test-plan.md (prepared vs spontaneous distinction)
**Dependency note:** dc-cr-spellcasting (deferred) — 19 TCs deferred; 17 TCs immediately activatable at Stage 0

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Sorcerer class business logic, stat derivation, bloodline selection, spell repertoire schema, feat/boost gating |
| `role-url-audit` | HTTP role audit | ACL regression — no new Sorcerer-specific routes; existing character routes only |

---

## Test Cases

### TC-SOR-01 — Sorcerer class selectable at character creation
- **Suite:** module-test-suite
- **Description:** Sorcerer appears in the class selection list during character creation.
- **Expected:** Sorcerer is a valid selectable class in the character creation flow.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-02 — Key ability: CHA (fixed, no override)
- **Suite:** module-test-suite
- **Description:** Sorcerer key ability is fixed as CHA at level 1; no alternative key ability selection offered.
- **Expected:** Key ability = CHA; no STR/DEX/WIS/INT key ability option for Sorcerer.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-03 — HP per level: 6 + CON modifier
- **Suite:** module-test-suite
- **Description:** At each level-up, Sorcerer gains exactly 6 + CON modifier HP.
- **Expected:** HP delta per level = 6 + CON mod (minimum 1 per level if CON is negative). Lowest among spellcasting classes.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-04 — Initial proficiency: Trained Perception
- **Suite:** module-test-suite
- **Description:** Sorcerer starts with Trained (not Expert) in Perception at level 1.
- **Expected:** Perception = Trained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-05 — Initial proficiencies: saves (Expert Will, Trained Fortitude + Reflex)
- **Suite:** module-test-suite
- **Description:** Sorcerer starts with Expert Will and Trained in both Fortitude and Reflex.
- **Expected:** Will = Expert, Fortitude = Trained, Reflex = Trained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-06 — Initial proficiencies: Untrained all armor
- **Suite:** module-test-suite
- **Description:** Sorcerer starts Untrained in all armor types (light, medium, heavy).
- **Expected:** All armor categories = Untrained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-07 — Initial proficiencies: Trained unarmed attacks and simple weapons
- **Suite:** module-test-suite
- **Description:** Sorcerer starts Trained in unarmed attacks and simple weapons.
- **Expected:** Unarmed = Trained, simple weapons = Trained; martial/advanced = Untrained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-08 — Bloodline selection required at level 1
- **Suite:** module-test-suite
- **Description:** Player must select exactly one Bloodline at level 1; no Sorcerer advances to level 2 without a Bloodline.
- **Expected:** Bloodline selection recorded on character; level advancement gated on selection.
- **Notes to PM:** Enumerate the full bloodline catalog available in dc-cr scope (AC mentions Draconic and Elemental explicitly; other bloodlines like Fey, Imperial, Undead, etc. need AC enumeration for full parameterization of TC-SOR-08 option list).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (full bloodline list needs PM enumeration)

### TC-SOR-09 — Bloodline determines spell tradition
- **Suite:** module-test-suite
- **Description:** The selected Bloodline sets the Sorcerer's spell tradition (arcane/divine/occult/primal) on the character record.
- **Expected:** Character's spell_tradition field = bloodline's tradition; Trained spell attacks/DCs recorded with that tradition.
- **Notes to PM:** Confirm bloodline-to-tradition mapping table exists in AC or data model (e.g., Draconic = arcane, Angelic = divine, etc.).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (tradition mapping table needed for full parameterization)

### TC-SOR-10 — Draconic bloodline: dragon type sub-selection (10 options)
- **Suite:** module-test-suite
- **Description:** With Draconic Bloodline, player selects one of 10 dragon types; the selection determines bloodline spell damage type.
- **Expected:** Dragon type sub-selection required when Bloodline = Draconic; exactly 10 valid options; damage_type on bloodline spells = dragon type's element.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-11 — Elemental bloodline: element sub-selection (4 options)
- **Suite:** module-test-suite
- **Description:** With Elemental Bloodline, player selects one element (air/earth/fire/water); element determines bloodline spell damage type.
- **Expected:** Element sub-selection required when Bloodline = Elemental; exactly 4 valid options.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-12 — Bloodline spells locked in repertoire (cannot be removed)
- **Suite:** module-test-suite
- **Description:** Bloodline-granted spells are permanently part of the spell repertoire and cannot be swapped out via retraining or manual removal.
- **Expected:** Bloodline spells appear in repertoire with locked=true; no UI path or API call permits their removal.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (schema-level; does not require full spellcasting system)

### TC-SOR-13 — Spell tradition proficiency: Trained spell attacks and DCs
- **Suite:** module-test-suite
- **Description:** Sorcerer starts Trained in spell attacks and spell DCs for their bloodline's tradition at level 1.
- **Expected:** Spell attack proficiency = Trained; spell DC proficiency = Trained; tradition = bloodline tradition.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-14 — Feat progression: class feat at level 1 and every even level
- **Suite:** module-test-suite
- **Description:** Sorcerer receives class feat slots at levels 1, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20.
- **Expected:** Class feat slot at each of those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-15 — Feat progression: general feats at levels 3, 7, 11, 15, 19
- **Suite:** module-test-suite
- **Description:** Sorcerer receives general feat slots at levels 3, 7, 11, 15, 19 only.
- **Expected:** General feat slots at exactly those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-16 — Feat progression: skill feats every even level
- **Suite:** module-test-suite
- **Description:** Sorcerer receives skill feat slots at levels 2, 4, 6, 8, 10, 12, 14, 16, 18, 20.
- **Expected:** Skill feat slot at each even level.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-17 — Feat progression: ancestry feats at levels 5, 9, 13, 17
- **Suite:** module-test-suite
- **Description:** Sorcerer receives ancestry feat slots at levels 5, 9, 13, 17 only.
- **Expected:** Ancestry feat slots at exactly those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-18 — Ability boosts at levels 5, 10, 15, 20
- **Suite:** module-test-suite
- **Description:** Sorcerer receives four ability boosts at levels 5, 10, 15, 20.
- **Expected:** Four ability boost choices available at each of those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-19 — Level-gated features do not appear before required level
- **Suite:** module-test-suite
- **Description:** Failure mode — class abilities and feat slots unavailable below their minimum level.
- **Expected:** Feature unlock list at level N contains only features with minimum_level ≤ N.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOR-20 — ACL regression: no new routes introduced by Sorcerer
- **Suite:** role-url-audit
- **Description:** Sorcerer implementation adds no new HTTP routes; existing character routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing character routes; HTTP 403 for anonymous on auth-required routes.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Deferred TCs — depend on dc-cr-spellcasting

### TC-SOR-21 — Spontaneous casting: spells selected from repertoire at cast time (no preparation)
- **Suite:** module-test-suite
- **Description:** Sorcerer casts any spell in their repertoire without daily preparation; spell selection happens at cast time.
- **Expected:** No "prepare spells" step; any repertoire spell available when a slot of equal or higher level is available.
- **Dependency note:** Requires dc-cr-spellcasting spell slot system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-22 — Material components replaced by somatic components (no component pouch required)
- **Suite:** module-test-suite
- **Description:** Spells with material components do not require a component pouch; somatic component substitutes automatically.
- **Expected:** No component pouch in equipment; material component check passes via somatic substitution.
- **Dependency note:** Requires dc-cr-spellcasting component system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-23 — Spell slots and spell repertoire tracked independently
- **Suite:** module-test-suite
- **Description:** Knowing a spell (repertoire entry) is separate from having a slot to cast it; slot exhaustion does not remove spell from repertoire.
- **Expected:** After all slots of a given level are used, the spell remains in repertoire (no "forgetting"); only casting is blocked.
- **Dependency note:** Requires dc-cr-spellcasting slot management.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-24 — Cantrips auto-heighten to half level rounded up
- **Suite:** module-test-suite
- **Description:** Cantrips automatically cast at heightened level = floor(character_level / 2) rounded up; no manual heighten needed.
- **Expected:** Level 1 cantrip = level 1; level 3 cantrip = level 2; level 5 = level 3; level 10 = level 5; level 20 = level 10.
- **Dependency note:** Requires dc-cr-spellcasting cantrip heightening system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-25 — New spell levels unlock at odd character levels
- **Suite:** module-test-suite
- **Description:** Sorcerer gains access to the next spell rank at odd character levels (3rd, 5th, 7th, 9th, etc.) per advancement table.
- **Expected:** Spell level 2 available at character level 3; spell level 3 at level 5; etc.
- **Dependency note:** Requires dc-cr-spellcasting spell level unlock system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-26 — Spell slot count does not exceed advancement table values
- **Suite:** module-test-suite
- **Description:** Failure mode — spell slot counts at each level are capped to the Sorcerer advancement table; no additional slots from non-slot sources.
- **Expected:** Slot count per spell level = advancement table value; no overflow.
- **Dependency note:** Requires dc-cr-spellcasting slot management.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-27 — Signature spell designation: one per spell level (level 3+)
- **Suite:** module-test-suite
- **Description:** Starting at character level 3, Sorcerer may designate one signature spell per accessible spell level; designation is stored on character.
- **Expected:** One signature slot per spell level from level 1 through highest accessible; designation persists on level-up.
- **Dependency note:** Requires dc-cr-spellcasting spell repertoire system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-28 — Signature spell: free heightening to any castable level
- **Suite:** module-test-suite
- **Description:** A designated signature spell can be cast at any spell level for which the Sorcerer has a slot, without learning separate higher-level versions.
- **Expected:** Signature spell castable at level 1 through highest available slot level; damage/effect scales correctly.
- **Dependency note:** Requires dc-cr-spellcasting heightening system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-29 — Non-signature spells: cannot be freely heightened beyond known level
- **Suite:** module-test-suite
- **Description:** Spells that are not designated as signature spells can only be cast at the level they were learned; cannot be spontaneously heightened.
- **Expected:** Non-signature spell only available at its learned level; no free heighten option.
- **Dependency note:** Requires dc-cr-spellcasting spell slot system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-30 — Casting spell not in repertoire: blocked
- **Suite:** module-test-suite
- **Description:** Failure mode — Sorcerer cannot cast a spell that is not in their known repertoire, even if they have available slots.
- **Expected:** Cast action for non-repertoire spell returns error/blocked state; spell slot not consumed.
- **Dependency note:** Requires dc-cr-spellcasting casting system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-31 — Spell and signature retraining via downtime
- **Suite:** module-test-suite
- **Description:** Sorcerer can swap a known spell and/or signature designation during downtime via the retraining mechanic.
- **Expected:** After downtime retraining, replaced spell removed from repertoire; new spell added; signature updated if changed.
- **Dependency note:** Requires dc-cr-spellcasting and character downtime/retraining system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-32 — Blood magic: triggers on bloodline spell slot cast when target fails save or attack succeeds
- **Suite:** module-test-suite
- **Description:** Blood magic effect activates when the Sorcerer casts a bloodline spell via a spell slot AND the target fails the save (or attack roll succeeds).
- **Expected:** Blood magic effect recorded/applied on qualifying cast; no trigger on miss or save success.
- **Dependency note:** Requires dc-cr-spellcasting for cast resolution and condition application.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-33 — Blood magic: area spells allow target designation
- **Suite:** module-test-suite
- **Description:** When blood magic triggers from an area spell, the player may designate one target from those affected to receive the blood magic effect.
- **Expected:** Designation prompt shown for area bloodline spells when blood magic triggers; effect applied to chosen target only.
- **Dependency note:** Requires dc-cr-spellcasting area targeting system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-34 — Bloodline focus spell blood magic trigger
- **Suite:** module-test-suite
- **Description:** Blood magic also triggers when casting a bloodline focus spell (same success/fail trigger conditions as spell slot version).
- **Expected:** Focus spell cast that hits or causes save failure → blood magic activates.
- **Dependency note:** Requires dc-cr-spellcasting and dc-cr-focus-spells systems.
- **Status:** deferred — pending `dc-cr-spellcasting` + `dc-cr-focus-spells`

### TC-SOR-35 — Metamagic timing: action gap wastes benefit
- **Suite:** module-test-suite
- **Description:** Edge case — if any action (free action or reaction) occurs between the metamagic action and Cast a Spell, the metamagic benefit is lost.
- **Expected:** Inserting an intermediate action after metamagic and before Cast a Spell = metamagic benefit removed/expired.
- **Dependency note:** Requires dc-cr-spellcasting action queue/metamagic tracking system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-36 — Cross-tradition spell (Crossblooded Evolution): cast as bloodline tradition
- **Suite:** module-test-suite
- **Description:** A spell added via Crossblooded Evolution feat (from another tradition) is cast as the bloodline's tradition, not its original tradition.
- **Expected:** Cross-tradition spell uses bloodline tradition for proficiency calculation and tradition-specific effects.
- **Dependency note:** Requires dc-cr-spellcasting tradition system and Crossblooded Evolution feat implementation.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-37 — Bloodline spell slot restriction: bloodline spells consume correct spell level slot
- **Suite:** module-test-suite
- **Description:** Bloodline spells cast via spell slot consume a slot of the correct spell level; cannot be cast for free beyond repertoire limits.
- **Expected:** Bloodline spell cast at level N consumes one level-N spell slot; slot tracking decrements correctly.
- **Dependency note:** Requires dc-cr-spellcasting slot consumption system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-SOR-38 — Blood magic focus spell cast: focus pool interaction
- **Suite:** module-test-suite
- **Description:** Bloodline focus spells consume Focus Points from the focus pool; pool starts at 1 (increased by further feats); cast with 0 FP blocked.
- **Expected:** Focus spell cast costs 1 FP; pool decrements; 0 FP = cast blocked; refocus restores 1 FP.
- **Dependency note:** Requires dc-cr-spellcasting focus pool and dc-cr-focus-spells systems.
- **Status:** deferred — pending `dc-cr-spellcasting` + `dc-cr-focus-spells`

### TC-SOR-39 — Bloodline spells cannot be removed from repertoire (schema guard)
- **Suite:** module-test-suite
- **Description:** Failure mode — API/UI attempt to remove a bloodline-locked spell from the repertoire is rejected.
- **Expected:** Delete or swap call on a locked bloodline spell returns error; repertoire unchanged.
- **Dependency note:** Partially testable at schema level (TC-SOR-12 covers the schema lock); this TC validates enforcement at the service/API layer, which requires dc-cr-spellcasting.
- **Status:** deferred — pending `dc-cr-spellcasting` (schema lock covered by TC-SOR-12)

---

## Deferred dependency summary

| TC | Dependency feature | Reason deferred |
|---|---|---|
| TC-SOR-21 | `dc-cr-spellcasting` | Spontaneous casting mechanics |
| TC-SOR-22 | `dc-cr-spellcasting` | Component substitution system |
| TC-SOR-23 | `dc-cr-spellcasting` | Slot vs repertoire independence |
| TC-SOR-24 | `dc-cr-spellcasting` | Cantrip auto-heighten |
| TC-SOR-25 | `dc-cr-spellcasting` | Spell level unlock schedule |
| TC-SOR-26 | `dc-cr-spellcasting` | Slot count cap enforcement |
| TC-SOR-27 | `dc-cr-spellcasting` | Signature spell designation |
| TC-SOR-28 | `dc-cr-spellcasting` | Signature free heightening |
| TC-SOR-29 | `dc-cr-spellcasting` | Non-signature heighten block |
| TC-SOR-30 | `dc-cr-spellcasting` | Out-of-repertoire cast block |
| TC-SOR-31 | `dc-cr-spellcasting` | Downtime retraining |
| TC-SOR-32 | `dc-cr-spellcasting` | Blood magic trigger (spell slot) |
| TC-SOR-33 | `dc-cr-spellcasting` | Blood magic area designation |
| TC-SOR-34 | `dc-cr-spellcasting` + `dc-cr-focus-spells` | Blood magic focus spell trigger |
| TC-SOR-35 | `dc-cr-spellcasting` | Metamagic timing/action queue |
| TC-SOR-36 | `dc-cr-spellcasting` | Cross-tradition (Crossblooded Evolution) |
| TC-SOR-37 | `dc-cr-spellcasting` | Bloodline spell slot consumption |
| TC-SOR-38 | `dc-cr-spellcasting` + `dc-cr-focus-spells` | Focus pool management |
| TC-SOR-39 | `dc-cr-spellcasting` | API-layer bloodline lock enforcement |

17 TCs immediately activatable at Stage 0.
19 TCs deferred pending `dc-cr-spellcasting` (and 2 of those also pending `dc-cr-focus-spells`).

---

## Notes to PM

1. **TC-SOR-08 (bloodline catalog):** AC explicitly covers Draconic and Elemental; the full bloodline list for dc-cr scope (Angelic, Demonic, Fey, Imperial, Nymph, Undead, etc.) needs enumeration in AC or the data model before TC-SOR-08 can be fully parameterized.
2. **TC-SOR-09 (tradition mapping):** Confirm bloodline → tradition mapping table (e.g., Draconic = arcane; Angelic = divine; Fey = primal/occult). This drives TC-SOR-09 parameterization and is needed before dev implements the bloodline data model.
3. **Activation sequencing:** Sorcerer's 19 deferred TCs make it a poor candidate for early Stage 0 activation without `dc-cr-spellcasting`. Recommend: activate 17 identity/stat/bloodline-selection TCs immediately, defer remaining 19 until spellcasting ships. Do NOT add Sorcerer to release scope until at least TC-SOR-21 through TC-SOR-26 are activatable.
4. **Focus pool (TC-SOR-34/38):** Sorcerer's bloodline focus spells share the dc-cr-focus-spells dependency pattern seen in Cleric and Druid. These 2 TCs require both spellcasting and focus spell systems.
