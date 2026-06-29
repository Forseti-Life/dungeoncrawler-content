# Suite Activation: dc-cr-class-druid

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:23:44+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-class-druid"`**  
   This links the test to the living requirements doc at `features/dc-cr-class-druid/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-class-druid-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-class-druid",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-class-druid"`**  
   Example:
   ```json
   {
     "id": "dc-cr-class-druid-<route-slug>",
     "feature_id": "dc-cr-class-druid",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-class-druid",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-class-druid

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Druid Class — identity/stats, universal anathema (metal armor/Druidic), druidic order subclass (Animal/Leaf/Storm/Wild), primal prepared spellcasting, focus pool, level-gated features, feat progression, edge cases
**AC source:** `features/dc-cr-class-druid/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Druid class logic; first druid implementation.
- Dependency note: `dc-cr-spellcasting` is **deferred** — all TCs relying on prepared-slot mechanics, spell advancement table, slot validation, and slot-manipulation restrictions must be flagged `pending-dev-confirmation` at Stage 0 until `dc-cr-spellcasting` ships.
- Dependency note: `dc-cr-animal-companion` is **planned** — Wild Shape form TCs and Animal Order animal companion TCs must be flagged `pending-dev-confirmation` until that feature ships.
- `dc-cr-character-class` ✓ and `dc-cr-character-creation` ✓ — no blocker on those.
- Cross-class pattern: Cleric also uses WIS + prepared divine spellcasting + a 10th-level slot at level 19 with slot-manipulation restrictions; same regression risk applies here for primal. Verify stat resolver returns WIS for Druid, not CHA (Champion risk).

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class identity, HP/proficiency, anathema (metal armor/Druidic), order selection, focus pool, spellcasting, level features, edge cases |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, QA audit regression |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. No Playwright suite needed at this scope.

---

## Test cases

### TC-DRU-01 — Druid exists as selectable class; WIS as key ability
- **AC:** `[NEW]` Druid selectable in character creation; Wisdom as key ability boost at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidClassTest::testDruidExistsWithWisdomKeyAbility()`
- **Setup:** Query `character_class` nodes; load druid; create druid character
- **Expected:** Druid class node exists; key_ability = Wisdom; no other key ability accepted
- **Roles:** authenticated player

### TC-DRU-02 — Druid HP calculation: 8 + CON modifier per level
- **AC:** `[NEW]` Druid HP = 8 + CON modifier per level
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidClassTest::testHpCalculation()`
- **Setup:** Create Druid with CON 14 (mod +2); inspect HP at level 1 and level 2
- **Expected:** Level 1 HP = 10; Level 2 HP = 20
- **Roles:** authenticated player

### TC-DRU-03 — Druid initial proficiencies: Perception/saves/armor defaults
- **AC:** `[NEW]` Trained Perception; Expert Will, Trained Fortitude and Reflex; Trained light and medium armor (metal armor and shields forbidden); Trained primal spell attacks/DCs (WIS)
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidClassTest::testInitialProficiencies()`
- **Setup:** Create level-1 Druid; inspect all proficiency fields
- **Expected:** Perception=Trained; Will=Expert; Fortitude=Trained; Reflex=Trained; light_armor=Trained; medium_armor=Trained; heavy_armor=Untrained; metal_armor=forbidden_flag; shield=forbidden_flag; primal_spell_attack=Trained; primal_spell_dc=Trained (WIS-based)
- **Roles:** authenticated player

### TC-DRU-04 — Druidic language automatically known at level 1
- **AC:** `[NEW]` Druid automatically knows Druidic language at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidClassTest::testDruidicLanguageGranted()`
- **Setup:** Create level-1 Druid; inspect languages
- **Expected:** Druidic present in known_languages without consuming a language slot
- **Roles:** authenticated player

### TC-DRU-05 — Wild Empathy: Druid can use Diplomacy on animals
- **AC:** `[NEW]` Wild Empathy: Druid can use Diplomacy on animals
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidClassTest::testWildEmpathyGranted()`
- **Setup:** Create level-1 Druid; inspect class_features and special_abilities
- **Expected:** wild_empathy feature present; allows_diplomacy_on_animals = true
- **Roles:** authenticated player
- **Note to PM:** Wild Empathy is a passive class feature flag; full Diplomacy-on-animals interaction requires a social/encounter system that may not yet be in scope. TC verifies the flag is granted; behavioral outcome testing should wait for that system.

### TC-DRU-06 — Metal armor blocked for Druid characters
- **AC:** `[NEW]` Metal armor and shields cannot be equipped by Druid characters; system prevents equipping or provides a blocking warning
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidAnathemaTest::testMetalArmorBlocked()`
- **Setup:** Druid character; attempt to equip a metal armor item (e.g., chainmail)
- **Expected:** Equip blocked or blocking warning issued; metal_armor_forbidden flag enforced; character not in metal armor state
- **Roles:** authenticated player

### TC-DRU-07 — Shields blocked for Druid characters
- **AC:** `[NEW]` Shields cannot be equipped by Druid characters
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidAnathemaTest::testShieldBlocked()`
- **Setup:** Druid character; attempt to equip a shield
- **Expected:** Equip blocked or blocking warning issued; shield_forbidden flag enforced
- **Roles:** authenticated player

### TC-DRU-08 — Teaching Druidic to non-druid is flagged as anathema; violation suspends primal spellcasting and order benefits
- **AC:** `[NEW]` Teaching Druidic to non-druids flagged as anathema; all anathema violations remove primal spellcasting and order benefits until atone ritual completed
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidAnathemaTest::testAnathemaViolationEffect()`
- **Setup:** Druid with active primal spellcasting and order benefits; trigger anathema violation event; inspect primal_spellcasting and order_benefits state
- **Expected:** After violation: primal_spellcasting = suspended; order_benefits = suspended; focus pool inaccessible
- **Roles:** authenticated player

### TC-DRU-09 — Atone ritual restores primal spellcasting and order benefits
- **AC:** `[NEW]` Anathema violations removed until atone ritual completed (implies atone restores)
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidAnathemaTest::testAtoneRitualRestores()`
- **Setup:** Druid in anathema-violated state; trigger atone ritual; re-inspect primal_spellcasting and order_benefits
- **Expected:** primal_spellcasting = active; order_benefits = active; focus pool accessible
- **Roles:** authenticated player

### TC-DRU-10 — Order selection: all four orders accepted at level 1
- **AC:** `[NEW]` At level 1, player selects one Order: Animal, Leaf, Storm, or Wild
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidOrderTest::testAllFourOrdersAccepted()`
- **Setup:** Create four Druids, each selecting a different order; inspect druidic_order field for each
- **Expected:** druidic_order = Animal | Leaf | Storm | Wild respectively; all four accepted without error
- **Roles:** authenticated player

### TC-DRU-11 — Order selection is permanent; cannot be changed after level 1
- **AC:** `[TEST-ONLY]` Order selection is permanent (class design pattern; implied)
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidOrderTest::testOrderSelectionIsPermanent()`
- **Setup:** Druid with Animal order selected; attempt to change to Storm order
- **Expected:** Change blocked; error references immutable order selection
- **Roles:** authenticated player

### TC-DRU-12 — Each order grants one order focus spell
- **AC:** `[NEW]` Each order grants one order focus spell
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidOrderTest::testEachOrderGrantsOneFocusSpell()`
- **Setup:** Create Druid for each order; inspect order_focus_spell
- **Expected:** Animal order → wild shape (or order-specific spell); Leaf order → its focus spell; Storm order → its focus spell; Wild order → its focus spell; one spell per order
- **Roles:** authenticated player
- **Note to PM:** Exact focus spell name per order must be enumerated in the AC before full parameterization is possible. TC verifies that exactly one focus spell is granted per order; correctness of which spell requires that mapping.

### TC-DRU-13 — Leaf and Storm orders start with 2 Focus Points; others start with 1
- **AC:** `[NEW]` Leaf and Storm orders start with 2 Focus Points (others start with 1)
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidOrderTest::testFocusPointStartingValues()`
- **Setup:** Create Druids for all four orders; inspect focus_pool_max at level 1
- **Expected:** Leaf → focus_pool_max = 2; Storm → focus_pool_max = 2; Animal → focus_pool_max = 1; Wild → focus_pool_max = 1
- **Roles:** authenticated player

### TC-DRU-14 — Focus pool at 0: order focus spells blocked
- **AC:** `[TEST-ONLY]` Focus pool at 0: order focus spells blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidOrderTest::testFocusSpellsBlockedAtZeroPool()`
- **Setup:** Druid with focus pool drained to 0; attempt to cast order focus spell
- **Expected:** Cast blocked; error references empty focus pool
- **Roles:** authenticated player

### TC-DRU-15 — Primal spellcasting is prepared (not spontaneous); must prepare each day
- **AC:** `[NEW]` Druid uses prepared primal spellcasting; must prepare spells each day
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidSpellcastingTest::testPreparedPrimalSpellcasting()`
- **Setup:** Create Druid; inspect spellcasting_type and spell_tradition; attempt to cast an unprepared spell
- **Expected:** spellcasting_type = prepared; spell_tradition = primal; unprepared spell cast blocked; error references daily preparation requirement
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-DRU-16 — WIS modifier used for primal spell attacks and DCs
- **AC:** `[NEW]` Spell attacks and DCs scale with Wisdom modifier
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidSpellcastingTest::testWisdomUsedForSpellAttackAndDc()`
- **Setup:** Druid with WIS 18 (mod +4); inspect spell_attack_modifier and spell_dc
- **Expected:** spell_attack_modifier references WIS mod; spell_dc = 10 + proficiency_bonus + WIS mod
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-DRU-17 — Spell slots scale per advancement table
- **AC:** `[NEW]` Spell slots scale per advancement table
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidSpellcastingTest::testSpellSlotsScalePerTable()`
- **Setup:** Level Druid from 1 to 3 to 5; inspect spell_slots at each level
- **Expected:** Spell slots increase per PF2E Druid advancement table at each level
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-DRU-18 — Preparing more spells than available slots blocked
- **AC:** `[TEST-ONLY]` Preparing more spells than available slots blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidSpellcastingTest::testCannotPrepareMoreThanSlots()`
- **Setup:** Druid with 2 first-level slots; attempt to prepare 3 first-level spells
- **Expected:** Third preparation blocked; error references slot limit
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-DRU-19 — Level 19: Primal Hierophant — one 10th-level prepared spell slot granted
- **AC:** `[NEW]` Level 19: Primal Hierophant — one 10th-level prepared primal spell slot
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidLevelFeaturesTest::testPrimalHierophantAtLevel19()`
- **Setup:** Druid at level 19; inspect spell_slots for presence of a level-10 slot
- **Expected:** One level-10 primal spell slot present; can prepare a primal spell in it
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-DRU-20 — Primal Hierophant slot cannot be used with slot-manipulation features
- **AC:** `[NEW]` Primal Hierophant cannot be used with slot-manipulation features
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidLevelFeaturesTest::testPrimalHierophantCannotBeManipulated()`
- **Setup:** Druid at level 19 with Primal Hierophant; attempt to apply a slot-manipulation feature to the 10th-level slot
- **Expected:** Slot manipulation rejected; error references restriction on the 10th-level slot
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Flag `pending-dev-confirmation` at Stage 0.

### TC-DRU-21 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated features do not appear before required level
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidLevelFeaturesTest::testLevelGatedFeaturesNotGrantedEarly()`
- **Setup:** Druid at level 2; inspect for Primal Hierophant
- **Expected:** Primal Hierophant not present at level 2
- **Roles:** authenticated player

### TC-DRU-22 — Class feat schedule: level 1 and every even level
- **AC:** `[NEW]` Druid gains class feat at level 1 and every even-numbered level (2, 4, 6 … 20)
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidFeatProgressionTest::testClassFeatSchedule()`
- **Setup:** Level Druid 1 → 4; inspect class_feats_available at each level
- **Expected:** Class feat slots at levels 1, 2, 4; not at level 3
- **Roles:** authenticated player

### TC-DRU-23 — General/skill/ancestry feat schedule correct
- **AC:** `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidFeatProgressionTest::testGeneralAndSkillFeatSchedule()`
- **Setup:** Level Druid 1 → 3; inspect general_feats at level 3; skill_feats at level 2
- **Expected:** General feat slot at level 3; skill feat slot at level 2; no general feat at level 2
- **Roles:** authenticated player

### TC-DRU-24 — Ability boosts at levels 5, 10, 15, 20
- **AC:** `[NEW]` Ability boosts at levels 5, 10, 15, 20
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidFeatProgressionTest::testAbilityBoostSchedule()`
- **Setup:** Level Druid to 5; inspect ability_boosts_available
- **Expected:** Ability boost slot present at level 5; not at level 4
- **Roles:** authenticated player

### TC-DRU-25 — Order Explorer: second order grants its level-1 feats; violating secondary order removes only those feats
- **AC:** `[NEW]` Order Explorer: joining a second order grants access to its 1st-level feats; violating that order's anathema removes only those feats, not the main primal connection
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidEdgeCaseTest::testOrderExplorerPartialAnathema()`
- **Setup:** Druid (Animal primary) with Order Explorer feat adding Leaf secondary order; trigger secondary order anathema violation; inspect primary primal_spellcasting and primary order benefits vs secondary order feats
- **Expected:** Secondary order feats = suspended; primary primal_spellcasting = still active; primary order benefits = still active
- **Roles:** authenticated player

### TC-DRU-26 — Wild Shape: attempting an unlocked form that hasn't been taken is blocked
- **AC:** `[NEW]` Wild Shape forms: each unlock feat adds specific forms; attempting unlocked forms that aren't taken yet is blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidEdgeCaseTest::testWildShapeUnlockedFormBlocked()`
- **Setup:** Druid with base Wild Shape but without a specific form unlock feat (e.g., Ferocious Shape); attempt to wild shape into a ferocious form
- **Expected:** Transformation blocked; error references missing form unlock feat
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-animal-companion` / Wild Shape implementation. Flag `pending-dev-confirmation` at Stage 0 until that feature ships.

### TC-DRU-27 — Form Control: duration extended; spell level –2 (min 1) applied when metamagic used
- **AC:** `[NEW]` Form Control: duration extends correctly; spell level reduction (–2, min 1) applied when metamagic is used
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidEdgeCaseTest::testFormControlDurationAndSpellLevelReduction()`
- **Setup:** Druid with Form Control feat; activate Wild Shape with metamagic; inspect resulting spell level and duration
- **Expected:** Duration = extended (per Form Control rules); effective_spell_level = original_level – 2, minimum 1
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-animal-companion` / Wild Shape and `dc-cr-spellcasting` (metamagic). Flag `pending-dev-confirmation` at Stage 0.

### TC-DRU-28 — Metal armor blocked (failure mode explicit check)
- **AC:** `[TEST-ONLY]` Metal armor equipment blocked for druid characters
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidAnathemaTest::testMetalArmorBlockedIsFailureMode()`
- **Setup:** Druid; attempt to equip chainmail (metal medium armor)
- **Expected:** Block or warning; character never enters metal-armored state
- **Roles:** authenticated player
- **Note:** Duplicate intent with TC-DRU-06 but explicitly sourced from Failure Modes AC section; keep both to ensure the negative case is tagged as a failure-mode test.

### TC-DRU-29 — Player cannot modify another player's Druid character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `DruidClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Druid owned by user A; attempt order change or spell preparation as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-DRU-30 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Exact order focus spell per order | TC-DRU-12 verifies one focus spell is granted per order; the exact spell name mapping (Animal → ?, Leaf → ?, Storm → ?, Wild → ?) must be enumerated in AC before parametric correctness can be tested |
| Wild Empathy Diplomacy behavior in encounters | TC-DRU-05 verifies the class feature flag exists; the actual Diplomacy check outcome against animal NPCs requires a social/encounter system not yet in scope |
| Specific Wild Shape forms unlocked per feat | TC-DRU-26 verifies that an unlocked form is blocked; the full form catalog (which feat unlocks which forms) must be a defined dataset before all cases are parametrized |
| Druidic language teaching detection | AC flags teaching Druidic to non-druids as anathema; detecting this action requires an in-game social/communication mechanic that may not exist yet — TC-DRU-08 tests the violation effect, not the detection trigger |

---

## Deferred dependency summary

| TC | Dependency | Deferred feature |
|---|---|---|
| TC-DRU-15 | Prepared spellcasting model | `dc-cr-spellcasting` |
| TC-DRU-16 | WIS spell attack/DC plumbing | `dc-cr-spellcasting` |
| TC-DRU-17 | Spell slot scaling table | `dc-cr-spellcasting` |
| TC-DRU-18 | Slot limit enforcement | `dc-cr-spellcasting` |
| TC-DRU-19 | 10th-level slot (Primal Hierophant) | `dc-cr-spellcasting` |
| TC-DRU-20 | Slot-manipulation restriction | `dc-cr-spellcasting` |
| TC-DRU-26 | Wild Shape form unlock/block | `dc-cr-animal-companion` |
| TC-DRU-27 | Form Control duration + metamagic spell level | `dc-cr-animal-companion`, `dc-cr-spellcasting` |

TCs with no deferred dependencies (immediately activatable at Stage 0): TC-DRU-01 through TC-DRU-14, TC-DRU-21 through TC-DRU-25, TC-DRU-28, TC-DRU-29, TC-DRU-30.

---

## Regression risk areas

1. **Metal armor/shield hard block**: Druid is the only class with an equipment-level forbidden-item constraint at class creation; risk that the equipment equip hook does not check class when applying the block, or checks it only at character sheet validation, not at equip time.
2. **Primal vs divine spellcasting stat**: Cleric uses WIS for divine, Druid uses WIS for primal — both share the same ability score but different traditions; verify tradition field is stored and does not bleed between classes.
3. **Focus pool starting value by order**: Leaf/Storm start with 2, others start with 1 — this is an order-conditional initialization; risk of off-by-one if the order list defaults to 1 without an explicit override for Leaf/Storm.
4. **Order Explorer partial anathema**: two orders simultaneously active; anathema violation on the secondary order must not cascade to the primary order — requires fine-grained order state isolation.
5. **Druidic language slot cost**: must not consume a language slot (granted free); risk of shared language-grant logic that charges a slot unconditionally.
6. **Primal Hierophant level-10 slot**: same risk as Cleric's Miraculous Spell — slot range validator must allow level 10.
7. **Wild Shape + Form Control interaction**: spell-level reduction (–2, min 1) applied on metamagic only, not base cast — risk of always applying the reduction regardless of whether metamagic was used.
8. **QA audit regression**: no new routes per security AC; verify no new endpoints introduced during implementation.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-class-druid

## Gap analysis reference
- DB sections: core/ch03/Druid (REQs 1116–1171)
- Track B: no existing DruidService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships), dc-cr-animal-companion (planned)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Druid exists as a selectable playable class with Wisdom as key ability boost at level 1.
- [ ] `[NEW]` Druid HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Trained light and medium armor (metal armor and shields forbidden); Trained primal spell attacks/DCs (Wisdom).
- [ ] `[NEW]` Druid automatically knows Druidic language at level 1.
- [ ] `[NEW]` Wild Empathy: Druid can use Diplomacy on animals.

### Universal Anathema
- [ ] `[NEW]` Metal armor and shields cannot be equipped by Druid characters; system prevents equipping or provides a blocking warning.
- [ ] `[NEW]` Teaching Druidic to non-druids flagged as anathema; all anathema violations remove primal spellcasting and order benefits until atone ritual completed.

### Druidic Order (Subclass)
- [ ] `[NEW]` At level 1, player selects one Order: Animal, Leaf, Storm, or Wild.
- [ ] `[NEW]` Each order grants one order focus spell; Leaf and Storm orders start with 2 Focus Points (others start with 1).

### Primal Spellcasting (Prepared)
- [ ] `[NEW]` Druid uses prepared primal spellcasting; must prepare spells each day.
- [ ] `[NEW]` Spell attacks and DCs scale with Wisdom modifier.
- [ ] `[NEW]` Spell slots scale per advancement table.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 19: Primal Hierophant — one 10th-level prepared primal spell slot (cannot be used with slot-manipulation features).

### Feat Progression
- [ ] `[NEW]` Druid gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Order Explorer: joining a second order grants access to its 1st-level feats; violating that order's anathema removes only those feats, not the main primal connection.
- [ ] `[NEW]` Wild Shape forms: each unlock feat adds specific forms; attempting unlocked forms that aren't taken yet is blocked.
- [ ] `[NEW]` Form Control: duration extends correctly; spell level reduction (–2, min 1) applied when metamagic is used.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Metal armor equipment blocked for druid characters.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Preparing more spells than available slots blocked.
- [ ] `[TEST-ONLY]` Focus pool at 0: order focus spells blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
