# Suite Activation: dc-cr-class-champion

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-09T01:31:04+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-class-champion"`**  
   This links the test to the living requirements doc at `features/dc-cr-class-champion/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-class-champion-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-class-champion",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-class-champion"`**  
   Example:
   ```json
   {
     "id": "dc-cr-class-champion-<route-slug>",
     "feature_id": "dc-cr-class-champion",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-class-champion",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-class-champion

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Champion Class — identity/stats, deity/cause/code, Champion's Reaction (all 3 causes), devotion spells/focus pool, level-gated features, feat progression, edge cases
**AC source:** `features/dc-cr-class-champion/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Champion class logic; first champion implementation.
- KB ref: `dc-cr-class-barbarian` test plan — same suite/runner pattern; instinct-specific behavior parallels cause-specific Champion's Reaction.
- Dependency note: `dc-cr-character-class` and `dc-cr-character-creation` are both ✓. No deferred dependencies — all TCs can be activated at Stage 0. However, focus pool behavior may depend on `dc-cr-focus-spells` if that system is shared; flag at Stage 0 if needed.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class selection, HP/proficiency, deity/cause, Champion's Reaction, devotion spells, focus pool, level-gated features, edge cases |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, QA audit regression |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. All Champion logic is service/entity layer. No Playwright suite needed at this scope.

---

## Test cases

### TC-CHP-01 — Champion exists as selectable class; STR or DEX as key ability (player chooses)
- **AC:** `[NEW]` Champion selectable in character creation; STR or DEX as key ability boost at level 1 (player chooses)
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionClassTest::testChampionExistsWithStrOrDexKeyAbility()`
- **Setup:** Query `character_class` nodes; load champion; create champion with STR choice; create champion with DEX choice
- **Expected:** Champion class node exists; both STR and DEX accepted as key_ability; no other key ability accepted
- **Roles:** authenticated player

### TC-CHP-02 — Champion HP calculation: 10 + CON modifier per level
- **AC:** `[NEW]` Champion HP = 10 + CON modifier per level
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionClassTest::testHpCalculation()`
- **Setup:** Create Champion with CON 14 (mod +2); inspect HP at level 1 and level 2
- **Expected:** Level 1 HP = 12; Level 2 HP = 24
- **Roles:** authenticated player

### TC-CHP-03 — Champion initial proficiencies applied correctly
- **AC:** `[NEW]` Trained Perception; Expert Fortitude/Will, Trained Reflex; Trained Religion + deity skill + 2+INT; Trained all armor; Trained divine spell attacks/DCs (CHA)
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionClassTest::testInitialProficiencies()`
- **Setup:** Create Champion with INT 10 (mod 0); inspect all proficiency fields
- **Expected:** Perception=Trained; Fortitude=Expert; Will=Expert; Reflex=Trained; religion=Trained; deity_skill=Trained; skill_count=2; all_armor=Trained; divine_spell_attack=Trained; divine_spell_dc=Trained (CHA-based)
- **Roles:** authenticated player

### TC-CHP-04 — Cause selection: Paladin requires Lawful Good alignment
- **AC:** `[NEW]` At level 1, player selects deity and Cause matching alignment; Paladin = Lawful Good
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionCauseTest::testPaladinRequiresLawfulGood()`
- **Setup:** Create Champion; select Paladin cause with LG alignment (success); attempt Paladin with NG alignment (failure)
- **Expected:** LG+Paladin: accepted; NG+Paladin: validation error referencing alignment requirement
- **Roles:** authenticated player

### TC-CHP-05 — Cause selection: Redeemer requires Neutral Good; Liberator requires Chaotic Good
- **AC:** `[NEW]` Redeemer = Neutral Good; Liberator = Chaotic Good
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionCauseTest::testRedemerAndLiberatorAlignmentRequirements()`
- **Setup:** Create Champion; select Redeemer with NG (success), with LG (failure); select Liberator with CG (success), with LG (failure)
- **Expected:** Alignment matches enforce for both causes; mismatches produce validation errors
- **Roles:** authenticated player

### TC-CHP-06 — Invalid deity/cause combination rejected
- **AC:** `[TEST-ONLY]` Invalid deity/cause combination rejected
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionCauseTest::testInvalidDeityCauseCombinationRejected()`
- **Setup:** Submit cause = Paladin with a deity incompatible with LG (e.g., chaotic deity)
- **Expected:** Validation error returned; character creation blocked
- **Roles:** authenticated player

### TC-CHP-07 — Deific Weapon: uncommon access granted; d4/simple weapon damage die upgraded one step
- **AC:** `[NEW]` Deity-granted weapon (Deific Weapon): uncommon access granted; d4/simple weapon damage die upgraded by one step
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionCauseTest::testDeificWeaponGranted()`
- **Setup:** Create Champion with deity; inspect available weapons for uncommon deific weapon; inspect damage die of deity weapon that is normally d4 (simple) and one normally d6
- **Expected:** Deific weapon accessible (uncommon flag bypassed); d4 weapon die upgraded to d6; d6 weapon upgraded to d8
- **Roles:** authenticated player

### TC-CHP-08 — Tenets are hierarchical; higher tenets override lower in conflicts
- **AC:** `[NEW]` Tenets are hierarchical; higher tenets override lower in conflicts
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionCauseTest::testTenetHierarchyEnforced()`
- **Setup:** Champion with conflicting tenet instructions; trigger conflict resolution logic
- **Expected:** Higher-priority tenet's outcome applied; lower-priority tenet's outcome suppressed
- **Roles:** authenticated player
- **Note to PM:** Tenet hierarchy requires the system to define tenet priority order. If not yet specified beyond "hierarchical", automation can only verify that a conflict-resolution mechanism exists, not that all specific cases are correct. PM should enumerate specific conflict cases before this TC is finalized.

### TC-CHP-09 — Code violation: focus pool and divine ally suspended; atone restores both
- **AC:** `[NEW]` Code violation removes focus pool and divine ally until atone ritual; atone restores both
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionCauseTest::testCodeViolationAndAtoneRitual()`
- **Setup:** Champion with active focus pool and divine ally; trigger code violation event; inspect availability of focus pool and divine ally; trigger atone ritual; re-inspect
- **Expected:** After violation: focus pool = inaccessible; divine ally benefits = suspended; After atone: both restored
- **Roles:** authenticated player

### TC-CHP-10 — Paladin's Reaction: Retributive Strike — ally resistance = 2 + level; melee Strike if foe in reach
- **AC:** `[NEW]` Paladin — Retributive Strike: ally resistance = 2 + level; if foe in reach, make melee Strike
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionReactionTest::testRetributiveStrike()`
- **Setup:** Paladin Champion at level 3; ally takes damage; trigger reaction; inspect ally resistance; inspect melee Strike on foe in reach
- **Expected:** Ally resistance to all damage = 5 (2+3); melee Strike executed against foe in reach
- **Roles:** authenticated player

### TC-CHP-11 — Redeemer's Reaction: Glimpse of Redemption — foe choice A/B; enfeebled 2 applied
- **AC:** `[NEW]` Redeemer — Glimpse of Redemption: foe chooses (A) ally unharmed or (B) ally resistance = 2 + level; foe becomes enfeebled 2 until end of its next turn
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionReactionTest::testGlimpseOfRedemption()`
- **Setup:** Redeemer Champion at level 5; ally takes damage; trigger reaction; simulate foe choosing option A (ally unharmed); simulate foe choosing option B (ally resistance); inspect foe's enfeebled condition in both cases
- **Expected:** Option A: ally damage = 0; Option B: ally resistance = 7 (2+5); in both cases, foe enfeebled 2 until end of its next turn
- **Roles:** authenticated player

### TC-CHP-12 — Liberator's Reaction: Liberating Step — ally resistance; break-free attempt; Step as free action
- **AC:** `[NEW]` Liberator — Liberating Step: ally resistance = 2 + level; ally can attempt break-free (new save or Escape); ally can Step as free action
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionReactionTest::testLiberatingStep()`
- **Setup:** Liberator Champion at level 4; ally takes damage while grabbed/restrained; trigger reaction; inspect resistance, break-free option availability, Step availability
- **Expected:** Ally resistance = 6 (2+4); break-free save or Escape available as free option; ally can Step as free action
- **Roles:** authenticated player

### TC-CHP-13 — Focus pool starts at 1; max 3 with feats; Refocus = 10 minutes
- **AC:** `[NEW]` Champion starts with focus pool of 1 Focus Point (max 3 with feats); Refocus = 10 minutes prayer/service
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionFocusPoolTest::testFocusPoolInitialState()`
- **Setup:** Create level-1 Champion; inspect focus_pool_max and focus_points; add 2 focus-granting feats; reinspect
- **Expected:** Initial focus_pool_max = 1; after 2 feats = 3; Refocus activity takes 10-minute duration
- **Roles:** authenticated player

### TC-CHP-14 — Lay on Hands granted to all good-aligned champions at level 1
- **AC:** `[NEW]` All good-aligned champions start with lay on hands devotion spell
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionFocusPoolTest::testLayOnHandsGrantedAtLevel1()`
- **Setup:** Create Champions with each of the 3 good causes (Paladin/Redeemer/Liberator); inspect devotion_spells
- **Expected:** lay on hands present in devotion_spells for all three
- **Roles:** authenticated player

### TC-CHP-15 — Devotion spells auto-heighten to half level rounded up; use CHA for attacks/DCs
- **AC:** `[NEW]` Devotion spells auto-heighten to half level rounded up; spell attacks/DCs use Charisma
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionFocusPoolTest::testDevotionSpellAutoHeightenAndCha()`
- **Setup:** Champion at levels 1, 3, 5 with CHA 16 (mod +3); inspect effective devotion spell level; inspect spell_attack and spell_dc modifiers
- **Expected:** Level 1 → spell level 1; level 3 → spell level 2; level 5 → spell level 3; spell_attack and spell_dc reference CHA modifier
- **Roles:** authenticated player

### TC-CHP-16 — Focus pool at 0: devotion spells blocked
- **AC:** `[TEST-ONLY]` Focus pool at 0: devotion spells blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionFocusPoolTest::testDevotionSpellsBlockedAtZeroFocus()`
- **Setup:** Drain focus pool to 0; attempt to cast lay on hands
- **Expected:** Cast blocked; error references empty focus pool
- **Roles:** authenticated player

### TC-CHP-17 — Level 1: Shield Block general feat granted for free
- **AC:** `[NEW]` Level 1: Shield Block general feat (free)
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testShieldBlockGrantedAtLevel1()`
- **Setup:** Create level-1 Champion; inspect general_feats
- **Expected:** Shield Block present in general_feats without requiring a feat slot
- **Roles:** authenticated player

### TC-CHP-18 — Level 3: Divine Ally selection — all 3 options accepted and stored; selection is permanent
- **AC:** `[NEW]` Level 3: Divine Ally — Blade Ally, Shield Ally, or Steed Ally; selection permanent
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testDivineAllySelectionAtLevel3()`
- **Setup:** Champion at level 3; select Blade Ally; inspect stored divine_ally; attempt to change after selection
- **Expected:** Blade Ally stored; change attempt blocked (immutable after selection); same for Shield Ally and Steed Ally selection paths
- **Roles:** authenticated player

### TC-CHP-19 — Blade Ally: weapon gets property rune + critical specialization
- **AC:** `[NEW]` Blade Ally: weapon gets property rune + crit specialization
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testBladeAllyEffects()`
- **Setup:** Champion at level 3 with Blade Ally; inspect primary weapon properties and critical hit behavior
- **Expected:** Property rune active on weapon; critical specialization effect applies on critical hit
- **Roles:** authenticated player

### TC-CHP-20 — Shield Ally: +2 Hardness and +50% HP/Broken Threshold on shield
- **AC:** `[NEW]` Shield Ally: +2 Hardness, +50% HP/Broken Threshold
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testShieldAllyEffects()`
- **Setup:** Champion at level 3 with Shield Ally; equip shield; inspect Hardness, HP, and Broken Threshold
- **Expected:** Shield Hardness += 2; Shield HP = base × 1.5 (rounded up); Broken Threshold = base × 1.5
- **Roles:** authenticated player

### TC-CHP-21 — Level 5: Simple and martial weapon proficiency increases to Expert
- **AC:** `[NEW]` Level 5: simple and martial weapon proficiency increases to Expert
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testWeaponProficiencyExpertAtLevel5()`
- **Setup:** Champion at level 5; inspect simple_weapons and martial_weapons proficiency
- **Expected:** Both = Expert
- **Roles:** authenticated player

### TC-CHP-22 — Level 7: All armor/unarmored to Expert; armor specialization for medium/heavy
- **AC:** `[NEW]` Level 7: all armor and unarmored defense increases to Expert; armor specialization for medium/heavy unlocked
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testArmorExpertAtLevel7()`
- **Setup:** Champion at level 7 wearing medium armor; inspect armor proficiency; inspect armor specialization availability
- **Expected:** light_armor=Expert; medium_armor=Expert; heavy_armor=Expert; unarmored=Expert; medium/heavy armor specialization effects active
- **Roles:** authenticated player

### TC-CHP-23 — Level 7: Weapon Specialization — +2/+3/+4 at Expert/Master/Legendary
- **AC:** `[NEW]` Level 7: Weapon Specialization — +2/+3/+4 at Expert/Master/Legendary
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testWeaponSpecializationAtLevel7()`
- **Setup:** Champion at level 7 with Expert-proficiency weapon; inspect damage modifier
- **Expected:** Damage bonus = +2 for Expert; +3 for Master; +4 for Legendary
- **Roles:** authenticated player

### TC-CHP-24 — Level 9: Champion class DC and divine spell attack/DC to Expert
- **AC:** `[NEW]` Level 9: champion class DC and divine spell attack rolls/DCs increase to Expert
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testClassDcAndDivineSpellExpertAtLevel9()`
- **Setup:** Champion at level 9; inspect champion_class_dc proficiency and divine_spell_attack/DC proficiency
- **Expected:** All three = Expert
- **Roles:** authenticated player

### TC-CHP-25 — Level 9: Divine Smite — reaction proc also inflicts persistent good damage = CHA modifier
- **AC:** `[NEW]` Level 9: Divine Smite — Champion's Reaction also inflicts persistent good damage = CHA modifier
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testDivineSmiteAtLevel9()`
- **Setup:** Champion at level 9 with CHA 16 (mod +3); trigger Champion's Reaction; inspect persistent damage on target
- **Expected:** persistent_good_damage = 3 (CHA modifier); applied in addition to normal reaction effects
- **Roles:** authenticated player

### TC-CHP-26 — Level 9: Fortitude to Master (successes → critical successes); Reflex to Expert
- **AC:** `[NEW]` Level 9: Fortitude saves increase to Master (successes become critical successes); Reflex saves increase to Expert
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testSaveProficienciesAtLevel9()`
- **Setup:** Champion at level 9; inspect Fortitude and Reflex proficiency; simulate Fortitude success
- **Expected:** Fortitude=Master; Reflex=Expert; Fortitude success result = critical success
- **Roles:** authenticated player

### TC-CHP-27 — Level 11: Perception to Expert; Will to Master (successes → critical successes)
- **AC:** `[NEW]` Level 11: Perception increases to Expert; Will saves increase to Master (successes become critical successes)
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testPerceptionAndWillAtLevel11()`
- **Setup:** Champion at level 11; inspect Perception and Will proficiency; simulate Will success
- **Expected:** Perception=Expert; Will=Master; Will success result = critical success
- **Roles:** authenticated player

### TC-CHP-28 — Level 11: Exalt — Retributive Strike upgraded to affect allies within 15 ft at –5 penalty
- **AC:** `[NEW]` Level 11: Exalt — Retributive Strike: allies within 15 ft can react-Strike at –5; cannot be reduced
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testExaltRetributiveStrikeAtLevel11()`
- **Setup:** Paladin Champion at level 11; trigger Retributive Strike; inspect allies within 15 ft reaction options
- **Expected:** Allies within 15 ft receive react-Strike option; penalty = –5; penalty cannot be reduced by any means
- **Roles:** authenticated player

### TC-CHP-29 — Level 11: Exalt — Glimpse of Redemption resistance applies to all within 15 ft (–2 each)
- **AC:** `[NEW]` Level 11: Exalt — Glimpse resistance applies to all within 15 ft (–2 each)
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testExaltGlimpseAtLevel11()`
- **Setup:** Redeemer Champion at level 11 with 3 allies within 15 ft; trigger Glimpse of Redemption; inspect resistance applied to each ally
- **Expected:** All allies within 15 ft receive resistance = (base - 2 per ally); applies to all, not just the triggering ally
- **Roles:** authenticated player

### TC-CHP-30 — Level 11: Exalt — Liberating Step all allies within 15 ft can Step
- **AC:** `[NEW]` Level 11: Exalt — Liberating Step: all within 15 ft can Step
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testExaltLiberatingStepAtLevel11()`
- **Setup:** Liberator Champion at level 11 with allies within 15 ft; trigger Liberating Step
- **Expected:** All allies within 15 ft receive Step as free action option (not just the triggering ally)
- **Roles:** authenticated player

### TC-CHP-31 — Level 13: All armor/unarmored to Master; weapons (simple/martial/unarmed) to Master
- **AC:** `[NEW]` Level 13: all armor and unarmored to Master; simple/martial/unarmed proficiency to Master
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testArmorAndWeaponMasterAtLevel13()`
- **Setup:** Champion at level 13; inspect all armor proficiency fields and weapon proficiency fields
- **Expected:** All armor categories and unarmored = Master; simple/martial/unarmed = Master
- **Roles:** authenticated player

### TC-CHP-32 — Level 15: Greater Weapon Specialization — +4/+6/+8 at Expert/Master/Legendary
- **AC:** `[NEW]` Level 15: Greater Weapon Specialization — +4/+6/+8 at Expert/Master/Legendary
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testGreaterWeaponSpecializationAtLevel15()`
- **Setup:** Champion at level 15 with Master-proficiency weapon; inspect weapon damage modifier
- **Expected:** Damage bonus = +6 for Master; Expert = +4; Legendary = +8
- **Roles:** authenticated player

### TC-CHP-33 — Level 17: Champion class DC and divine spell attack/DC to Master; all armor to Legendary
- **AC:** `[NEW]` Level 17: champion class DC and divine rolls/DCs increase to Master; all armor + unarmored increases to Legendary
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testLevel17MasteryAndLegendaryArmor()`
- **Setup:** Champion at level 17; inspect champion_class_dc, divine_spell_attack, divine_spell_dc, and all armor proficiency fields
- **Expected:** champion_class_dc=Master; divine_spell_attack=Master; divine_spell_dc=Master; all armor categories and unarmored = Legendary
- **Roles:** authenticated player

### TC-CHP-34 — Level 19: Hero's Defiance devotion spell granted
- **AC:** `[NEW]` Level 19: gain hero's defiance devotion spell
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testHeroesDefianceAtLevel19()`
- **Setup:** Champion at level 19; inspect devotion_spells
- **Expected:** hero's defiance present in devotion_spells; description references defying fate and continuing to fight
- **Roles:** authenticated player

### TC-CHP-35 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated features do not appear before required level
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionLevelFeaturesTest::testLevelGatedFeaturesNotGrantedEarly()`
- **Setup:** Champion at level 2; inspect Divine Ally, Weapon Expert, Armor Expert, Weapon Specialization, Exalt availability
- **Expected:** None of the level-gated features present at level 2; only level-1 features (Shield Block, Deific Weapon, Champion's Reaction, Lay on Hands) present
- **Roles:** authenticated player

### TC-CHP-36 — Oath feat: only one Oath feat per champion enforced
- **AC:** `[NEW]` Oath feat: only one Oath feat per champion enforced
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionEdgeCaseTest::testOathFeatLimitEnforced()`
- **Setup:** Champion with one Oath feat already selected; attempt to select a second Oath feat
- **Expected:** Second Oath feat selection blocked; error references one-Oath-per-champion rule
- **Roles:** authenticated player

### TC-CHP-37 — Exalt Retributive Strike ally penalty –5 cannot be reduced
- **AC:** `[NEW]` Exalt Retributive Strike ally at –5 penalty: correctly applied, cannot be reduced
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionEdgeCaseTest::testExaltRetributiveStrikePenaltyCannotBeReduced()`
- **Setup:** Champion at level 11; ally with a feat that reduces attack penalties; trigger Exalt Retributive Strike; inspect ally's attack roll modifier
- **Expected:** Penalty = –5 regardless of penalty-reduction feats/abilities
- **Roles:** authenticated player

### TC-CHP-38 — Class feat schedule: level 1 and every even level
- **AC:** `[NEW]` Champion gains class feat at level 1 and every even-numbered level (2, 4, 6 … 20)
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionFeatProgressionTest::testClassFeatSchedule()`
- **Setup:** Level Champion from 1 to 4; inspect class_feats_available at each level
- **Expected:** Class feat slots at levels 1, 2, 4; not at level 3
- **Roles:** authenticated player

### TC-CHP-39 — General feats at levels 3, 7, 11, 15, 19 (including free Shield Block at 1)
- **AC:** `[NEW]` General feats at levels 3, 7, 11, 15, 19; free Shield Block at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionFeatProgressionTest::testGeneralFeatSchedule()`
- **Setup:** Level Champion to 3; inspect general_feats_available (Shield Block already granted free at 1; new general feat at 3)
- **Expected:** Shield Block in feats at level 1 (no slot consumed); general feat slot granted at level 3
- **Roles:** authenticated player

### TC-CHP-40 — Player cannot modify another player's Champion character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `ChampionClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Champion owned by user A; attempt cause change or divine ally selection as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-CHP-41 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Tenet hierarchy conflict resolution specifics | TC-CHP-08 verifies a conflict-resolution mechanism exists; the exact priority order and all specific conflict outcomes are not enumerated in the AC. PM/BA must define the full tenet priority matrix before this TC can be fully specified |
| Deity-specific skill grant | TC-CHP-03 verifies that a deity_skill field is populated; verifying that the correct skill is granted per each deity requires a full deity catalog that is not yet defined |
| Steed Ally (young animal companion mount) mechanics | TC-CHP-18 verifies Steed Ally selection is stored; the full animal companion stats/behaviors are not in scope for this AC and require a separate feature |
| Champion's Reaction trigger conditions (enemy within reach, ally in specific range) | TCs CHP-10/11/12 test the reaction outcomes; whether the trigger conditions (reach, range) are correctly checked requires a combat-adjacent positioning system that may not yet be in scope |
| Blade Ally specific property rune (which rune is granted at which level) | TC-CHP-19 verifies the rune mechanism exists; the exact rune granted at each level is not specified in the AC |

---

## Regression risk areas

1. **Cause-alignment enforcement**: three causes, three alignment requirements — verify no off-by-one in alignment enum comparison (Lawful Good ≠ Neutral Good ≠ Chaotic Good).
2. **Code violation state machine**: focus pool suspension + divine ally suspension must both be restored atomically on atone; partial restore is a bug.
3. **Exalt Retributive Strike –5 penalty immutability**: if the system has a general "reduce penalty" hook, it must be bypassed specifically for this reaction — risk of regression if penalty-reduction logic is applied globally.
4. **Divine Smite persistent damage**: added at level 9 to the Champion's Reaction; must not double-count with base reaction resistance or apply to both Cause A and Cause B outcomes of Glimpse of Redemption.
5. **Focus pool shared system**: Champion focus pool likely shares implementation with Bard focus pool (`dc-cr-focus-spells`); verify Champion-specific devotion spell auto-heighten is not affected by Bard composition spell heighten logic.
6. **HP calculation overlap**: Champion HP = 10 + CON mod; verify no conflict with ancestry HP bonus additions.
7. **QA audit regression**: no new routes per security AC; if any are added, they must be probed in qa-permissions.json before release.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-class-champion

## Gap analysis reference
- DB sections: core/ch03/Champion (REQs 964–1042+)
- Track B: no existing ChampionService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Champion exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Champion HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Fortitude and Will, Trained Reflex; Trained Religion + deity-specific skill + 2 + INT additional; Trained all armor categories (light, medium, heavy); Trained divine spell attacks/DCs (Charisma).

### Deity, Cause & Code
- [ ] `[NEW]` At level 1, player selects a deity and one of three Causes matching alignment: Paladin (Lawful Good), Redeemer (Neutral Good), Liberator (Chaotic Good).
- [ ] `[NEW]` Champion has a mandatory behavioral code; violation removes focus pool and divine ally until atone ritual completed.
- [ ] `[NEW]` Deity-granted weapon (Deific Weapon): uncommon access granted; d4/simple weapon damage die upgraded by one step.
- [ ] `[NEW]` Tenets are hierarchical; higher tenets override lower in conflicts.

### Champion's Reaction (Cause-determined)
- [ ] `[NEW]` Paladin — Retributive Strike: ally gains resistance to all damage = 2 + level; if foe in reach, make a melee Strike.
- [ ] `[NEW]` Redeemer — Glimpse of Redemption: foe chooses (A) ally is unharmed or (B) ally gains resistance = 2 + level; then foe becomes enfeebled 2 until end of its next turn.
- [ ] `[NEW]` Liberator — Liberating Step: ally gains resistance = 2 + level; ally can attempt to break free (new save or free Escape); ally can Step as free action.

### Devotion Spells & Focus Pool
- [ ] `[NEW]` Champion starts with focus pool of 1 Focus Point (max 3 with feats); Refocus = 10 minutes prayer/service.
- [ ] `[NEW]` All good-aligned champions start with lay on hands devotion spell.
- [ ] `[NEW]` Devotion spells auto-heighten to half level rounded up; spell attacks/DCs use Charisma.

### Class Features Unlocks by Level
- [ ] `[NEW]` Level 1: Shield Block general feat (free).
- [ ] `[NEW]` Level 3: Divine Ally selection — Blade Ally (weapon gets property rune + crit specialization), Shield Ally (+2 Hardness, +50% HP/Broken Threshold), or Steed Ally (young animal companion mount).
- [ ] `[NEW]` Level 5: simple and martial weapon proficiency increases to Expert.
- [ ] `[NEW]` Level 7: all armor and unarmored defense increases to Expert; armor specialization for medium and heavy armor unlocked.
- [ ] `[NEW]` Level 7: Weapon Specialization — +2/+3/+4 damage at Expert/Master/Legendary.
- [ ] `[NEW]` Level 9: champion class DC and divine spell attack rolls/DCs increase to Expert.
- [ ] `[NEW]` Level 9: Divine Smite — Champion's Reaction on proc also inflicts persistent good damage = CHA modifier.
- [ ] `[NEW]` Level 9: Fortitude saves increase to Master (successes become critical successes); Reflex saves increase to Expert.
- [ ] `[NEW]` Level 11: Perception increases to Expert; Will saves increase to Master (successes become critical successes).
- [ ] `[NEW]` Level 11: Exalt — Champion's Reaction upgrades to affect nearby allies: Retributive Strike allies within 15 ft can react-Strike at –5; Glimpse resistance applies to all within 15 ft (–2 each); Liberating Step all within 15 ft can Step.
- [ ] `[NEW]` Level 13: all armor and unarmored defense increases to Master; weapon proficiency (simple, martial, unarmed) increases to Master.
- [ ] `[NEW]` Level 15: Greater Weapon Specialization — +4/+6/+8 at Expert/Master/Legendary.
- [ ] `[NEW]` Level 17: champion class DC and divine rolls/DCs increase to Master; all armor + unarmored increases to Legendary.
- [ ] `[NEW]` Level 19: gain hero's defiance devotion spell (defy fate, continue fighting with divine energy).

### Feat Progression
- [ ] `[NEW]` Champion gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Code violation: focus pool inaccessible and divine ally benefits suspended until atone ritual; atone restores both.
- [ ] `[NEW]` Oath feat: only one Oath feat per champion enforced.
- [ ] `[NEW]` Exalt Retributive Strike ally at –5 penalty: correctly applied, cannot be reduced.
- [ ] `[NEW]` Cause must match alignment: Paladin requires Lawful Good; invalid cause/alignment combination blocked.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Focus pool at 0: devotion spells blocked.
- [ ] `[TEST-ONLY]` Invalid deity/cause combination rejected.
- [ ] `[TEST-ONLY]` Divine ally selection is permanent; attempting to change post-selection is blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
