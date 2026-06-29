# Suite Activation: dc-cr-class-fighter

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T02:13:19+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-class-fighter"`**  
   This links the test to the living requirements doc at `features/dc-cr-class-fighter/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-class-fighter-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-class-fighter",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-class-fighter"`**  
   Example:
   ```json
   {
     "id": "dc-cr-class-fighter-<route-slug>",
     "feature_id": "dc-cr-class-fighter",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-class-fighter",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-class-fighter

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Fighter Class — identity/stats, Attack of Opportunity, combat trait rules (Press/Stance/Flourish), Power Attack scaling, feat progression, edge cases
**AC source:** `features/dc-cr-class-fighter/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Fighter class logic; first fighter implementation.
- Dependency note: `dc-cr-equipment-system` is **in-progress Release B** (not deferred). Fighter weapon/armor proficiency TCs depend on equipment entity existence. TCs that test specific weapon interactions (advanced weapons, Expert weapon proficiency bonus to attack) should be flagged `pending-dev-confirmation` at Stage 0 if `dc-cr-equipment-system` has not yet shipped. Identity, proficiency tier storage, trait enforcement, and AoO feature flag TCs have no external dependency and can activate immediately.
- `dc-cr-character-class` ✓ and `dc-cr-character-creation` ✓ — no blocker on those.
- Cross-class note: Champion and Barbarian also start with Expert Fortitude; Fighter is the only class with **Expert** (not Trained) Perception and **Expert** weapons at level 1 — regression risk if proficiency initialization code uses a class-agnostic default.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class selection, HP/proficiency, AoO, trait rules, Power Attack scaling, feat progression, edge cases |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, QA audit regression |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. No Playwright suite needed at this scope.

---

## Test cases

### TC-FGT-01 — Fighter exists as selectable class; STR or DEX as key ability (player chooses)
- **AC:** `[NEW]` Fighter selectable in character creation; STR or DEX as key ability boost at level 1 (player chooses)
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterClassTest::testFighterExistsWithStrOrDexKeyAbility()`
- **Setup:** Query `character_class` nodes; load fighter; create fighter with STR choice; create fighter with DEX choice
- **Expected:** Fighter class node exists; both STR and DEX accepted as key_ability; no other key ability accepted
- **Roles:** authenticated player

### TC-FGT-02 — Fighter HP calculation: 10 + CON modifier per level
- **AC:** `[NEW]` Fighter HP = 10 + CON modifier per level
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterClassTest::testHpCalculation()`
- **Setup:** Create Fighter with CON 14 (mod +2); inspect HP at level 1 and level 2
- **Expected:** Level 1 HP = 12; Level 2 HP = 24
- **Roles:** authenticated player

### TC-FGT-03 — Fighter initial proficiencies: Expert Perception, Expert Fortitude/Reflex, Trained Will
- **AC:** `[NEW]` Expert Perception; Expert Fortitude and Expert Reflex, Trained Will
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterClassTest::testInitialSavesAndPerception()`
- **Setup:** Create level-1 Fighter; inspect Perception and all saving throw proficiency fields
- **Expected:** Perception=Expert; Fortitude=Expert; Reflex=Expert; Will=Trained
- **Roles:** authenticated player

### TC-FGT-04 — Fighter initial weapon proficiencies: Expert simple/martial/unarmed; Trained advanced
- **AC:** `[NEW]` Expert simple and martial weapons + unarmed attacks (unique to Fighter at level 1); Trained advanced weapons
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterClassTest::testInitialWeaponProficiencies()`
- **Setup:** Create level-1 Fighter; inspect weapon proficiency fields
- **Expected:** simple_weapons=Expert; martial_weapons=Expert; unarmed=Expert; advanced_weapons=Trained
- **Roles:** authenticated player

### TC-FGT-05 — Fighter initial armor proficiencies: Trained light, medium, heavy
- **AC:** `[NEW]` Trained all armor categories (light, medium, heavy)
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterClassTest::testInitialArmorProficiencies()`
- **Setup:** Create level-1 Fighter; inspect armor proficiency fields
- **Expected:** light_armor=Trained; medium_armor=Trained; heavy_armor=Trained; unarmored=Trained
- **Roles:** authenticated player

### TC-FGT-06 — Fighter starts with Expert weapons at level 1 (not Trained like other classes)
- **AC:** `[NEW]` Expert simple and martial weapons unique to Fighter at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterClassTest::testExpertWeaponsUniqueAtLevel1()`
- **Setup:** Create level-1 Fighter and level-1 Champion; compare simple_weapons proficiency
- **Expected:** Fighter simple_weapons = Expert; Champion simple_weapons = Trained (or lower); confirms Fighter uniqueness
- **Roles:** authenticated player

### TC-FGT-07 — Attack of Opportunity granted at level 1
- **AC:** `[NEW]` Fighter gains Attack of Opportunity at level 1 as a class feature
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterCombatTest::testAttackOfOpportunityGrantedAtLevel1()`
- **Setup:** Create level-1 Fighter; inspect class_features and reactions
- **Expected:** attack_of_opportunity feature present; reaction type; available at level 1
- **Roles:** authenticated player

### TC-FGT-08 — Attack of Opportunity fires only once per triggering event
- **AC:** `[NEW]` Attack of Opportunity: only once per triggering event
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterCombatTest::testAttackOfOpportunityOncePerTrigger()`
- **Setup:** Fighter in combat; single trigger event (creature uses manipulate in reach); attempt to fire AoO twice on same trigger
- **Expected:** First AoO fires; second AoO on same trigger event rejected
- **Roles:** authenticated player

### TC-FGT-09 — Attack of Opportunity does not count toward MAP
- **AC:** `[NEW]` Attack of Opportunity: does not count toward MAP
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterCombatTest::testAttackOfOpportunityNoMapPenalty()`
- **Setup:** Fighter who has already made 2 attacks this turn (MAP –10); trigger AoO; inspect attack roll penalty applied to AoO
- **Expected:** AoO uses no MAP penalty (or uses pre-AoO MAP); subsequent attacks after AoO still carry the same MAP as before AoO
- **Roles:** authenticated player

### TC-FGT-10 — Press trait: blocked when not under MAP (first action of turn)
- **AC:** `[NEW]` Press trait: can only be used when under MAP (not first action of turn)
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testPressBLockedWithNoMap()`
- **Setup:** Fighter at start of turn (0 attacks made, no MAP penalty); attempt a Press action
- **Expected:** Press action blocked; error references MAP requirement
- **Roles:** authenticated player

### TC-FGT-11 — Press trait: allowed when under MAP (second or later attack)
- **AC:** `[NEW]` Press trait: can only be used when under MAP
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testPressAllowedUnderMap()`
- **Setup:** Fighter who has made one attack this turn (now under MAP –5); attempt a Press action
- **Expected:** Press action allowed; proceeds normally
- **Roles:** authenticated player

### TC-FGT-12 — Press trait: cannot be used with Ready action
- **AC:** `[NEW]` Press trait: cannot be Readied
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testPressCannotBeReadied()`
- **Setup:** Fighter attempts to Ready a Press action
- **Expected:** Ready blocked for Press actions; error references Press cannot be Readied
- **Roles:** authenticated player

### TC-FGT-13 — Press trait: failure does not apply critical failure effects
- **AC:** `[NEW]` Press trait: failure on a press action does not apply crit-fail effects
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testPressFailureNoCritFailEffect()`
- **Setup:** Fighter under MAP; execute Press action; simulate failure result; inspect applied effects
- **Expected:** Normal failure effects applied (if any); crit-fail effects NOT applied on a failure degree result
- **Roles:** authenticated player

### TC-FGT-14 — Stance trait: only one stance active at a time
- **AC:** `[NEW]` Stance trait: only one stance active at a time per encounter
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testOnlyOneStanceActive()`
- **Setup:** Fighter with two stance feats; activate first stance; activate second stance; inspect active_stance
- **Expected:** Second stance activation ends the first; active_stance = second; first stance no longer active
- **Roles:** authenticated player

### TC-FGT-15 — Stance trait: 1-round cooldown on stance actions
- **AC:** `[NEW]` Stance trait: 1-round cooldown on stance actions
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testStanceOneRoundCooldown()`
- **Setup:** Fighter activates a stance; attempt to use another stance action on the same turn (before next round)
- **Expected:** Second stance action on same turn blocked; error references 1-round cooldown
- **Roles:** authenticated player

### TC-FGT-16 — Stance trait: stances end on knockout, violation, or end of encounter
- **AC:** `[NEW]` Stances end on knockout/violation/end of encounter
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testStanceEndsOnKnockout()`
- **Setup:** Fighter in active stance; trigger knockout state; inspect active_stance
- **Expected:** active_stance = null/none after knockout; same for end-of-encounter trigger
- **Roles:** authenticated player

### TC-FGT-17 — Two stances simultaneously: not permitted
- **AC:** `[TEST-ONLY]` Two stances simultaneously: not permitted
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testTwoStancesSimultaneouslyBlocked()`
- **Setup:** Attempt to force two stance flags active at same time (direct entity manipulation test)
- **Expected:** Only one stance_active flag can be true at any time; constraint enforced at data layer
- **Roles:** authenticated player

### TC-FGT-18 — Flourish trait: only one flourish action per turn
- **AC:** `[NEW]` Flourish trait: only one flourish action per turn enforced
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testOnlyOneFlourishPerTurn()`
- **Setup:** Fighter uses one flourish action in a turn; attempts a second flourish action same turn
- **Expected:** Second flourish action blocked; error references flourish limit
- **Roles:** authenticated player

### TC-FGT-19 — Flourish limit: second flourish in same turn blocked (failure mode)
- **AC:** `[TEST-ONLY]` Flourish limit: second flourish in same turn blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterTraitTest::testSecondFlourishBlockedIsFailureMode()`
- **Setup:** Duplicate of TC-FGT-18 from Failure Modes section; explicitly tagged as failure-mode test
- **Expected:** Same as TC-FGT-18; keeps the failure-mode origin traceable
- **Roles:** authenticated player

### TC-FGT-20 — Power Attack: counts as 2 MAP attacks
- **AC:** `[NEW]` Power Attack: counts as 2 MAP attacks
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterCombatTest::testPowerAttackCountsAsTwoMapAttacks()`
- **Setup:** Fighter at start of turn (MAP = 0); use Power Attack; inspect MAP penalty after the action
- **Expected:** After Power Attack, MAP penalty = –10 (as if 2 attacks made, not 1)
- **Roles:** authenticated player

### TC-FGT-21 — Power Attack: +1 extra damage die at base; +2 at level 10; +3 at level 18
- **AC:** `[NEW]` Power Attack: +1 extra damage die (becomes +2 at 10th, +3 at 18th); correctly scales
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterCombatTest::testPowerAttackDiescaling()`
- **Setup:** Fighter at levels 1, 10, 18 with same weapon; inspect Power Attack damage dice count at each level
- **Expected:** Level 1: weapon dice +1 extra die; Level 10: weapon dice +2 extra dice; Level 18: weapon dice +3 extra dice
- **Roles:** authenticated player

### TC-FGT-22 — Class feat schedule: level 1 and every even level
- **AC:** `[NEW]` Fighter gains class feat at level 1 and every even-numbered level (2, 4, 6 … 20)
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterFeatProgressionTest::testClassFeatSchedule()`
- **Setup:** Level Fighter 1 → 4; inspect class_feats_available at each level
- **Expected:** Class feat slots at levels 1, 2, 4; not at level 3
- **Roles:** authenticated player

### TC-FGT-23 — Ability boost schedule: levels 5, 10, 15, 20
- **AC:** `[NEW]` Ability boosts at levels 5, 10, 15, 20
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterFeatProgressionTest::testAbilityBoostSchedule()`
- **Setup:** Level Fighter to 5; inspect ability_boosts_available
- **Expected:** Ability boost slot present at level 5; not at level 4
- **Roles:** authenticated player

### TC-FGT-24 — Skill feat / general feat / ancestry feat schedule
- **AC:** `[NEW]` Skill feats at every even level; general feats at 3, 7, 11, 15, 19; ancestry feats at 5, 9, 13, 17
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterFeatProgressionTest::testFeatSchedule()`
- **Setup:** Level Fighter 1 → 3; inspect skill_feats at level 2; general_feats at level 3
- **Expected:** Skill feat slot at level 2; general feat slot at level 3; no general feat at level 2
- **Roles:** authenticated player

### TC-FGT-25 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated features do not appear before required level
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterLevelFeaturesTest::testNoEarlyLevelFeatures()`
- **Setup:** Fighter at level 1; inspect for ability boost slot, general feat slot
- **Expected:** Ability boost not present at level 1; general feat not present at level 1 (first general feat at level 3)
- **Roles:** authenticated player

### TC-FGT-26 — Press actions blocked when no prior attack has been made (edge case)
- **AC:** `[NEW]` Press actions blocked when no prior attack has been made this turn (not under MAP)
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterEdgeCaseTest::testPressBlockedWithoutPriorAttack()`
- **Setup:** Fighter fresh turn (no attacks yet); attempt a Press-trait action
- **Expected:** Blocked; identical to TC-FGT-10 but sourced from Edge Cases section for traceability
- **Roles:** authenticated player

### TC-FGT-27 — Player cannot modify another player's Fighter character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `FighterClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Fighter owned by user A; attempt class feature or stance modification as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-FGT-28 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| AoO trigger conditions (manipulate/move/ranged attack/leave square) | TC-FGT-07 through TC-FGT-09 verify the feature exists and per-event limit; verifying all four trigger types requires a combat-positioning system (who is in whose reach, which actions count as Manipulate) that may not be in scope at this AC level |
| Stance violation condition | TC-FGT-16 tests knockout as a stance-ending event; "violation" is listed as another ender — the specific definition of a stance violation is not enumerated and requires PM to define per-stance |
| Press failure vs critical failure degree | TC-FGT-13 verifies crit-fail effects are not applied on a failure; the exact effects for each press action's failure degree require an effect catalog |
| Power Attack at level 10/18 transitions | TC-FGT-21 tests scaling; the exact die progression requires the full weapon damage die catalog from `dc-cr-equipment-system` |

---

## Deferred / conditional dependency summary

| TC | Dependency | Status |
|---|---|---|
| TC-FGT-04 | Weapon entity existence (simple/martial/advanced weapon items) | `dc-cr-equipment-system` in-progress Release B |
| TC-FGT-05 | Armor entity existence (light/medium/heavy items) | `dc-cr-equipment-system` in-progress Release B |
| TC-FGT-06 | Comparative weapon proficiency check requires weapon entities | `dc-cr-equipment-system` in-progress Release B |
| TC-FGT-21 | Power Attack die scaling requires weapon damage dice | `dc-cr-equipment-system` in-progress Release B |

**Recommendation:** If `dc-cr-equipment-system` ships before this feature activates at Stage 0, all TCs can activate immediately. If not, flag TC-FGT-04/05/06/21 as `pending-dev-confirmation` and activate the remaining 24 TCs.

---

## Regression risk areas

1. **Expert proficiency at level 1**: Fighter is unique in having Expert weapons at level 1 (other classes start Trained); risk that the proficiency initialization logic uses a class-agnostic "Trained" default and fails to apply the Fighter exception.
2. **Expert Perception**: Fighter also starts at Expert Perception; most classes start Trained — same initialization risk as weapons.
3. **Expert Reflex and Fortitude simultaneously**: Fighter has Expert in both (most classes have Expert in only one save); verify both are initialized correctly without one overwriting the other.
4. **Attack of Opportunity MAP isolation**: AoO must not add to or consume from MAP; if the attack-counter is a simple integer, there's a risk the AoO Strike increments it.
5. **Press trait MAP check**: Press requires at least one prior attack (MAP penalty present); the check must evaluate MAP state at the time of the Press action, not at start-of-turn.
6. **Stance cooldown vs encounter reset**: 1-round cooldown must not persist across encounters; verify stance cooldown state is cleared on encounter end.
7. **Flourish per-turn limit**: limit must reset at the start of each new turn; risk of limit persisting beyond one turn if turn-start cleanup is not implemented.
8. **Power Attack MAP multiplication**: Power Attack counts as 2 attacks for MAP; verify the MAP increment is +10 (two steps) not +5 (one step) after a Power Attack.
9. **QA audit regression**: no new routes per security AC; verify no new endpoints introduced.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-class-fighter

## Gap analysis reference
- DB sections: core/ch03/Fighter (REQs 1172–1250+)
- Track B: no existing FighterService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓, dc-cr-equipment-system (in-progress Release B)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Fighter exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Fighter HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Expert Perception; Expert Fortitude and Expert Reflex, Trained Will; Expert simple and martial weapons + unarmed attacks (unique to Fighter at level 1); Trained advanced weapons; Trained all armor categories (light, medium, heavy).

### Attack of Opportunity (Level 1)
- [ ] `[NEW]` Fighter gains Attack of Opportunity at level 1 as a class feature (reaction; trigger: creature in reach uses manipulate/moves/ranged attacks/leaves a square; make a melee Strike).

### Class Feature Unlocks by Level
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20; skill feats at every even level; general feats at 3, 7, 11, 15, 19; ancestry feats at 5, 9, 13, 17.

### Key Combat Trait Rules
- [ ] `[NEW]` Press trait: can only be used when under MAP (not first action of turn); cannot be Readied; failure on a press action does not apply crit-fail effects.
- [ ] `[NEW]` Stance trait: only one stance active at a time per encounter; 1-round cooldown on stance actions; stances end on knockout/violation/end of encounter.
- [ ] `[NEW]` Flourish trait: only one flourish action per turn enforced.

### Feat Progression
- [ ] `[NEW]` Fighter gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).

---

## Edge Cases

- [ ] `[NEW]` Press actions blocked when no prior attack has been made this turn (not under MAP).
- [ ] `[NEW]` Stance change: previous stance immediately ends; new stance subject to 1-round cooldown.
- [ ] `[NEW]` Attack of Opportunity: only once per triggering event; does not count toward MAP.
- [ ] `[NEW]` Power Attack: counts as 2 MAP attacks; +1 extra damage die (becomes +2 at 10th, +3 at 18th); correctly scales.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Attempting a press action before any other attack this turn: blocked.
- [ ] `[TEST-ONLY]` Two stances simultaneously: not permitted.
- [ ] `[TEST-ONLY]` Flourish limit: second flourish in same turn blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
