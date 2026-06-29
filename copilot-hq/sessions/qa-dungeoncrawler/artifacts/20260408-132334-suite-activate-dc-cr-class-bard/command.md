# Suite Activation: dc-cr-class-bard

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-class-bard"`**  
   This links the test to the living requirements doc at `features/dc-cr-class-bard/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-class-bard-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-class-bard",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-class-bard"`**  
   Example:
   ```json
   {
     "id": "dc-cr-class-bard-<route-slug>",
     "feature_id": "dc-cr-class-bard",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-class-bard",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-class-bard

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Bard Class — identity/stats, Muse selection, occult spellcasting (repertoire/cantrips/signature spells), composition spells/focus pool, level-gated features, spell slots, metamagic
**AC source:** `features/dc-cr-class-bard/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Bard class logic; first bard implementation.
- KB ref: `dc-cr-character-class` test plan pattern (`features/dc-cr-character-class/03-test-plan.md`) — same suite/runner structure applies.
- Dependency note: `dc-cr-spellcasting` is deferred; `dc-cr-focus-spells` is deferred. All spellcasting and focus pool TCs must be marked `pending-dev-confirmation` at Stage 0 activation until those features ship. Identity, Muse selection, and proficiency TCs can activate independently.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class selection, HP/proficiency, Muse, spellcasting, compositions, focus pool, level-gated features, spell slot enforcement |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, QA audit regression |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. All Bard logic is service/entity layer. No Playwright suite needed at this scope (extend if a composition UI is built).

---

## Test cases

### TC-BRD-01 — Bard exists as selectable class with CHA key ability
- **AC:** `[NEW]` Bard selectable in character creation; Charisma as key ability boost at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `BardClassTest::testBardExistsWithChaKeyAbility()`
- **Setup:** Query `character_class` nodes; load bard; inspect key_ability field
- **Expected:** Bard class node exists; `key_ability = charisma`; included in selectable class list
- **Roles:** authenticated player

### TC-BRD-02 — Bard HP calculation: 8 + CON modifier per level
- **AC:** `[NEW]` Bard HP = 8 + CON modifier per level
- **Suite:** `module-test-suite`
- **Test class/method:** `BardClassTest::testHpCalculation()`
- **Setup:** Create Bard with CON 12 (mod +1); inspect HP at level 1 and level 2
- **Expected:** Level 1 HP = 9; Level 2 HP = 18
- **Roles:** authenticated player

### TC-BRD-03 — Bard initial proficiencies applied correctly
- **AC:** `[NEW]` Expert Perception; Expert Will, Trained Fortitude/Reflex; Trained Occultism+Performance; 4+INT skills; Trained specific weapons; Trained light armor; Trained occult spell attacks/DCs
- **Suite:** `module-test-suite`
- **Test class/method:** `BardClassTest::testInitialProficiencies()`
- **Setup:** Create Bard with INT 10 (mod 0); inspect all proficiency fields
- **Expected:** Perception=Expert; Will=Expert; Fortitude=Trained; Reflex=Trained; occultism=Trained; performance=Trained; skill_count=4; simple_weapons=Trained; longsword/rapier/sap/shortbow/shortsword/whip=Trained; light_armor=Trained; occult_spell_attack=Trained; occult_spell_dc=Trained
- **Roles:** authenticated player

### TC-BRD-04 — Muse selection: all 3 valid values accepted
- **AC:** `[NEW]` At level 1, player selects one Muse: Enigma, Maestro, or Polymath
- **Suite:** `module-test-suite`
- **Test class/method:** `BardMuseTest::testAllValidMusesAccepted()`
- **Setup:** Create Bard; submit muse = each of the 3 valid values
- **Expected:** Each accepted; stored on character entity
- **Roles:** authenticated player

### TC-BRD-05 — Invalid Muse selection rejected
- **AC:** `[TEST-ONLY]` Invalid Muse selection rejected
- **Suite:** `module-test-suite`
- **Test class/method:** `BardMuseTest::testInvalidMuseRejected()`
- **Setup:** Submit muse = "fighter" or "invalid_value"
- **Expected:** Validation error returned; muse not set
- **Roles:** authenticated player

### TC-BRD-06 — Enigma Muse: Bardic Lore feat + true strike added to repertoire
- **AC:** `[NEW]` Enigma → Bardic Lore feat + true strike spell
- **Suite:** `module-test-suite`
- **Test class/method:** `BardMuseTest::testEnigmaMuseGrantsFeatsAndSpell()`
- **Setup:** Create Bard with Enigma muse; inspect class feats and spell repertoire
- **Expected:** Bardic Lore feat in class_feats; true strike in spell_repertoire
- **Roles:** authenticated player
- **Dependency note:** Spell repertoire field depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0 if not yet shipped.

### TC-BRD-07 — Maestro Muse: Lingering Composition feat + soothe added to repertoire
- **AC:** `[NEW]` Maestro → Lingering Composition feat + soothe spell
- **Suite:** `module-test-suite`
- **Test class/method:** `BardMuseTest::testMaestroMuseGrantsFeatsAndSpell()`
- **Setup:** Create Bard with Maestro muse; inspect class feats and spell repertoire
- **Expected:** Lingering Composition feat in class_feats; soothe in spell_repertoire
- **Roles:** authenticated player
- **Dependency note:** Spell repertoire field depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0 if not yet shipped.

### TC-BRD-08 — Polymath Muse: Versatile Performance feat + unseen servant added to repertoire
- **AC:** `[NEW]` Polymath → Versatile Performance feat + unseen servant spell
- **Suite:** `module-test-suite`
- **Test class/method:** `BardMuseTest::testPolymathMuseGrantsFeatsAndSpell()`
- **Setup:** Create Bard with Polymath muse; inspect class feats and spell repertoire
- **Expected:** Versatile Performance feat in class_feats; unseen servant in spell_repertoire
- **Roles:** authenticated player
- **Dependency note:** Spell repertoire field depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0 if not yet shipped.

### TC-BRD-09 — Occult spellcasting: spell attacks and DCs scale with CHA
- **AC:** `[NEW]` Bard uses occult spell tradition; spell attacks and DCs scale with Charisma
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testSpellAttackAndDcScaleWithCha()`
- **Setup:** Create Bard with CHA 16 (mod +3); inspect spell_attack_bonus and spell_dc values
- **Expected:** spell_attack_bonus references CHA modifier; spell_dc = 10 + proficiency + CHA mod
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-10 — Instrument replaces somatic/material components; can replace verbal
- **AC:** `[NEW]` Instrument replaces material and somatic component requirements; can also replace verbal components
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testInstrumentReplacesComponents()`
- **Setup:** Bard with instrument equipped; cast spell with somatic/material components; cast spell with verbal component using instrument; inspect component requirements and hands required
- **Expected:** Somatic/material component requirements waived when instrument held in one hand; verbal component replaceable via instrument
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-11 — Spell repertoire: 2 first-level spells + 5 cantrips at level 1
- **AC:** `[NEW]` Bard uses Spell Repertoire; starts with 2 first-level occult spells + 5 cantrips at level 1
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testStartingSpellRepertoire()`
- **Setup:** Create level-1 Bard; inspect spell_repertoire (excluding muse spell)
- **Expected:** 2 level-1 occult spells in repertoire; 5 cantrips in repertoire; all tagged as occult tradition
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-12 — Cantrips auto-heighten to half level rounded up
- **AC:** `[NEW]` Cantrips auto-heighten to half level rounded up
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testCantripsAutoHeighten()`
- **Setup:** Bard at level 1, 3, 5; inspect effective cantrip level
- **Expected:** Level 1 cantrip heightened to 1; level 3 → 2; level 5 → 3
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-13 — Spell swap at level-up: one known spell can be replaced per level
- **AC:** `[NEW]` Each level-up allows swapping one known spell for another of the same level
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testSpellSwapAtLevelUp()`
- **Setup:** Bard at level 1 with known level-1 spell; level up; swap for another level-1 spell
- **Expected:** New spell in repertoire; old spell removed; cannot swap for a different level spell
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-14 — Signature Spells at level 3: one per spell level, can be freely heightened
- **AC:** `[NEW]` Level 3: Signature Spells — one per spell level designated; can be heightened freely without learning each level
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testSignatureSpellsAtLevel3()`
- **Setup:** Bard at level 3; designate a level-1 spell as signature; cast it at level-2 slot (heightened) without learning level-2 version
- **Expected:** Heightened cast succeeds; non-signature spell heightened to unlearned level is blocked
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-15 — Focus pool starts at 1; max 3 with feats
- **AC:** `[NEW]` Bard starts with focus pool of 1 Focus Point; max 3 with feats
- **Suite:** `module-test-suite`
- **Test class/method:** `BardFocusPoolTest::testFocusPoolStartsAtOne()`
- **Setup:** Create level-1 Bard; inspect focus_pool_max and focus_points
- **Expected:** focus_pool_max = 1; focus_points = 1; adding 2 focus-granting feats raises max to 3
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-focus-spells`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-16 — Composition focus spells cost 1 Focus Point; cantrip compositions are free
- **AC:** `[NEW]` Composition focus spells cost 1 Focus Point; composition cantrips are free
- **Suite:** `module-test-suite`
- **Test class/method:** `BardFocusPoolTest::testCompositionFocusPointCost()`
- **Setup:** Bard with 1 focus point; use Counter Performance (focus spell); inspect focus_points; use Inspire Courage (cantrip); inspect focus_points
- **Expected:** Counter Performance: focus_points reduced by 1; Inspire Courage: focus_points unchanged
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-focus-spells`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-17 — Focus pool at 0: composition focus spells blocked; cantrip compositions work
- **AC:** `[NEW]` Focus pool at 0: composition focus spells blocked; cantrip compositions still work
- **Suite:** `module-test-suite`
- **Test class/method:** `BardFocusPoolTest::testFocusPoolEmptyBlocksNonCantrip()`
- **Setup:** Drain focus pool to 0; attempt Counter Performance (focus spell); attempt Inspire Courage (cantrip)
- **Expected:** Counter Performance blocked (no focus points); Inspire Courage succeeds
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-focus-spells`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-18 — Refocus: 10 minutes; restores 1 Focus Point
- **AC:** `[NEW]` Refocus = 10 minutes performing, writing a composition, or engaging muse
- **Suite:** `module-test-suite`
- **Test class/method:** `BardFocusPoolTest::testRefocusRestoresFocusPoint()`
- **Setup:** Drain focus pool; trigger refocus action (10-minute activity); inspect focus_points
- **Expected:** focus_points restored by 1 (up to max)
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-focus-spells`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-19 — Composition spells auto-heighten to half level rounded up
- **AC:** `[NEW]` Composition spells auto-heighten to half level rounded up
- **Suite:** `module-test-suite`
- **Test class/method:** `BardFocusPoolTest::testCompositionSpellsAutoHeighten()`
- **Setup:** Bard at level 5; use Counter Performance; inspect effective spell level
- **Expected:** Counter Performance cast at level 3 (ceil(5/2)); Bard at level 7 → cast at level 4
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-focus-spells`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-20 — Counter Performance available as starting composition focus spell
- **AC:** `[NEW]` Starting composition focus spell: Counter Performance (reaction, protects from auditory/visual effects)
- **Suite:** `module-test-suite`
- **Test class/method:** `BardCompositionTest::testCounterPerformanceAvailableAtLevel1()`
- **Setup:** Create level-1 Bard; inspect available composition focus spells
- **Expected:** Counter Performance present; tagged as reaction; description references auditory/visual protection
- **Roles:** authenticated player

### TC-BRD-21 — Inspire Courage available as starting composition cantrip
- **AC:** `[NEW]` Starting composition cantrip: Inspire Courage (buffs ally attacks, damage, saves vs fear)
- **Suite:** `module-test-suite`
- **Test class/method:** `BardCompositionTest::testInspireCourageAvailableAtLevel1()`
- **Setup:** Create level-1 Bard; inspect available composition cantrips
- **Expected:** Inspire Courage present; tagged as cantrip; description references attack/damage/fear save buff
- **Roles:** authenticated player

### TC-BRD-22 — Composition trait: only one composition per turn
- **AC:** `[NEW]` Composition trait: only one composition per turn; only one active at a time; new ends previous
- **Suite:** `module-test-suite`
- **Test class/method:** `BardCompositionTest::testOnlyOneCompositionPerTurn()`
- **Setup:** Bard uses Inspire Courage; attempt to use Counter Performance on same turn
- **Expected:** Second composition blocked with error referencing composition trait
- **Roles:** authenticated player

### TC-BRD-23 — Composition trait: new composition immediately ends previous
- **AC:** `[NEW]` Casting a new composition while one is active: previous composition immediately ends
- **Suite:** `module-test-suite`
- **Test class/method:** `BardCompositionTest::testNewCompositionEndsPrevious()`
- **Setup:** Bard activates Inspire Courage (sustained); on next turn, activates a different composition
- **Expected:** Inspire Courage ends immediately; new composition active; no two compositions active simultaneously
- **Roles:** authenticated player

### TC-BRD-24 — Level 3: Lightning Reflexes — Reflex to Expert
- **AC:** `[NEW]` Level 3: Lightning Reflexes — Reflex saves increase to Expert
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testLightningReflexesAtLevel3()`
- **Setup:** Bard at level 3; inspect Reflex proficiency
- **Expected:** Reflex proficiency = Expert
- **Roles:** authenticated player

### TC-BRD-25 — Level 7: Occult spell attacks and DCs increase to Expert
- **AC:** `[NEW]` Level 7: occult spell attack rolls and DCs increase to Expert
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testOccultSpellProficiencyExpertAtLevel7()`
- **Setup:** Bard at level 7; inspect occult_spell_attack proficiency and occult_spell_dc proficiency
- **Expected:** Both = Expert
- **Roles:** authenticated player

### TC-BRD-26 — Level 9: Great Fortitude — Fortitude to Expert; Resolve — Will to Master
- **AC:** `[NEW]` Level 9: Great Fortitude (Fortitude → Expert); Resolve (Will → Master; successes become critical successes)
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testLevel9SaveProficiencies()`
- **Setup:** Bard at level 9; inspect Fortitude and Will proficiency; simulate Will success
- **Expected:** Fortitude = Expert; Will = Master; Will success result = critical success
- **Roles:** authenticated player

### TC-BRD-27 — Level 11: Bard Weapon Expertise — weapons to Expert; crit specialization during composition
- **AC:** `[NEW]` Level 11: Bard Weapon Expertise — simple/unarmed/specific martial weapons to Expert; crit specialization during active composition
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testBardWeaponExpertiseAtLevel11()`
- **Setup:** Bard at level 11; inspect weapon proficiency; activate composition; perform critical hit; inspect specialization effect
- **Expected:** Weapon proficiency = Expert; critical specialization applied during active composition; not applied without active composition
- **Roles:** authenticated player

### TC-BRD-28 — Level 11: Vigilant Senses — Perception to Master
- **AC:** `[NEW]` Level 11: Vigilant Senses — Perception increases to Master
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testVigilantSensesAtLevel11()`
- **Setup:** Bard at level 11; inspect Perception proficiency
- **Expected:** Perception proficiency = Master
- **Roles:** authenticated player

### TC-BRD-29 — Level 13: Light armor and unarmored to Expert; Weapon Specialization
- **AC:** `[NEW]` Level 13: light armor/unarmored to Expert; Weapon Specialization +2/+3/+4 at Expert/Master/Legendary
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testLevel13ArmorAndWeaponSpec()`
- **Setup:** Bard at level 13 with Expert-proficiency weapon; inspect armor proficiency and weapon damage modifier
- **Expected:** light_armor = Expert; unarmored = Expert; damage bonus = +2 for Expert weapon
- **Roles:** authenticated player

### TC-BRD-30 — Level 15: Occult spell attacks and DCs increase to Master
- **AC:** `[NEW]` Level 15: occult spell attack rolls and DCs increase to Master
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testOccultSpellProficiencyMasterAtLevel15()`
- **Setup:** Bard at level 15; inspect occult_spell_attack proficiency and occult_spell_dc proficiency
- **Expected:** Both = Master
- **Roles:** authenticated player

### TC-BRD-31 — Level 17: Greater Resolve — Will to Legendary; critical failures become failures; failure damage halved
- **AC:** `[NEW]` Level 17: Greater Resolve — Will to Legendary; successes → critical successes; critical failures → failures; failure damage halved
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testGreaterResolveAtLevel17()`
- **Setup:** Bard at level 17; inspect Will proficiency; simulate Will critical failure; simulate Will failure against damage effect
- **Expected:** Will = Legendary; critical failure result = failure; failure damage = half of original
- **Roles:** authenticated player

### TC-BRD-32 — Level 19: Occult spell attacks and DCs increase to Legendary; Magnum Opus
- **AC:** `[NEW]` Level 19: occult spell attacks/DCs to Legendary; Magnum Opus — 2 common 10th-level spells + unique 10th-level slot
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testLevel19OccultMasteryAndMagnumOpus()`
- **Setup:** Bard at level 19; inspect occult_spell_attack/DC proficiency; inspect spell repertoire for 10th-level spells; inspect 10th-level slot
- **Expected:** Both occult proficiencies = Legendary; 2 common 10th-level occult spells in repertoire; 1 unique 10th-level slot available; unique slot cannot be used with slot-manipulation features
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-33 — Spell slots: correct count at level 1; scale per advancement table
- **AC:** `[NEW]` Bard gains spell slots per advancement table (2 at level 1, scaling through 9th at level 17)
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testSpellSlotsMatchAdvancementTable()`
- **Setup:** Bard at levels 1, 3, 5, 7; inspect spell_slots per level
- **Expected:** Level 1: 2 first-level slots; Level 3: first-level slots + 2 second-level slots; etc., per PF2E advancement table
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-34 — Spell slots do not exceed advancement table values per level
- **AC:** `[TEST-ONLY]` Spell slots do not exceed advancement table values per level
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testSpellSlotsDoNotExceedTable()`
- **Setup:** Attempt to manually grant extra spell slots above the advancement table values at level 2
- **Expected:** Enforcement prevents over-granting; total slots = table values only
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-35 — Metamagic: interruption before Cast a Spell wastes metamagic
- **AC:** `[NEW]` Metamagic actions must immediately precede Cast a Spell; interruption wastes metamagic
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testMetamagicInterruptionWastesMetamagic()`
- **Setup:** Bard uses a metamagic action; before casting, another action interrupts the sequence; inspect metamagic state
- **Expected:** Metamagic is wasted (no longer active); spell cast normally (without metamagic) or next cast attempt has no metamagic
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-36 — Heightening a signature spell to any level: permitted without learning each level
- **AC:** `[NEW]` Heightening a signature spell to any level: permitted without learning each level
- **Suite:** `module-test-suite`
- **Test class/method:** `BardSpellcastingTest::testSignatureSpellHeighteningFreelyPermitted()`
- **Setup:** Bard at level 5 with level-1 signature spell; cast at level-3 slot; inspect result
- **Expected:** Cast succeeds at level 3; non-signature level-1 spell cast at level-3 slot blocked (not learned at that level)
- **Roles:** authenticated player
- **Dependency note:** Depends on `dc-cr-spellcasting`. Mark `pending-dev-confirmation` at Stage 0.

### TC-BRD-37 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated class features do not appear before required level
- **Suite:** `module-test-suite`
- **Test class/method:** `BardLevelFeaturesTest::testLevelGatedFeaturesNotGrantedEarly()`
- **Setup:** Bard at level 2; inspect Lightning Reflexes, Signature Spells, Weapon Expertise availability
- **Expected:** None of the level-gated features present at level 2
- **Roles:** authenticated player

### TC-BRD-38 — Player cannot modify another player's Bard character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `BardClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Bard owned by user A; attempt muse change or spell repertoire modification as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-BRD-39 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Specific weapon list completeness (longsword/rapier/sap/shortbow/shortsword/whip all trained) | TC-BRD-03 checks that these weapons exist in trained_weapons; verifying the full list is correct against PF2E source requires editorial review |
| Spell repertoire advancement table completeness (one spell per new slot tier) | TC-BRD-33 tests specific levels; full table coverage requires parameterized tests against all 20 levels once `dc-cr-spellcasting` is implemented |
| Muse bonus feats (Bardic Lore, Lingering Composition, Versatile Performance) — full feat behavior | TCs BRD-06/07/08 verify feats are granted; the actual feat mechanics (what Bardic Lore does, how Lingering Composition extends compositions) are not automatable until those feats are implemented |
| Instrument component replacement UX | TC-BRD-10 tests the logic; whether the instrument is properly surfaced in the UI (held-in-hand state tracking) may require Playwright once a UI is built |

---

## Regression risk areas

1. **`dc-cr-spellcasting` dependency**: nearly all Bard spellcasting TCs (BRD-06 through BRD-14, BRD-32 through BRD-36) depend on this deferred feature. Mark all as `pending-dev-confirmation` at Stage 0 until `dc-cr-spellcasting` ships.
2. **`dc-cr-focus-spells` dependency**: focus pool and composition focus TCs (BRD-15 through BRD-19) depend on this deferred feature.
3. **`dc-cr-character-class` overlap**: Bard class node seeded by `dc-cr-character-class`; Muse data must bind correctly to that node without breaking class seeding.
4. **HP calculation overlap**: Bard HP = 8 + CON mod; verify no conflict with ancestry/background HP additions.
5. **Composition state machine**: only one active composition; new ends previous. Verify that composition tracking is not corrupted across turn boundaries or if composition ends prematurely.
6. **Muse bonus feat + level-1 class feat interaction**: Fury Instinct pattern (Barbarian) and Muse pattern (Bard) both grant bonus feats at level 1. Verify the feat slot count system is shared and additive, not overwritten.
7. **QA audit regression**: no new routes per security AC; if any are added, they must be probed in qa-permissions.json before release.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-class-bard

## Gap analysis reference
- DB sections: core/ch03/Bard (REQs 882–940+)
- Track B: no existing BardService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships), dc-cr-focus-spells (deferred)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Bard exists as a selectable playable class with Charisma as key ability boost at level 1.
- [ ] `[NEW]` Bard HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Expert Perception; Expert Will, Trained Fortitude and Reflex; Trained Occultism and Performance (automatic) plus 4 + INT skills; Trained simple weapons plus longsword/rapier/sap/shortbow/shortsword/whip; Trained light armor only; Trained occult spell attacks and DCs.

### Muse Selection (Level 1 Subclass)
- [ ] `[NEW]` At level 1, player selects one Muse: Enigma, Maestro, or Polymath.
- [ ] `[NEW]` Each Muse grants one bonus feat and one spell added to repertoire:
  - Enigma → Bardic Lore feat + true strike.
  - Maestro → Lingering Composition feat + soothe.
  - Polymath → Versatile Performance feat + unseen servant.

### Occult Spellcasting
- [ ] `[NEW]` Bard uses occult spell tradition; spell attacks and DCs scale with Charisma.
- [ ] `[NEW]` Instrument replaces material and somatic component requirements (one hand free); can also replace verbal components.
- [ ] `[NEW]` Bard uses a Spell Repertoire (spontaneous casting — known spells, not prepared). Starts with 2 first-level occult spells + 5 cantrips at level 1.
- [ ] `[NEW]` One spell added per new slot tier as levels increase per advancement table.
- [ ] `[NEW]` Cantrips auto-heighten to half level rounded up.
- [ ] `[NEW]` Each level-up allows swapping one known spell for another of the same level.
- [ ] `[NEW]` Signature Spells (level 3): one spell per spell level designated as signature; can be heightened freely without learning each level separately.

### Composition Spells & Focus Pool
- [ ] `[NEW]` Bard starts with focus pool of 1 Focus Point (max 3 with feats).
- [ ] `[NEW]` Focus spells (compositions) cost 1 Focus Point; composition cantrips are free.
- [ ] `[NEW]` Refocus = 10 minutes performing, writing a composition, or engaging muse.
- [ ] `[NEW]` Composition spells auto-heighten to half level rounded up.
- [ ] `[NEW]` Starting composition focus spell: Counter Performance (reaction, protects allies from auditory/visual effects).
- [ ] `[NEW]` Starting composition cantrip: Inspire Courage (free; buffs ally attacks, damage, saves vs fear).
- [ ] `[NEW]` Composition trait enforced: only one composition per turn; only one active at a time; new composition immediately ends previous.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 3: Lightning Reflexes — Reflex saves increase to Expert.
- [ ] `[NEW]` Level 7: occult spell attack rolls and DCs increase to Expert.
- [ ] `[NEW]` Level 9: Great Fortitude — Fortitude saves increase to Expert.
- [ ] `[NEW]` Level 9: Resolve — Will saves increase to Master; successes become critical successes.
- [ ] `[NEW]` Level 11: Bard Weapon Expertise — simple weapons, unarmed, and specific martial weapons increase to Expert; during active composition, critical hits apply critical specialization effects.
- [ ] `[NEW]` Level 11: Vigilant Senses — Perception increases to Master.
- [ ] `[NEW]` Level 13: light armor and unarmored defense increase to Expert.
- [ ] `[NEW]` Level 13: Weapon Specialization — +2/+3/+4 damage at Expert/Master/Legendary.
- [ ] `[NEW]` Level 15: occult spell attack rolls and DCs increase to Master.
- [ ] `[NEW]` Level 17: Greater Resolve — Will saves increase to Legendary; successes become critical successes; critical failures become failures; failures against damaging effects take half damage.
- [ ] `[NEW]` Level 19: occult spell attack rolls and DCs increase to Legendary.
- [ ] `[NEW]` Level 19: Magnum Opus — add 2 common 10th-level occult spells to repertoire; gain one unique 10th-level spell slot (cannot be used with slot-manipulation features).

### Spell Slots
- [ ] `[NEW]` Bard gains spell slots per advancement table (2 at level 1, scaling through 9th-level slots at level 17, 10th-level unique slot at level 19).
- [ ] `[NEW]` Metamagic actions must immediately precede Cast a Spell; interruption (including free actions) wastes the metamagic.

---

## Edge Cases

- [ ] `[NEW]` Attempting to cast two compositions in the same turn: second is blocked.
- [ ] `[NEW]` Casting a new composition while one is active: previous composition immediately ends.
- [ ] `[NEW]` Heightening a signature spell to any level: permitted without learning each level.
- [ ] `[NEW]` Focus pool at 0: composition focus spells blocked; cantrip compositions still work.
- [ ] `[NEW]` Instrument replaces somatic/material: correctly applies when instrument is held in one hand.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Invalid Muse selection rejected.
- [ ] `[TEST-ONLY]` Level-gated class features do not appear before required level.
- [ ] `[TEST-ONLY]` Spell slots do not exceed advancement table values per level.
- [ ] `[TEST-ONLY]` Spell repertoire size enforced; spells above advanced alchemy level (N/A — cap is by spell level/slot) are correctly gated.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
