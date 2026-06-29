# Suite Activation: dc-cr-class-monk

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-09T01:31:50+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-class-monk"`**  
   This links the test to the living requirements doc at `features/dc-cr-class-monk/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-class-monk-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-class-monk",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-class-monk"`**  
   Example:
   ```json
   {
     "id": "dc-cr-class-monk-<route-slug>",
     "feature_id": "dc-cr-class-monk",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-class-monk",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-class-monk

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Monk Class — identity/stats, Flurry of Blows, Ki spells/focus pool, stance unarmed attacks, Mountain Stance, Stunning Fist, Fuse Stance, feat progression, edge cases
**AC source:** `features/dc-cr-class-monk/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Monk class logic; first monk implementation.
- No deferred dependencies — `dc-cr-character-class` ✓ and `dc-cr-character-creation` ✓. All TCs can be activated at Stage 0.
- Cross-class note: Fighter also uses Stance trait (1-round cooldown, one-at-a-time). Monk stances share the same enforcement pattern; regression risk if stance logic is shared code that Fighter stances already exercise — verify Monk-specific unarmed attack profiles within stances are not lost when Fighter's stance system initializes.
- Cross-class note: Champion focus pool shares similar initialization; Monk uses WIS for Ki spells (same as Cleric/Druid) — verify spellcasting stat resolver returns WIS for Monk Ki spells.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class identity, HP/proficiency, Flurry of Blows, Ki spells/focus pool, stance profiles, Mountain Stance, Stunning Fist, Fuse Stance, feat progression, edge cases |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, QA audit regression |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. No Playwright suite needed at this scope.

---

## Test cases

### TC-MNK-01 — Monk exists as selectable class; STR or DEX as key ability (player chooses)
- **AC:** `[NEW]` Monk selectable in character creation; STR or DEX as key ability boost at level 1 (player chooses)
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testMonkExistsWithStrOrDexKeyAbility()`
- **Setup:** Query `character_class` nodes; load monk; create monk with STR choice; create monk with DEX choice
- **Expected:** Monk class node exists; both STR and DEX accepted as key_ability; no other key ability accepted
- **Roles:** authenticated player

### TC-MNK-02 — Monk HP calculation: 10 + CON modifier per level
- **AC:** `[NEW]` Monk HP = 10 + CON modifier per level
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testHpCalculation()`
- **Setup:** Create Monk with CON 14 (mod +2); inspect HP at level 1 and level 2
- **Expected:** Level 1 HP = 12; Level 2 HP = 24
- **Roles:** authenticated player

### TC-MNK-03 — Monk initial proficiencies: saves, armor, unarmored
- **AC:** `[NEW]` Trained Perception; Expert Will, Trained Fortitude and Reflex; Untrained all armor, Expert unarmored defense
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testInitialProficiencies()`
- **Setup:** Create level-1 Monk; inspect all proficiency fields
- **Expected:** Perception=Trained; Will=Expert; Fortitude=Trained; Reflex=Trained; light_armor=Untrained; medium_armor=Untrained; heavy_armor=Untrained; unarmored=Expert
- **Roles:** authenticated player

### TC-MNK-04 — Monk fist base damage = 1d6 (not standard 1d4 unarmed)
- **AC:** `[NEW]` Monk fist base damage = 1d6 (not 1d4)
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testFistDamageIs1d6()`
- **Setup:** Create Monk; inspect unarmed_attack_damage_die for fist
- **Expected:** fist damage die = d6; not d4 (which is the default unarmed for other characters)
- **Roles:** authenticated player

### TC-MNK-05 — Monk unarmed attacks have no lethal/nonlethal penalty
- **AC:** `[NEW]` No lethal/nonlethal penalty on unarmed attacks
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testNoLethalNonlethalPenaltyOnUnarmed()`
- **Setup:** Create Monk; inspect unarmed_attack properties for lethal_nonlethal_penalty flag
- **Expected:** lethal_nonlethal_penalty = false (or absent); monk can freely choose lethal or nonlethal without penalty
- **Roles:** authenticated player

### TC-MNK-06 — Flurry of Blows granted at level 1; 1-action, makes two unarmed Strikes
- **AC:** `[NEW]` Flurry of Blows is a 1-action ability that makes two unarmed Strikes
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkCombatTest::testFlurryOfBlowsGranted()`
- **Setup:** Create level-1 Monk; inspect class_features for Flurry of Blows; inspect action_cost
- **Expected:** Flurry of Blows present in class_features; action_cost = 1; produces two unarmed Strike results
- **Roles:** authenticated player

### TC-MNK-07 — Flurry of Blows usable only once per turn
- **AC:** `[NEW]` Flurry of Blows usable once per turn
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkCombatTest::testFlurryOfBlowsOncePerTurn()`
- **Setup:** Monk uses Flurry of Blows; attempts to use it again on same turn
- **Expected:** Second use on same turn blocked; error references once-per-turn limit
- **Roles:** authenticated player

### TC-MNK-08 — Flurry of Blows: MAP increases normally after both attacks
- **AC:** `[NEW]` MAP increases normally after Flurry of Blows (both attacks count)
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkCombatTest::testFlurryOfBlowsMapIncrement()`
- **Setup:** Monk at start of turn (MAP = 0); use Flurry of Blows; inspect MAP penalty after
- **Expected:** MAP penalty = –10 after Flurry (two attacks counted); subsequent action at –10
- **Roles:** authenticated player

### TC-MNK-09 — Ki spells are focus spells; focus pool starts at 1; WIS is spellcasting ability
- **AC:** `[NEW]` Ki spells are focus spells; focus pool starts at 1 Focus Point (max 3 with feats); Wisdom is spellcasting ability
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkKiSpellTest::testKiSpellFocusPoolInitial()`
- **Setup:** Create level-1 Monk without any Ki feats; inspect focus_pool_max and spellcasting_ability
- **Expected:** focus_pool_max = 1 (base, before feats); spellcasting_ability = Wisdom
- **Roles:** authenticated player

### TC-MNK-10 — Each Ki spell feat grants +1 Focus Point (up to max 3)
- **AC:** `[NEW]` Ki spell feats each grant +1 Focus Point when taken
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkKiSpellTest::testKiSpellFeatGrantsFocusPoint()`
- **Setup:** Monk without Ki feats; take Ki Rush feat; inspect focus_pool_max; take Ki Strike feat; re-inspect
- **Expected:** After Ki Rush: focus_pool_max = 2; after Ki Strike: focus_pool_max = 3; no further increase beyond 3
- **Roles:** authenticated player

### TC-MNK-11 — Focus pool at 0: Ki spells blocked
- **AC:** `[NEW]` Ki spells require active focus points; casting with 0 FP is blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkKiSpellTest::testKiSpellBlockedAtZeroFocus()`
- **Setup:** Drain Monk focus pool to 0; attempt to cast a Ki spell
- **Expected:** Cast blocked; error references empty focus pool
- **Roles:** authenticated player

### TC-MNK-12 — Each stance feat provides unique unarmed attack profile while active
- **AC:** `[NEW]` Each stance feat provides unique unarmed attack profiles (damage die, traits) that replace or supplement base unarmed attacks while the stance is active
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkStanceTest::testStanceProvidesUnarmedAttackProfile()`
- **Setup:** Monk with a stance feat active (e.g., Tiger Stance); inspect unarmed_attack_die and unarmed_attack_traits while in stance vs without stance
- **Expected:** In-stance unarmed attack profile differs from base fist (different die and/or traits); base profile restored when stance ends
- **Roles:** authenticated player
- **Note to PM:** Exact unarmed attack profile per stance (Tiger/Wolf/Mountain/etc.) must be enumerated in AC or a stance catalog before all stances can be parametrized.

### TC-MNK-13 — Stance restriction: only one stance active at a time; new stance ends previous
- **AC:** `[NEW]` Stance restriction: only one stance active at a time; entering a new stance ends the previous
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkStanceTest::testOnlyOneStanceActive()`
- **Setup:** Monk with two stance feats; activate first stance; activate second stance; inspect active_stance
- **Expected:** Second stance activation immediately ends first stance; active_stance = second; first no longer active
- **Roles:** authenticated player

### TC-MNK-14 — Two stances simultaneously blocked (without Fuse Stance)
- **AC:** `[TEST-ONLY]` Two stances simultaneously: not permitted (without Fuse Stance feat)
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkStanceTest::testTwoStancesBlockedWithoutFuseStance()`
- **Setup:** Monk without Fuse Stance; attempt to force two stance flags active simultaneously (direct entity test)
- **Expected:** Only one stance_active flag at a time; data-layer constraint enforced
- **Roles:** authenticated player

### TC-MNK-15 — Mountain Stance: +4 item bonus to AC
- **AC:** `[NEW]` Mountain Stance: +4 item bonus to AC
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkMountainStanceTest::testMountainStanceAcBonus()`
- **Setup:** Monk activates Mountain Stance; inspect AC item_bonus
- **Expected:** AC item_bonus = +4 while in Mountain Stance; removed when stance ends
- **Roles:** authenticated player

### TC-MNK-16 — Mountain Stance: +2 circumstance bonus vs Shove and Trip
- **AC:** `[NEW]` Mountain Stance: +2 circumstance vs Shove/Trip
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkMountainStanceTest::testMountainStanceShoveTripBonus()`
- **Setup:** Monk in Mountain Stance; inspect circumstance bonuses to Shove and Trip defense
- **Expected:** circumstance_bonus_vs_shove = +2; circumstance_bonus_vs_trip = +2
- **Roles:** authenticated player

### TC-MNK-17 — Mountain Stance: DEX cap to AC = +0 (overrides DEX bonus)
- **AC:** `[NEW]` Mountain Stance: DEX cap to AC = +0; correctly overrides character DEX bonus even with bonuses from other sources
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkMountainStanceTest::testMountainStanceDexCapZero()`
- **Setup:** Monk with DEX 18 (mod +4) activates Mountain Stance; inspect effective DEX contribution to AC
- **Expected:** DEX contribution to AC = 0 (capped); full DEX mod not applied; other DEX-boosting sources also capped at 0
- **Roles:** authenticated player

### TC-MNK-18 — Mountain Stance: –5 ft Speed penalty
- **AC:** `[NEW]` Mountain Stance: –5 ft Speed
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkMountainStanceTest::testMountainStanceSpeedPenalty()`
- **Setup:** Monk with base Speed 25 activates Mountain Stance; inspect speed
- **Expected:** speed = 20 (–5 ft); restored to 25 when stance ends
- **Roles:** authenticated player

### TC-MNK-19 — Mountain Stance: requires touching ground
- **AC:** `[NEW]` Mountain Stance: requires touching ground
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkMountainStanceTest::testMountainStanceRequiresTouchingGround()`
- **Setup:** Monk in airborne/flying state; attempt to activate Mountain Stance
- **Expected:** Mountain Stance activation blocked; error references ground requirement
- **Roles:** authenticated player
- **Note to PM:** Airborne/flying state requires a movement/terrain system. If not yet in scope, this TC should be marked `pending-dev-confirmation` at Stage 0 until movement states are implemented.

### TC-MNK-20 — Mountain Stance AC item bonus stacks with armor potency runes on mage armor / explorer's clothing
- **AC:** `[NEW]` Item AC stacks with armor potency runes on mage armor / explorer's clothing
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkMountainStanceTest::testMountainStanceStacksWithPotencyRune()`
- **Setup:** Monk in Mountain Stance with explorer's clothing that has a +1 potency rune; inspect total AC item bonus
- **Expected:** AC item bonus = +4 (Mountain Stance) + +1 (potency rune) = +5; stacking confirmed
- **Roles:** authenticated player
- **Note to PM:** This TC depends on `dc-cr-equipment-system` having potency rune support. Flag `pending-dev-confirmation` at Stage 0 if equipment system lacks rune support.

### TC-MNK-21 — Flurry of Blows: both strikes attempted even if second is under MAP
- **AC:** `[NEW]` Flurry of Blows when only one unarmed attack remains MAP-viable: both strikes still attempted; second may have MAP penalty
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkCombatTest::testFlurryBothStrikesAttemptedUnderMap()`
- **Setup:** Monk who already has a MAP penalty (from prior action); use Flurry of Blows; inspect that both strikes are attempted (not blocked by MAP)
- **Expected:** Both Flurry strikes always attempted; second strike rolls with existing MAP + –5; neither strike is skipped due to MAP state
- **Roles:** authenticated player

### TC-MNK-22 — Stunning Fist: requires Flurry of Blows feat; Fortitude vs class DC only when BOTH hits land
- **AC:** `[NEW]` Stunning Fist: Fortitude vs class DC check only when BOTH flurry strikes hit same creature
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkCombatTest::testStunningFistTriggerCondition()`
- **Setup:** Monk with Stunning Fist feat; use Flurry on same creature with both hits landing; use Flurry with only one hit landing; inspect Fortitude check trigger in each case
- **Expected:** Both hits: Fortitude check triggered; only one hit: Fortitude check NOT triggered
- **Roles:** authenticated player

### TC-MNK-23 — Stunning Fist: incapacitation rules applied correctly vs higher-level creatures
- **AC:** `[NEW]` Incapacitation rules applied correctly vs higher-level creatures
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkCombatTest::testStunningFistIncapacitationRule()`
- **Setup:** Monk at level 5 uses Stunning Fist on a level-7 creature; inspect degree of success outcome adjustment
- **Expected:** Success treated as critical success; failure treated as success for creature of higher level than caster (incapacitation rule shifts one step worse for Monk)
- **Roles:** authenticated player
- **Note to PM:** Incapacitation rule direction: "higher level than spell/ability" means the creature's saves are one step better (not worse). TC should verify the system correctly penalizes the Monk (not the creature) when creature level exceeds Monk level.

### TC-MNK-24 — Armor equip blocked for Monk (must stay unarmored)
- **AC:** `[TEST-ONLY]` Armor equip blocked: monk cannot wear non-explorer's-clothing armor without explicitly training via feat
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testArmorEquipBlockedByDefault()`
- **Setup:** Monk without armor training feat; attempt to equip light armor (e.g., leather)
- **Expected:** Equip blocked or warning issued; unarmored_only constraint enforced; monk remains unarmored
- **Roles:** authenticated player

### TC-MNK-25 — Explorer's clothing is equippable without feat
- **AC:** `[TEST-ONLY]` Armor equip blocked except explorer's clothing (implied by AC)
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testExplorersClothingAllowed()`
- **Setup:** Monk without armor training feat; equip explorer's clothing
- **Expected:** Explorer's clothing equipped without restriction (it is the "no-armor" baseline)
- **Roles:** authenticated player

### TC-MNK-26 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated features do not appear before required level
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkLevelFeaturesTest::testNoEarlyLevelFeatures()`
- **Setup:** Monk at level 1; inspect for general feat slot, ability boost
- **Expected:** General feat not present at level 1 (first at level 3); ability boost not present at level 1 (first at level 5)
- **Roles:** authenticated player

### TC-MNK-27 — Fuse Stance (level 20): grants all effects of both selected stances simultaneously
- **AC:** `[NEW]` Fuse Stance (level 20): correctly grants all effects of both selected stances simultaneously
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkStanceTest::testFuseStanceGrantsBothEffects()`
- **Setup:** Monk at level 20 with Fuse Stance feat; activate Fuse Stance with two selected stances; inspect active effects
- **Expected:** Both stance unarmed attack profiles available; all bonuses from both stances applied (AC bonuses, speed penalties, etc.); no conflict error
- **Roles:** authenticated player
- **Note to PM:** Fuse Stance is a level-20 feat. If conflicting stance effects exist (e.g., two –5 ft Speed penalties), the system needs to define how to resolve them. TC verifies both effects are granted; conflict resolution logic needs AC clarification.

### TC-MNK-28 — Class feat schedule: level 1 and every even level
- **AC:** `[NEW]` Class feat at level 1 and every even level (2, 4, 6 … 20)
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkFeatProgressionTest::testClassFeatSchedule()`
- **Setup:** Level Monk 1 → 4; inspect class_feats_available at each level
- **Expected:** Class feat slots at levels 1, 2, 4; not at level 3
- **Roles:** authenticated player

### TC-MNK-29 — General/skill/ancestry feat schedule and ability boosts
- **AC:** `[NEW]` General feats at 3, 7, 11, 15, 19; skill feats every even level; ancestry feats at 5, 9, 13, 17; ability boosts at 5, 10, 15, 20
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkFeatProgressionTest::testFeatAndBoostSchedule()`
- **Setup:** Level Monk 1 → 5; inspect general_feats at level 3; skill_feats at level 2; ancestry_feats at level 5; ability_boosts at level 5
- **Expected:** All four feature slots present at their first qualifying level; none present one level early
- **Roles:** authenticated player

### TC-MNK-30 — Player cannot modify another player's Monk character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `MonkClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Monk owned by user A; attempt stance change or Ki spell use as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-MNK-31 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Unarmed attack profile per stance (exact die/traits) | TC-MNK-12 verifies that a stance provides a unique profile; the exact die/traits per specific stance (Tiger, Wolf, Crane, etc.) require a per-stance catalog to be parametrized |
| Mountain Stance ground requirement (airborne state) | TC-MNK-19 verifies the block exists; "touching ground" requires movement/terrain/condition states to detect airborne — flag pending-dev-confirmation until movement states ship |
| Incapacitation rule direction and step adjustment | TC-MNK-23 verifies the rule applies; the exact degree-of-success shift direction (creature's saves improved vs Monk's effect degraded) should be clarified in AC to prevent implementation ambiguity |
| Fuse Stance conflict resolution for overlapping penalties | TC-MNK-27 verifies both effects are granted; if two stances have the same penalty type (e.g., both reduce Speed), the stacking/override rule needs PM enumeration |
| Stunning Fist stun duration and condition details | TC-MNK-22/23 verify the trigger conditions; the stun condition's exact duration and behavior requires the `dc-cr-conditions` system |

---

## Conditional dependency summary

| TC | Dependency | Status |
|---|---|---|
| TC-MNK-19 | Airborne/flying movement state | Movement system — not yet scoped |
| TC-MNK-20 | Potency rune item bonus stacking | `dc-cr-equipment-system` in-progress Release B |
| TC-MNK-22/23 | Stun condition application | `dc-cr-conditions` in-progress Release B |

**All other TCs (28 of 31) have no deferred dependencies and can activate at Stage 0 immediately.**

---

## Regression risk areas

1. **Fist damage 1d6 not 1d4**: Monk unarmed fist is an exception to the standard 1d4 unarmed baseline; risk that the unarmed attack initializer applies 1d4 universally and fails to check class.
2. **Expert unarmored + Untrained armor simultaneously**: Monk has Expert in unarmored but Untrained in all armor categories — dual-state initialization; risk of the armor proficiency loop setting unarmored to Trained (default) instead of Expert.
3. **Flurry of Blows MAP count**: Flurry is 1 action but counts as 2 attacks for MAP; risk that the MAP counter only increments by 1 (one action cost) rather than 2 (two strikes).
4. **Flurry once-per-turn reset**: once-per-turn limit must reset at start of next turn; risk of limit flag persisting across turns.
5. **Ki spell focus pool initialization**: Monk starts with 1 FP without any Ki feat; risk that pool defaults to 0 if the initialization assumes feats always grant the first FP.
6. **Mountain Stance DEX cap 0 with external bonuses**: if an item or condition grants a DEX-to-AC bonus, the cap must apply to the net result, not just the base DEX mod — risk of cap being applied only to the raw attribute.
7. **Mountain Stance AC item bonus type**: the +4 is an item bonus (not circumstance or status); risk of mis-categorization causing incorrect stacking behavior with potency runes.
8. **Stance shared infrastructure with Fighter**: if Fighter stances and Monk stances share implementation, Monk-specific unarmed attack profile injection (die type, traits) must be isolated from Fighter's weapon-based stance effects.
9. **Stunning Fist trigger precision**: trigger requires both hits to land on the same creature in the same Flurry action; risk of triggering if one hit lands on creature A and one on creature B (split targeting).

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-class-monk

## Gap analysis reference
- DB sections: core/ch03/Monk (REQs 1256–1323)
- Track B: no existing MonkService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Monk exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Monk HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Untrained all armor, Expert unarmored defense.
- [ ] `[NEW]` Monk fist base damage = 1d6 (not 1d4); no lethal/nonlethal penalty on unarmed attacks.

### Flurry of Blows (Level 1)
- [ ] `[NEW]` Flurry of Blows is a 1-action ability that makes two unarmed Strikes; usable once per turn.
- [ ] `[NEW]` MAP increases normally after Flurry of Blows (both attacks count).

### Ki Spells & Focus Pool
- [ ] `[NEW]` Ki spells are focus spells; focus pool starts at 1 Focus Point (max 3 with feats); Wisdom is spellcasting ability.
- [ ] `[NEW]` Ki spell feats (Ki Rush, Ki Strike, etc.) each grant +1 Focus Point when taken.

### Stance Unarmed Attacks
- [ ] `[NEW]` Each stance feat provides unique unarmed attack profiles (damage die, traits) that replace or supplement base unarmed attacks while the stance is active.
- [ ] `[NEW]` Stance restriction: only one stance active at a time; entering a new stance ends the previous.
- [ ] `[NEW]` Mountain Stance: +4 item bonus to AC; +2 circumstance vs Shove/Trip; DEX cap to AC = +0; –5 ft Speed; requires touching ground. Item AC stacks with armor potency runes on mage armor / explorer's clothing.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every even level; ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Flurry of Blows when only one unarmed attack remains MAP-viable: both strikes still attempted; second may have MAP penalty.
- [ ] `[NEW]` Stunning Fist (requires Flurry of Blows): Fortitude vs class DC check only when BOTH flurry strikes hit same creature; incapacitation rules applied correctly vs higher-level creatures.
- [ ] `[NEW]` Ki spells require active focus points; casting with 0 FP is blocked.
- [ ] `[NEW]` Mountain Stance DEX cap of +0: correctly overrides character DEX bonus to AC even with bonuses from other sources.
- [ ] `[NEW]` Fuse Stance (level 20): correctly grants all effects of both selected stances simultaneously.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Armor equip blocked: monk cannot wear non-explorer's-clothing armor without explicitly training via feat.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Flurry of Blows usable only once per turn (second use blocked).
- [ ] `[TEST-ONLY]` Two stances simultaneously: not permitted (without Fuse Stance feat).

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
