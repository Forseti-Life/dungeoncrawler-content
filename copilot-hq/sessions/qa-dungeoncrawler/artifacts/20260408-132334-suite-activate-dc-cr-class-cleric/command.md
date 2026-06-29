# Suite Activation: dc-cr-class-cleric

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:23:34+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-class-cleric"`**  
   This links the test to the living requirements doc at `features/dc-cr-class-cleric/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-class-cleric-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-class-cleric",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-class-cleric"`**  
   Example:
   ```json
   {
     "id": "dc-cr-class-cleric-<route-slug>",
     "feature_id": "dc-cr-class-cleric",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-class-cleric",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-class-cleric

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Cleric Class — identity/stats, deity/anathema, doctrine subclass, prepared divine spellcasting, divine font, level-gated features, feat progression, edge cases
**AC source:** `features/dc-cr-class-cleric/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Cleric class logic; first cleric implementation.
- Dependency note: `dc-cr-spellcasting` is **deferred**. All TC groups that depend on spellcasting mechanics (prepared slots, cantrip scaling, spell preparation flow) must be flagged `pending-dev-confirmation` at Stage 0 activation until `dc-cr-spellcasting` ships. The class identity, proficiency, deity, anathema, divine font, and doctrine TCs have no deferred dependencies and can activate at Stage 0 immediately.
- `dc-cr-character-class` ✓ and `dc-cr-character-creation` ✓ — no blocker on those.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class selection, HP/proficiency, deity/anathema, doctrine, divine font, level-gated features, edge cases |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, QA audit regression |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. No Playwright suite needed at this scope.

---

## Test cases

### TC-CLR-01 — Cleric exists as selectable class; WIS as key ability
- **AC:** `[NEW]` Cleric selectable in character creation; Wisdom as key ability boost at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericClassTest::testClericExistsWithWisdomKeyAbility()`
- **Setup:** Query `character_class` nodes; load cleric; create cleric character
- **Expected:** Cleric class node exists; key_ability = Wisdom; no other key ability accepted
- **Roles:** authenticated player

### TC-CLR-02 — Cleric HP calculation: 8 + CON modifier per level
- **AC:** `[NEW]` Cleric HP = 8 + CON modifier per level
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericClassTest::testHpCalculation()`
- **Setup:** Create Cleric with CON 14 (mod +2); inspect HP at level 1 and level 2
- **Expected:** Level 1 HP = 10; Level 2 HP = 20
- **Roles:** authenticated player

### TC-CLR-03 — Cleric initial proficiencies: Perception/saves/weapon defaults
- **AC:** `[NEW]` Trained Perception; Expert Will, Trained Fortitude and Reflex; Trained deity's favored weapon; untrained all armor by default; Trained divine spell attacks/DCs (WIS)
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericClassTest::testInitialProficiencies()`
- **Setup:** Create Cleric with no doctrine selected yet; inspect proficiency fields
- **Expected:** Perception=Trained; Will=Expert; Fortitude=Trained; Reflex=Trained; deity_favored_weapon=Trained; armor proficiency = untrained (default, before doctrine); divine_spell_attack=Trained; divine_spell_dc=Trained (WIS-based)
- **Roles:** authenticated player

### TC-CLR-04 — Deity selection: approved alignment enforced
- **AC:** `[NEW]` At level 1, player selects a deity with approved alignment; anathema violation removes divine connection until atone
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDeityTest::testDeityAlignmentRequirementEnforced()`
- **Setup:** Create Cleric; select deity with approved alignment (success); select deity with mismatched alignment (failure)
- **Expected:** Approved alignment: deity selection accepted; mismatched alignment: validation error referencing deity alignment requirement
- **Roles:** authenticated player

### TC-CLR-05 — Anathema violation: divine connection suspended; spellcasting still works but domain spells/deity abilities disabled
- **AC:** `[NEW]` Anathema violation removes divine connection until atone; spellcasting still works but domain spells and deity abilities disabled
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDeityTest::testAnathemaViolationEffects()`
- **Setup:** Cleric with active divine connection, domain spells, and deity abilities; trigger anathema violation; inspect each
- **Expected:** divine_connection = suspended; domain_spells = inaccessible; deity_abilities = inaccessible; regular spellcasting (non-domain) = still accessible
- **Roles:** authenticated player

### TC-CLR-06 — Atone ritual restores divine connection
- **AC:** `[NEW]` Atone ritual restores divine connection (implied by anathema AC)
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDeityTest::testAtoneRitualRestoresDivineConnection()`
- **Setup:** Cleric in anathema-violated state; trigger atone ritual; re-inspect
- **Expected:** divine_connection = active; domain_spells = accessible; deity_abilities = accessible
- **Roles:** authenticated player

### TC-CLR-07 — Doctrine selection: Cloistered Cleric accepted
- **AC:** `[NEW]` At level 1, player selects Cloistered Cleric doctrine
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDoctrineTest::testCloisteredClericDoctrineSelected()`
- **Setup:** Create level-1 Cleric; select Cloistered Cleric doctrine; inspect doctrine field and associated stat modifiers
- **Expected:** doctrine = Cloistered_Cleric stored; faster spell DC/attack progression flag active; domain emphasis flag active
- **Roles:** authenticated player

### TC-CLR-08 — Doctrine selection: Warpriest accepted; grants armor/weapon proficiencies
- **AC:** `[NEW]` At level 1, player selects Warpriest doctrine; gains armor and weapon proficiencies; slower spell DC/attack progression
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDoctrineTest::testWarpriestDoctrineGrantsArmorAndWeaponProficiency()`
- **Setup:** Create level-1 Cleric; select Warpriest doctrine; inspect armor and weapon proficiency fields
- **Expected:** doctrine = Warpriest stored; light_armor = Trained (at minimum); martial weapons = Trained (or light/deity weapon upgraded); slower_spell_progression flag active
- **Roles:** authenticated player
- **Dependency note:** Exact armor/weapon proficiency tiers granted by Warpriest are not specified at this AC level. PM should enumerate exact proficiency grants before this TC is fully specified.

### TC-CLR-09 — Only one doctrine allowed; selection is permanent
- **AC:** `[TEST-ONLY]` Doctrine selection is permanent (implied by class design pattern)
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDoctrineTest::testDoctrineSelectionIsPermanent()`
- **Setup:** Cleric with Cloistered Cleric selected; attempt to change to Warpriest
- **Expected:** Change blocked; error references immutable doctrine selection
- **Roles:** authenticated player

### TC-CLR-10 — Cloistered vs Warpriest spell DC/attack progression differs
- **AC:** `[NEW]` Cloistered: faster spell DC/attack progression; Warpriest: slower spell DC/attack progression
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDoctrineTest::testDoctrineSpellProgressionDifference()`
- **Setup:** Two clerics at the same level — one Cloistered, one Warpriest; compare divine_spell_attack and divine_spell_dc proficiency tier
- **Expected:** At the same level, Cloistered Cleric has higher spell proficiency tier than Warpriest
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting` for exact proficiency progression tables. Flag `pending-dev-confirmation` at Stage 0 until `dc-cr-spellcasting` ships.

### TC-CLR-11 — Cleric uses prepared divine spellcasting; must prepare spells each day
- **AC:** `[NEW]` Cleric uses prepared spellcasting (not spontaneous); must prepare spells each day
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericSpellcastingTest::testPreparedSpellcastingModel()`
- **Setup:** Create Cleric; inspect spellcasting_type; attempt to cast an unprepared spell
- **Expected:** spellcasting_type = prepared; unprepared spell cast attempt blocked; error references daily preparation requirement
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-12 — Religious symbol acts as divine focus replacing material components
- **AC:** `[NEW]` Religious symbol acts as divine focus replacing material components
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericSpellcastingTest::testReligiousSymbolAsDivineFocus()`
- **Setup:** Cleric with religious symbol in inventory; cast a spell with material component requirement; inspect whether material component is bypassed
- **Expected:** Material component requirement bypassed when religious symbol present; spell cast succeeds without separate material components
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting` and `dc-cr-equipment-system`. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-13 — Spell slots and cantrips scale per advancement table
- **AC:** `[NEW]` Spell slots and cantrips scale per advancement table
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericSpellcastingTest::testSpellSlotsAndCantripsScale()`
- **Setup:** Level Cleric 1 → 3 → 5; inspect spell_slots by level and cantrips_available at each level
- **Expected:** Spell slots increase per PF2E Cleric advancement table; cantrips scale (cantrips don't consume slots); WIS modifier used for spell_attack and spell_dc
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-14 — WIS modifier used for divine spell attacks and DCs
- **AC:** `[NEW]` Wisdom modifier used for spell attacks and DCs
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericSpellcastingTest::testWisdomUsedForSpellAttackAndDc()`
- **Setup:** Cleric with WIS 18 (mod +4); inspect spell_attack_modifier and spell_dc
- **Expected:** spell_attack_modifier references WIS mod; spell_dc = 10 + proficiency_bonus + WIS mod
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-15 — Healing Font granted for deities that grant it; bonus slots = 1 + CHA mod
- **AC:** `[NEW]` Healing Font: prepare heal spells in bonus slots = 1 + CHA modifier; at highest spell level accessible
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testHealingFontGranted()`
- **Setup:** Cleric with CHA 14 (mod +2) with a deity granting Healing Font; inspect divine_font type and font_slots count
- **Expected:** divine_font = Healing; font_slots = 3 (1+2); font slot spell level = highest accessible; heal spells available in those slots
- **Roles:** authenticated player

### TC-CLR-16 — Harmful Font granted for deities that grant it; bonus slots = 1 + CHA mod
- **AC:** `[NEW]` Harmful Font: prepare harm spells in bonus slots = 1 + CHA modifier
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testHarmfulFontGranted()`
- **Setup:** Cleric with CHA 12 (mod +1) with a deity granting Harmful Font; inspect divine_font type and font_slots count
- **Expected:** divine_font = Harmful; font_slots = 2 (1+1); harm spells available in those slots
- **Roles:** authenticated player

### TC-CLR-17 — Font choice is locked without Versatile Font feat
- **AC:** `[NEW]` Font choice is locked unless deity allows both and Versatile Font feat is taken
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testFontChoiceLockedWithoutVersatileFeat()`
- **Setup:** Cleric with Healing Font, no Versatile Font feat; attempt to prepare harm spells in font slots
- **Expected:** harm spell in font slot blocked; error references font lock
- **Roles:** authenticated player

### TC-CLR-18 — Versatile Font feat: both heal and harm fill font slots
- **AC:** `[NEW]` Healing Font with Versatile Font: both heal and harm can fill font slots
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testVersatileFontAllowsBothHealAndHarm()`
- **Setup:** Cleric with deity allowing both fonts and Versatile Font feat taken; prepare both heal and harm in font slots
- **Expected:** Both heal and harm spells accepted in font slots; total font slot count unchanged (still 1 + CHA)
- **Roles:** authenticated player

### TC-CLR-19 — Font slots minimum 1 even with CHA modifier 0 or negative
- **AC:** `[NEW]` Font slot count: capped at 1 + CHA modifier; minimum 1
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testFontSlotsMinimumOne()`
- **Setup:** Cleric with CHA 8 (mod –1); inspect font_slots
- **Expected:** font_slots = 1 (not 0 or negative)
- **Roles:** authenticated player

### TC-CLR-20 — Font slots do not exceed 1 + CHA modifier
- **AC:** `[TEST-ONLY]` Font slots do not exceed 1 + CHA modifier
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testFontSlotsDoNotExceedCap()`
- **Setup:** Cleric with CHA 20 (mod +5); inspect font_slots; attempt to add additional font slots via external bonus
- **Expected:** font_slots = 6; attempts to exceed 6 via bonus rejected
- **Roles:** authenticated player

### TC-CLR-21 — Harmful Font without evil alignment: permitted if deity explicitly allows
- **AC:** `[NEW]` Harmful Font without evil alignment (deity allows): correctly permitted if deity explicitly allows it
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testHarmfulFontPermittedForNonEvilIfDeityAllows()`
- **Setup:** Non-evil-aligned Cleric with a deity that explicitly allows Harmful Font; inspect font grant
- **Expected:** Harmful Font granted without alignment violation; divine_font = Harmful; no anathema triggered
- **Roles:** authenticated player

### TC-CLR-22 — Bonus divine font slots are at the highest spell level cleric has access to
- **AC:** `[NEW]` Bonus divine font slots are at the highest spell level the cleric has access to
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDivineFontTest::testFontSlotsAtHighestSpellLevel()`
- **Setup:** Cleric at level 5 (highest spell level = 3); inspect font_slot_spell_level
- **Expected:** font_slot_spell_level = 3; at level 10 (highest = 5), font_slot_spell_level = 5
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting` for highest accessible spell level tracking. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-23 — Level 19: Miraculous Spell — one 10th-level spell slot granted
- **AC:** `[NEW]` Level 19: Miraculous Spell — one 10th-level spell slot for a prepared divine spell
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericLevelFeaturesTest::testMiraculousSpellAtLevel19()`
- **Setup:** Cleric at level 19; inspect spell_slots for presence of a 10th-level slot
- **Expected:** One level-10 spell slot present; can prepare a divine spell in it
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-24 — Miraculous Spell cannot be used with slot-manipulation features
- **AC:** `[NEW]` Miraculous Spell cannot be used with slot-manipulation features
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericLevelFeaturesTest::testMiraculousSpellCannotBeManipulated()`
- **Setup:** Cleric at level 19 with Miraculous Spell; attempt to use a slot-manipulation feature (e.g., Quickened Casting or similar) on the 10th-level slot
- **Expected:** Slot manipulation rejected for the Miraculous Spell slot; error references restriction
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-25 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated features do not appear before required level
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericLevelFeaturesTest::testLevelGatedFeaturesNotGrantedEarly()`
- **Setup:** Cleric at level 2; inspect for Miraculous Spell
- **Expected:** Miraculous Spell not present at level 2
- **Roles:** authenticated player

### TC-CLR-26 — Class feat schedule: level 1 and every even level
- **AC:** `[NEW]` Cleric gains class feat at level 1 and every even-numbered level (2, 4, 6 … 20)
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericFeatProgressionTest::testClassFeatSchedule()`
- **Setup:** Level Cleric 1 → 4; inspect class_feats_available at each level
- **Expected:** Class feat slots at levels 1, 2, 4; not at level 3
- **Roles:** authenticated player

### TC-CLR-27 — General feat schedule: levels 3, 7, 11, 15, 19
- **AC:** `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericFeatProgressionTest::testGeneralAndSkillFeatSchedule()`
- **Setup:** Level Cleric 1 → 3; inspect general_feats_available at level 3; inspect skill_feats at level 2
- **Expected:** General feat slot at level 3; skill feat at level 2; no general feat at level 2
- **Roles:** authenticated player

### TC-CLR-28 — Ability boosts at levels 5, 10, 15, 20
- **AC:** `[NEW]` Ability boosts at levels 5, 10, 15, 20
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericFeatProgressionTest::testAbilityBoostSchedule()`
- **Setup:** Level Cleric to 5; inspect ability_boosts_available
- **Expected:** Ability boost slot present at level 5; not at level 4
- **Roles:** authenticated player

### TC-CLR-29 — Invalid deity/alignment combination rejected
- **AC:** `[TEST-ONLY]` Invalid deity/alignment combination rejected
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericDeityTest::testInvalidDeityAlignmentRejected()`
- **Setup:** Attempt to create Cleric with a deity that requires Good alignment while character alignment = Evil
- **Expected:** Validation error; character creation blocked; error references deity/alignment incompatibility
- **Roles:** authenticated player

### TC-CLR-30 — Preparing more spells than available slots blocked
- **AC:** `[TEST-ONLY]` Preparing more spells than available slots blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericSpellcastingTest::testCannotPrepareMoreSpellsThanSlots()`
- **Setup:** Cleric with 2 first-level slots; attempt to prepare 3 first-level spells
- **Expected:** Third preparation blocked; error references slot limit
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-CLR-31 — Player cannot modify another player's Cleric character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `ClericClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Cleric owned by user A; attempt deity change or doctrine selection as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-CLR-32 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Exact Warpriest armor/weapon proficiency tiers | AC states "gains armor and weapon proficiencies" but does not enumerate exact tiers (light only? medium? martial weapons? deity weapon only?). PM must specify exact proficiency grants for TC-CLR-08 to be fully implemented |
| Exact doctrine spell proficiency progression table | TC-CLR-10 verifies the difference between doctrines exists; the exact proficiency tier at each level requires the full advancement table from `dc-cr-spellcasting` |
| Deity catalog and alignment approval matrix | TC-CLR-04 and TC-CLR-29 verify alignment enforcement; the full deity catalog with allowed alignments must be a defined dataset before these TCs can be parameterized |
| Anathema behavior specifics beyond domain/deity | TC-CLR-05 covers domain spells + deity abilities; if additional cleric features are disabled on anathema violation, those cases need enumeration |
| Slot-manipulation feature catalog | TC-CLR-24 tests that Miraculous Spell cannot be slot-manipulated; the specific list of slot-manipulation features needs to be defined before all edge-case parameterizations are possible |

---

## Deferred dependency summary

The following TCs depend on `dc-cr-spellcasting` (deferred) and must be flagged `pending-dev-confirmation` at Stage 0:

| TC | Dependency |
|---|---|
| TC-CLR-10 | Doctrine-specific spell proficiency progression tables |
| TC-CLR-11 | Prepared spellcasting model (slot/preparation mechanics) |
| TC-CLR-12 | Divine focus + material component replacement (also `dc-cr-equipment-system`) |
| TC-CLR-13 | Spell slots and cantrip scaling per advancement table |
| TC-CLR-14 | WIS spell attack/DC modifier plumbing |
| TC-CLR-22 | Font slot spell-level tracking (highest accessible) |
| TC-CLR-23 | 10th-level slot existence (Miraculous Spell) |
| TC-CLR-24 | Slot-manipulation restriction on Miraculous Spell |
| TC-CLR-30 | Slot limit enforcement during preparation |

TCs with no deferred dependencies (activatable at Stage 0 immediately): TC-CLR-01 through TC-CLR-09, TC-CLR-15 through TC-CLR-21, TC-CLR-25 through TC-CLR-29, TC-CLR-31, TC-CLR-32.

---

## Regression risk areas

1. **Doctrine armor/weapon grants**: Warpriest adds armor/weapon proficiencies at creation — must not carry over to Cloistered Cleric characters or bleed into other classes sharing proficiency infrastructure.
2. **Divine Font slot isolation**: font slots are bonus slots distinct from normal spell slots; risk of double-counting if slot arrays are merged.
3. **Anathema partial state**: partial disable (domain spells off, base spellcasting on) requires fine-grained feature-flag logic — risk of either over-disabling or under-disabling features on violation.
4. **Healing Font vs Harmful Font mutual exclusion**: without Versatile Font, font type is strictly one-or-the-other; risk of off-by-one in slot-type validation if font array is shared.
5. **Miraculous Spell level-10 slot**: level-10 spells are outside the standard 1–9 slot range; risk that slot validation code rejects level-10 as out-of-bounds if the range check is not extended.
6. **Spellcasting shared infrastructure**: Champion also uses CHA for divine spellcasting; Cleric uses WIS — verify no shared spellcasting stat resolver returns the wrong ability score for each class.
7. **QA audit regression**: no new routes per security AC; if any are added, they must be probed in qa-permissions.json before release.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-class-cleric

## Gap analysis reference
- DB sections: core/ch03/Cleric (REQs 1052–1115)
- Track B: no existing ClericService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Cleric exists as a selectable playable class with Wisdom as key ability boost at level 1.
- [ ] `[NEW]` Cleric HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Trained deity's favored weapon; untrained in all armor by default (doctrine may override); Trained divine spell attacks/DCs (Wisdom).

### Deity & Anathema
- [ ] `[NEW]` At level 1, player selects a deity with approved alignment; anathema violation removes divine connection until atone ritual completed.

### Doctrine (Subclass)
- [ ] `[NEW]` At level 1, player selects a Doctrine: Cloistered Cleric or Warpriest.
- [ ] `[NEW]` Cloistered Cleric: focuses on spell power; faster spell DC/attack progression, domain emphasis.
- [ ] `[NEW]` Warpriest: gains armor and weapon proficiencies; slower spell DC/attack progression.

### Divine Spellcasting (Prepared)
- [ ] `[NEW]` Cleric uses prepared divine spellcasting (not spontaneous); must prepare spells each day.
- [ ] `[NEW]` Religious symbol acts as divine focus replacing material components.
- [ ] `[NEW]` Spell slots and cantrips scale per advancement table.
- [ ] `[NEW]` Wisdom modifier used for spell attacks and DCs.

### Divine Font
- [ ] `[NEW]` Cleric gains Divine Font based on deity: Healing Font (prepare heal spells in bonus slots = 1 + CHA) or Harmful Font (harm spells = 1 + CHA).
- [ ] `[NEW]` Font choice is locked unless deity allows both and Versatile Font feat is taken.
- [ ] `[NEW]` Bonus divine font slots are at the highest spell level the cleric has access to.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 19: Miraculous Spell — one 10th-level spell slot for a prepared divine spell (cannot be used with slot-manipulation features).

### Feat Progression
- [ ] `[NEW]` Cleric gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Anathema violation: divine connection suspended; spellcasting still works but domain spells and deity abilities disabled until atone.
- [ ] `[NEW]` Healing Font with Versatile Font: both heal and harm can fill font slots.
- [ ] `[NEW]` Harmful Font without evil alignment (deity allows): correctly permitted if deity explicitly allows it.
- [ ] `[NEW]` Font slot count: capped at 1 + CHA modifier; minimum 1.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Invalid deity/alignment combination rejected.
- [ ] `[TEST-ONLY]` Font slots do not exceed 1 + CHA modifier.
- [ ] `[TEST-ONLY]` Preparing more spells than available slots blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
