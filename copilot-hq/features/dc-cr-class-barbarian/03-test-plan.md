# Test Plan: dc-cr-class-barbarian

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Barbarian Class — identity/stats, instinct selection, Rage action, level-gated features, feat progression, key trait enforcement
**AC source:** `features/dc-cr-class-barbarian/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Barbarian class logic; first barbarian implementation.
- KB ref: `dc-cr-character-class` test plan pattern (`features/dc-cr-character-class/03-test-plan.md`) — same suite/runner structure applies.
- Dependency note: `dc-cr-conditions` is in-progress Release B. Deny Advantage (flat-footed immunity), Rage AC penalty, and Giant Instinct clumsy 1 all depend on the conditions system. Flag at Stage 0 activation; TCs that require conditions may need `pending-dev-confirmation` status until `dc-cr-conditions` ships.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class selection, HP/proficiency, instinct, Rage activation/state/cooldown, level-gated features, trait enforcement, edge cases |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, QA audit regression |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. All Barbarian logic is service/entity layer. No Playwright suite needed at this scope.

---

## Test cases

### TC-BAR-01 — Barbarian exists as selectable class with STR key ability
- **AC:** `[NEW]` Barbarian selectable in character creation; Strength as key ability boost at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianClassTest::testBarbarianExistsWithStrKeyAbility()`
- **Setup:** Query `character_class` nodes; load barbarian; inspect key_ability field
- **Expected:** Barbarian class node exists; `key_ability = strength`; included in selectable class list
- **Roles:** authenticated player

### TC-BAR-02 — Barbarian HP calculation: 12 + CON modifier per level
- **AC:** `[NEW]` Barbarian HP = 12 + CON modifier per level
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianClassTest::testHpCalculation()`
- **Setup:** Create Barbarian with CON 14 (mod +2); inspect HP at level 1 and level 2
- **Expected:** Level 1 HP = 14; Level 2 HP = 28
- **Roles:** authenticated player

### TC-BAR-03 — Barbarian initial proficiencies applied correctly
- **AC:** `[NEW]` Expert Perception; Expert Fortitude and Will, Trained Reflex; Trained simple/martial weapons; Trained light/medium armor; 3 + INT modifier skills; Athletics fixed as trained
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianClassTest::testInitialProficiencies()`
- **Setup:** Create Barbarian with INT 10 (mod 0); inspect proficiency fields
- **Expected:** Perception=Expert; Fortitude=Expert; Will=Expert; Reflex=Trained; simple_weapons=Trained; martial_weapons=Trained; light_armor=Trained; medium_armor=Trained; skill_count = 3; Athletics in trained_skills
- **Roles:** authenticated player

### TC-BAR-04 — Instinct selection: all 5 valid values accepted
- **AC:** `[NEW]` At level 1, player selects one Instinct: Animal, Dragon, Fury, Giant, or Spirit
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testAllValidInstinctsAccepted()`
- **Setup:** Create Barbarian; submit instinct = each of the 5 valid values
- **Expected:** Each accepted and stored on character entity
- **Roles:** authenticated player

### TC-BAR-05 — Invalid instinct selection rejected
- **AC:** `[TEST-ONLY]` Invalid instinct selection (not one of the 5) is rejected
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testInvalidInstinctRejected()`
- **Setup:** Submit instinct = "wizard" or "invalid_value"
- **Expected:** Validation error returned; instinct not set
- **Roles:** authenticated player

### TC-BAR-06 — Anathema stored and instinct abilities removed on violation
- **AC:** `[NEW]` Each instinct has an anathema behavioral restriction stored; violating removes instinct-dependent abilities until 1-day downtime re-centering
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testAnathemaViolationRemovesInstinctAbilities()`
- **Setup:** Character with Animal instinct; trigger anathema violation event; inspect available instinct abilities
- **Expected:** Instinct abilities (animal unarmed attacks, etc.) removed from character; re-centering downtime re-enables them
- **Roles:** authenticated player

### TC-BAR-07 — Animal Instinct: correct unarmed attacks granted during Rage
- **AC:** `[NEW]` Animal Instinct grants animal unarmed attacks during Rage (Ape, Bear, Bull, Cat, Deer, Frog, Shark, Snake, Wolf — correct damage die, type, traits); Rage gains morph/primal/transmutation traits
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testAnimalInstinctGrantsAnimalAttacks()`
- **Setup:** Barbarian with Animal instinct (each animal type); activate Rage; inspect available unarmed attacks and Rage trait list
- **Expected:** Animal-appropriate unarmed attack available; damage die, type, and traits match PF2E spec; Rage traits include morph, primal, transmutation
- **Roles:** authenticated player

### TC-BAR-08 — Dragon Instinct: Draconic Rage increases damage and changes type; traits updated
- **AC:** `[NEW]` Dragon Instinct: player selects dragon type; Draconic Rage damage 2→4, type changes to breath weapon type; Rage gains arcane/evocation/elemental traits
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testDragonInstinctDraconicRage()`
- **Setup:** Barbarian with Dragon (fire dragon) instinct; activate Rage; inspect damage bonus and type; inspect Rage traits
- **Expected:** Rage damage bonus = 4 (not 2); damage type = fire; Rage traits include arcane, evocation, elemental
- **Roles:** authenticated player

### TC-BAR-09 — Dragon Instinct: damage type sourced from selected dragon type
- **AC:** `[NEW]` Draconic Rage damage type correctly sourced from selected dragon type
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testDragonInstinctDamageTypeFromDragonType()`
- **Setup:** Barbarian with Dragon (cold dragon) instinct; activate Rage; inspect damage type
- **Expected:** Damage type = cold (not fire or other)
- **Roles:** authenticated player

### TC-BAR-10 — Fury Instinct: no anathema; grants additional 1st-level barbarian feat
- **AC:** `[NEW]` Fury Instinct: no anathema; grants one additional 1st-level barbarian feat
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testFuryInstinctGrantsBonusFeat()`
- **Setup:** Barbarian with Fury instinct; inspect anathema field and available class feat slots at level 1
- **Expected:** anathema = null/none; class feat count at level 1 = 2 (standard 1 + Fury bonus 1)
- **Roles:** authenticated player

### TC-BAR-11 — Giant Instinct: oversized weapons allowed; Rage damage 2→6; clumsy 1 applied
- **AC:** `[NEW]` Giant Instinct: wield oversized weapons (one size larger, normal Price/Bulk); Rage damage 2→6 with oversized weapon; clumsy 1 (cannot be removed)
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testGiantInstinctOversizedWeaponAndRage()`
- **Setup:** Barbarian with Giant instinct; equip oversized weapon; activate Rage; inspect damage bonus and conditions
- **Expected:** Oversized weapon equippable (no error); Rage damage bonus = 6; clumsy 1 condition applied; clumsy 1 removal attempt fails
- **Roles:** authenticated player

### TC-BAR-12 — Giant Instinct: clumsy 1 cannot be removed while raging with oversized weapon
- **AC:** `[NEW]` Giant Instinct clumsy 1 cannot be removed by any means while raging with oversized weapon
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testGiantInstinctClumsy1CannotBeRemoved()`
- **Setup:** Giant Instinct Barbarian raging with oversized weapon; attempt to remove clumsy 1 via any available condition-removal mechanism
- **Expected:** Removal blocked; clumsy 1 persists until Rage ends or weapon is non-oversized
- **Roles:** authenticated player
- **Dependency note:** Requires `dc-cr-conditions` to be implemented. Mark `pending-dev-confirmation` at Stage 0 if not yet shipped.

### TC-BAR-13 — Spirit Instinct: Rage damage changes to negative/positive; ghost touch rune; traits updated
- **AC:** `[NEW]` Spirit Instinct: Rage damage type changes to negative or positive (chosen each Rage); weapon acts as ghost touch property rune while raging; Rage gains divine/necromancy traits
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianInstinctTest::testSpiritInstinctRageEffects()`
- **Setup:** Barbarian with Spirit instinct; activate Rage choosing negative; activate Rage choosing positive; inspect damage type and weapon properties; inspect Rage traits
- **Expected:** Damage type matches chosen (negative or positive); weapon has ghost touch property rune active; Rage traits include divine, necromancy
- **Roles:** authenticated player

### TC-BAR-14 — Rage activation: temp HP = level + CON modifier
- **AC:** `[NEW]` On Rage activation: grant temp HP = level + CON modifier
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageGrantsTempHp()`
- **Setup:** Level-3 Barbarian with CON 16 (mod +3); activate Rage; inspect temp_hp
- **Expected:** temp_hp = 6 (3 + 3)
- **Roles:** authenticated player

### TC-BAR-15 — Rage: +2 damage bonus on melee Strikes; halved for agile weapons
- **AC:** `[NEW]` While raging: +2 damage on melee Strikes (halved for agile weapons/unarmed attacks)
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageDamageBonus()`
- **Setup:** Barbarian raging; inspect damage modifier for a non-agile melee weapon and for an agile weapon
- **Expected:** Non-agile melee Strike: rage damage bonus = +2; agile weapon/unarmed: rage damage bonus = +1
- **Roles:** authenticated player

### TC-BAR-16 — Rage: –1 AC penalty applied and removed on Rage end
- **AC:** `[NEW]` While raging: –1 AC penalty applied; removed when Rage ends
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageAcPenalty()`
- **Setup:** Note AC baseline; activate Rage; verify AC reduced by 1; end Rage; verify AC restored
- **Expected:** AC = baseline - 1 during Rage; AC = baseline after Rage
- **Roles:** authenticated player
- **Dependency note:** Requires `dc-cr-conditions` or AC modifier system. Mark `pending-dev-confirmation` at Stage 0 if not yet shipped.

### TC-BAR-17 — Rage: concentrate-trait actions blocked (except rage-trait or Seek)
- **AC:** `[NEW]` While raging: concentrate-trait actions blocked EXCEPT those with rage trait; Seek explicitly permitted
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageBlocksConcentrateActions()`
- **Setup:** Barbarian raging; attempt a concentrate-trait action (e.g., Cast a Spell); attempt Seek; attempt a rage-trait action
- **Expected:** Concentrate action blocked with error; Seek succeeds; rage-trait action succeeds
- **Roles:** authenticated player

### TC-BAR-18 — Rage: cannot be voluntarily ended early
- **AC:** `[NEW]` Rage cannot be voluntarily ended early by the player
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageCannotBeVoluntarilyEnded()`
- **Setup:** Barbarian raging; attempt to end Rage via player action
- **Expected:** Error returned; Rage remains active
- **Roles:** authenticated player

### TC-BAR-19 — Rage: ends when no perceived enemies or unconscious
- **AC:** `[NEW]` Rage duration ends early if no perceived enemies or if unconscious
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageEndsOnNoEnemiesOrUnconscious()`
- **Setup:** Barbarian raging; trigger "no perceived enemies" condition; verify Rage ends; separately trigger unconscious condition; verify Rage ends
- **Expected:** Rage ends in both cases; temp HP removed; cooldown starts
- **Roles:** authenticated player

### TC-BAR-20 — Rage: temp HP disappears on Rage end; cooldown begins
- **AC:** `[TEST-ONLY]` Temp HP from Rage disappears on rage end (does not persist as regular HP); 1-minute cooldown before Rage can be used again
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageEndTempHpAndCooldown()`
- **Setup:** Barbarian with active Rage and temp HP; end Rage; inspect temp_hp and rage_cooldown
- **Expected:** temp_hp = 0 (not converted to regular HP); rage_on_cooldown = true
- **Roles:** authenticated player

### TC-BAR-21 — Rage cooldown enforced: cannot Rage again within 1 minute
- **AC:** `[TEST-ONLY]` Rage cooldown enforced: cannot Rage again within 1 minute (without Quick Rage at level 17)
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageCooldownEnforced()`
- **Setup:** End Rage; immediately attempt to activate Rage again (within 1 minute / before cooldown expires)
- **Expected:** Rage activation blocked; error references cooldown
- **Roles:** authenticated player

### TC-BAR-22 — Rage activation while already raging: blocked
- **AC:** `[NEW]` Rage activation while already raging: blocked
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianRageTest::testRageActivationBlockedWhileRaging()`
- **Setup:** Activate Rage; attempt to activate Rage again
- **Expected:** Error returned; second Rage activation blocked
- **Roles:** authenticated player

### TC-BAR-23 — Level 3: Deny Advantage — flat-footed immunity from same/lower-level creatures
- **AC:** `[NEW]` Level 3: Deny Advantage — immune to flat-footed from hidden/undetected/flanking/surprise from creatures of same or lower level
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testDenyAdvantageAtLevel3()`
- **Setup:** Barbarian at level 3; apply flat-footed condition via flanking from same-level creature; inspect condition state
- **Expected:** flat-footed not applied; same-level creature can still grant flanking to its allies (Deny Advantage only blocks the barbarian's flat-footed)
- **Roles:** authenticated player
- **Dependency note:** Requires `dc-cr-conditions`. Mark `pending-dev-confirmation` at Stage 0 if not yet shipped.

### TC-BAR-24 — Level 5: Brutality — weapon proficiency increases to Master; crit specialization while raging
- **AC:** `[NEW]` Level 5: Brutality — simple/martial/unarmed proficiency increases to Master; critical specialization applies while raging
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testBrutalityAtLevel5()`
- **Setup:** Barbarian at level 5; inspect weapon proficiency; activate Rage; perform critical hit; inspect specialization effect
- **Expected:** Weapon proficiency = Master; critical specialization effect applied on critical hit while raging; not applied when not raging
- **Roles:** authenticated player

### TC-BAR-25 — Level 7: Juggernaut — Fortitude to Master; successes become critical successes
- **AC:** `[NEW]` Level 7: Juggernaut — Fortitude save proficiency increases to Master; successes become critical successes
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testJuggernautAtLevel7()`
- **Setup:** Barbarian at level 7; inspect Fortitude proficiency; simulate Fortitude success; inspect result
- **Expected:** Fortitude proficiency = Master; Fortitude success result = critical success
- **Roles:** authenticated player

### TC-BAR-26 — Level 7: Weapon Specialization — damage bonus by proficiency tier
- **AC:** `[NEW]` Level 7: Weapon Specialization — +2 (Expert), +3 (Master), +4 (Legendary) damage; instinct specialization ability unlocked
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testWeaponSpecializationAtLevel7()`
- **Setup:** Barbarian at level 7 with Expert-proficiency weapon; inspect weapon damage modifier; inspect instinct specialization availability
- **Expected:** Damage bonus = +2 for Expert weapon; instinct specialization ability available
- **Roles:** authenticated player

### TC-BAR-27 — Level 9: Raging Resistance — resistance type per instinct; value = 3 + CON modifier
- **AC:** `[NEW]` Level 9: Raging Resistance — while raging, resistance = 3 + CON modifier; type per instinct
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testRagingResistanceAtLevel9()`
- **Setup:** Barbarian at level 9 with each instinct (CON 14, mod +2); activate Rage; inspect damage resistance
- **Expected:** Resistance value = 5 (3+2); type matches instinct spec (Animal: piercing+slashing; Dragon: piercing+breath type; Fury: bludgeoning+player-chosen elemental; Giant: physical; Spirit: negative+undead)
- **Roles:** authenticated player

### TC-BAR-28 — Level 11: Mighty Rage — class DC to Expert; free-action on Rage activation
- **AC:** `[NEW]` Level 11: Mighty Rage — class DC proficiency to Expert; Mighty Rage free-action triggers on Rage activation
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testMightyRageAtLevel11()`
- **Setup:** Barbarian at level 11; inspect class DC proficiency; activate Rage; verify Mighty Rage free-action is available; use it to immediately perform a rage-trait action
- **Expected:** class DC proficiency = Expert; Mighty Rage free-action available on Rage activation; rage-trait action executed without spending an additional action
- **Roles:** authenticated player

### TC-BAR-29 — Level 11: Mighty Rage free-action only available on Rage activation window
- **AC:** `[NEW]` Mighty Rage free-action: only available as trigger on Rage activation; cannot be used outside that window
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testMightyRageFreeActionWindowEnforced()`
- **Setup:** Barbarian at level 11 already raging; attempt to use Mighty Rage free-action mid-combat (not at Rage activation moment)
- **Expected:** Mighty Rage free-action blocked; error indicates it is only available at Rage activation
- **Roles:** authenticated player

### TC-BAR-30 — Level 13: Greater Juggernaut — Fortitude to Legendary; critical failures become failures; failure damage halved
- **AC:** `[NEW]` Level 13: Greater Juggernaut — Fortitude to Legendary; critical failures become failures; failures against damage effects halve damage
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testGreaterJuggernautAtLevel13()`
- **Setup:** Barbarian at level 13; inspect Fortitude proficiency; simulate Fortitude critical failure; simulate Fortitude failure against damage effect
- **Expected:** Fortitude proficiency = Legendary; critical failure result = failure; failure damage = half of original damage value
- **Roles:** authenticated player

### TC-BAR-31 — Level 15: Greater Weapon Specialization — damage bonus updated by proficiency tier
- **AC:** `[NEW]` Level 15: Greater Weapon Specialization — +4 (Expert) / +6 (Master) / +8 (Legendary)
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testGreaterWeaponSpecializationAtLevel15()`
- **Setup:** Barbarian at level 15 with Master-proficiency weapon; inspect weapon damage modifier
- **Expected:** Damage bonus = +6 for Master weapon; Expert weapon = +4; Legendary weapon = +8
- **Roles:** authenticated player

### TC-BAR-32 — Level 17: Quick Rage — removes 1-minute cooldown
- **AC:** `[NEW]` Level 17: Quick Rage — removes 1-minute Rage cooldown; after one full turn without raging, Barbarian can Rage again
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testQuickRageAtLevel17()`
- **Setup:** Barbarian at level 17; end Rage; advance one full turn (without raging); attempt to activate Rage immediately
- **Expected:** Rage activation succeeds; no cooldown error
- **Roles:** authenticated player

### TC-BAR-33 — Level 19: Devastator — Strikes ignore 10 points of physical resistance
- **AC:** `[NEW]` Level 19: Devastator — class DC to Master; melee Strikes ignore 10 points of resistance to physical damage types
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testDevastatorAtLevel19()`
- **Setup:** Barbarian at level 19; inspect class DC proficiency; apply Strike against target with 15 physical resistance; inspect final damage
- **Expected:** class DC proficiency = Master; effective resistance = 5 (15 - 10 ignored); damage = expected minus 5
- **Roles:** authenticated player

### TC-BAR-34 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated features do not appear before required level
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianLevelFeaturesTest::testLevelGatedFeaturesNotGrantedEarly()`
- **Setup:** Barbarian at level 2 (below all gates); inspect Deny Advantage, Brutality, Juggernaut, Raging Resistance, Mighty Rage availability
- **Expected:** None of the level-gated features present; Rage and instinct features present
- **Roles:** authenticated player

### TC-BAR-35 — Class feat schedule: level 1 and every even level
- **AC:** `[NEW]` Barbarian gains class feat at level 1 and every even-numbered level (2, 4, 6 … 20)
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianFeatProgressionTest::testClassFeatSchedule()`
- **Setup:** Level Barbarian from 1 to 4; inspect class_feats_available at each level
- **Expected:** Class feat slots at levels 1, 2, 4; not at levels 3
- **Roles:** authenticated player

### TC-BAR-36 — General feats at levels 3, 7, 11, 15, 19
- **AC:** `[NEW]` General feats at levels 3, 7, 11, 15, 19
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianFeatProgressionTest::testGeneralFeatSchedule()`
- **Setup:** Level Barbarian to 3; inspect general_feats_available; level to 4; verify count unchanged
- **Expected:** General feat slot at level 3; not at level 4
- **Roles:** authenticated player

### TC-BAR-37 — Ancestry feats at levels 5, 9, 13, 17
- **AC:** `[NEW]` Ancestry feats at levels 5, 9, 13, 17
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianFeatProgressionTest::testAncestryFeatSchedule()`
- **Setup:** Level Barbarian to 5; inspect ancestry_feats_available; check level 4 has none
- **Expected:** Ancestry feat slot granted at level 5; not at levels 4 or 6
- **Roles:** authenticated player

### TC-BAR-38 — Flourish trait: only one flourish action per turn
- **AC:** `[NEW]` Flourish trait: only one flourish action per turn enforced
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianTraitRulesTest::testFlourishLimitPerTurn()`
- **Setup:** Barbarian with multiple flourish actions available; use one flourish action; attempt second flourish action on same turn
- **Expected:** First flourish succeeds; second flourish blocked with turn-limit error
- **Roles:** authenticated player

### TC-BAR-39 — Open trait: only usable as first action of turn, before any attack/open action
- **AC:** `[NEW]` Open trait: only usable as first action of a turn before any attack or open action
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianTraitRulesTest::testOpenActionOnlyAsFirstAction()`
- **Setup:** Barbarian; attempt open-trait action as first action of turn (success); attempt open-trait action after an attack action (failure)
- **Expected:** First-action open: succeeds; post-attack open: blocked with error
- **Roles:** authenticated player

### TC-BAR-40 — Rage trait: requires active Rage; ability ends if Rage ends
- **AC:** `[NEW]` Rage trait on abilities: requires active Rage; ability ends if Rage ends
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianTraitRulesTest::testRageTraitRequiresActiveRage()`
- **Setup:** Barbarian with a rage-trait ability; attempt use while not raging; activate Rage; attempt use while raging (success); trigger Rage end mid-ability
- **Expected:** Non-raging use blocked; raging use succeeds; ability ends when Rage ends
- **Roles:** authenticated player

### TC-BAR-41 — Player cannot modify another player's Barbarian character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `BarbarianClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Barbarian owned by user A; attempt Rage activation or instinct change as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-BAR-42 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Rage traits vary by instinct (morph/primal for Animal, arcane/evocation/elemental for Dragon, divine/necromancy for Spirit) — full trait list correctness | Automation (TC-BAR-07/08/13) checks for key named traits; full canonical trait set completeness requires manual editorial review against PF2E source |
| Animal Instinct: all 9 animal attack forms have correct damage die, type, and traits | TC-BAR-07 tests the mechanism exists; verifying all 9 animal attack stat blocks are exactly correct requires review against PF2E Core Rulebook |
| Instinct specialization ability at level 7 (instinct-specific bonus) | The exact instinct specialization bonus for each instinct is not enumerated in the AC; TC-BAR-26 can only verify the ability is unlocked, not its values, until they are specified |
| Fury Instinct: secondary type choice (cold/electricity/fire) for Raging Resistance | Player-chosen at Fury instinct selection; automation verifies the choice is accepted and stored; validating all 3 elemental type combinations requires parameterized test runs |

---

## Regression risk areas

1. **`dc-cr-conditions` dependency**: Deny Advantage (flat-footed), AC penalty, clumsy 1, Giant Instinct oversized weapon use — all depend on the conditions system. TCs BAR-12, BAR-16, BAR-23 should be marked `pending-dev-confirmation` at Stage 0 activation until `dc-cr-conditions` ships.
2. **`dc-cr-character-class` overlap**: Barbarian class node seeded by `dc-cr-character-class`; verify instinct data binds correctly to that node and does not break class seeding.
3. **HP calculation overlap**: Barbarian HP = 12 + CON mod is the highest base HP among PF2E classes; verify this does not conflict with ancestry HP bonus additions or double-count any modifier.
4. **Rage state machine integrity**: multiple overlapping state changes (activate, end, cooldown, Quick Rage) must not leave character in an inconsistent state (raging with no temp HP, or cooldown while still raging).
5. **Mighty Rage 2-action interaction**: AC mentions "2-action Rage" for Mighty Rage — this is an edge case where the Barbarian uses a 2-action variant of Rage; verify the free-action window still triggers correctly.
6. **QA audit regression**: no new routes per security AC; if any are added, they must be probed in qa-permissions.json before release.
