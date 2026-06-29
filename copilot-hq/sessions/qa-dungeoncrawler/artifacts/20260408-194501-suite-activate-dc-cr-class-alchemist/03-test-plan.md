# Test Plan: dc-cr-class-alchemist

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Alchemist Class — identity/role, research field, infused reagents, advanced alchemy, quick alchemy, formula book, level-gated features, additive feats
**AC source:** `features/dc-cr-class-alchemist/01-acceptance-criteria.md`
**Status:** NEXT-RELEASE grooming — DO NOT add to suite.json until Stage 0 activation

## KB references
- KB: no prior lessons found for Alchemist class logic; this is the first alchemist implementation.
- KB ref: `dc-cr-character-class` test plan pattern (`features/dc-cr-character-class/03-test-plan.md`) — same suite/runner structure applies.
- Dependency note: `dc-cr-equipment-system` is in-progress; Advanced Alchemy item creation may depend on equipment system entity structure being stable. Flag at Stage 0 activation.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | All business logic: class selection, HP calc, research field, reagents, alchemy actions, feature unlocks, additive feats, guards |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class node read, character creation form access |

> **Note:** AC includes a security exemption — no new routes beyond existing character creation and leveling forms. All alchemy logic is service/entity layer. No Playwright or API integration suite needed at this scope (extend Playwright if a Quick Alchemy UI action is built later).

---

## Test cases

### TC-ALC-01 — Alchemist exists as selectable class in character creation
- **AC:** `[NEW]` Alchemist exists as a selectable playable class in character creation
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistClassTest::testAlchemistExistsInClassList()`
- **Setup:** Query `character_class` nodes; inspect class list returned by character creation step
- **Expected:** Alchemist class node exists; included in selectable class list
- **Roles:** authenticated player

### TC-ALC-02 — Alchemist class description references alchemical items (not spellcasting)
- **AC:** `[NEW]` Class description surfaces that alchemical items (not spellcasting) define identity
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistClassTest::testAlchemistDescriptionContent()`
- **Setup:** Load alchemist `character_class` node; read description field
- **Expected:** Description contains "alchemical" and does NOT contain "spellcasting" or "spell slots"
- **Roles:** n/a (data integrity check)

### TC-ALC-03 — Alchemist enforces Intelligence as key ability score
- **AC:** `[NEW]` Character creation enforces INT as Alchemist key ability score; class DC references INT
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistClassTest::testKeyAbilityIsIntelligence()`
- **Setup:** Select Alchemist class for a character; inspect key_ability and class_dc_ability fields
- **Expected:** `key_ability = intelligence`; class DC modifier references INT score
- **Roles:** authenticated player

### TC-ALC-04 — Alchemist starting HP calculation
- **AC:** `[NEW]` Starting HP = 8 + CON modifier at level 1; increases by same each subsequent level
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistClassTest::testStartingHpCalculation()`
- **Setup:** Create Alchemist character with CON 14 (mod +2); check HP at level 1 and level 2
- **Expected:** Level 1 HP = 10; Level 2 HP = 20
- **Roles:** authenticated player

### TC-ALC-05 — Research field selection: valid values accepted
- **AC:** `[NEW]` At level 1, player selects one research field: Bomber, Chirurgeon, or Mutagenist
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistResearchFieldTest::testValidResearchFieldAccepted()`
- **Setup:** Create level-1 Alchemist; submit research_field = "bomber" / "chirurgeon" / "mutagenist"
- **Expected:** Each accepted; stored on character entity; all three valid values enumerated
- **Roles:** authenticated player

### TC-ALC-06 — Research field locked after level 1
- **AC:** `[NEW]` Research field selection is stored and locked after level 1 (cannot be changed)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistResearchFieldTest::testResearchFieldLockedAfterLevelOne()`
- **Setup:** Set research_field = "bomber" at level 1; level character to 2; attempt to change to "chirurgeon"
- **Expected:** Error returned; research_field remains "bomber"
- **Roles:** authenticated player

### TC-ALC-07 — Invalid research field rejected
- **AC:** `[TEST-ONLY]` Invalid research field selection (not Bomber/Chirurgeon/Mutagenist) is rejected
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistResearchFieldTest::testInvalidResearchFieldRejected()`
- **Setup:** Submit research_field = "wizard" or "invalid_value"
- **Expected:** Validation error returned; research_field not set
- **Roles:** authenticated player

### TC-ALC-08 — Bomber field: advanced alchemy produces bombs
- **AC:** `[NEW]` Bomber field grants: advanced alchemy produces bombs
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistResearchFieldTest::testBomberFieldGrantsBombAccess()`
- **Setup:** Alchemist with Bomber field; call advanced alchemy with bomb formula
- **Expected:** Bomb item created; no error
- **Roles:** authenticated player

### TC-ALC-09 — Chirurgeon field: advanced alchemy produces healing elixirs; Medicine bonus granted
- **AC:** `[NEW]` Chirurgeon field grants healing elixirs and extra Medicine bonus
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistResearchFieldTest::testChirurgeonFieldGrantsHealingElixirAndMedicineBonus()`
- **Setup:** Alchemist with Chirurgeon field; call advanced alchemy with healing elixir formula; inspect Medicine skill modifier
- **Expected:** Healing elixir created; Medicine modifier includes field bonus
- **Roles:** authenticated player

### TC-ALC-10 — Mutagenist field: advanced alchemy produces mutagens
- **AC:** `[NEW]` Mutagenist field grants: advanced alchemy produces mutagens
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistResearchFieldTest::testMutagenistFieldGrantsMutagenAccess()`
- **Setup:** Alchemist with Mutagenist field; call advanced alchemy with mutagen formula
- **Expected:** Mutagen item created; no error
- **Roles:** authenticated player

### TC-ALC-11 — Infused reagent count = level + INT modifier
- **AC:** `[NEW]` Infused reagent batches per day = level + INT modifier (minimum 1)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistInfusedReagentsTest::testReagentCountEqualsLevelPlusIntMod()`
- **Setup:** Level-3 Alchemist with INT 16 (mod +3); inspect reagent_count
- **Expected:** reagent_count = 6 (3 + 3); test minimum: level-1, INT 8 (mod -1) → reagent_count = 1
- **Roles:** authenticated player

### TC-ALC-12 — Infused reagents refresh at daily preparations
- **AC:** `[NEW]` Infused reagent count refreshes at daily preparations
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistInfusedReagentsTest::testReagentsRefreshAtDailyPrep()`
- **Setup:** Consume all reagents; trigger daily preparation action; inspect reagent_count
- **Expected:** reagent_count restored to level + INT modifier
- **Roles:** authenticated player

### TC-ALC-13 — Infused reagents consumed by Advanced and Quick Alchemy
- **AC:** `[NEW]` Infused reagents are consumed when using Advanced Alchemy or Quick Alchemy
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistInfusedReagentsTest::testReagentsConsumedByAlchemyActions()`
- **Setup:** Alchemist with 4 reagents; spend 1 via Quick Alchemy, 2 via Advanced Alchemy
- **Expected:** reagent_count = 1
- **Roles:** authenticated player

### TC-ALC-14 — Quick Alchemy blocked with 0 reagents
- **AC:** `[NEW]` Infused reagent count of 0: Quick Alchemy and Advanced Alchemy blocked; UI shows clear error
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistInfusedReagentsTest::testAlchemyBlockedWithZeroReagents()`
- **Setup:** Drain all reagents; attempt Quick Alchemy
- **Expected:** Error returned; no item created; error message references reagent count
- **Roles:** authenticated player

### TC-ALC-15 — Advanced Alchemy: items created during daily preparations
- **AC:** `[NEW]` During daily preparations, alchemist spends reagent batches to create items from formula book
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistAdvancedAlchemyTest::testAdvancedAlchemyCreatesItemsDuringDailyPrep()`
- **Setup:** Alchemist with 4 reagents, formula book with level-1 bomb; call daily prep with 2 reagent spend
- **Expected:** 2 bombs created (infused); reagent_count reduced by 2; no gold cost
- **Roles:** authenticated player

### TC-ALC-16 — Advanced Alchemy: item level cap enforced
- **AC:** `[NEW]` Items limited to level ≤ advanced alchemy level (= character level)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistAdvancedAlchemyTest::testAdvancedAlchemyItemLevelCapEnforced()`
- **Setup:** Level-3 Alchemist; attempt to create a level-4 alchemical item via Advanced Alchemy
- **Expected:** Error returned; item not created
- **Roles:** authenticated player

### TC-ALC-17 — Advanced Alchemy: items expire at next daily preparations
- **AC:** `[NEW]` Advanced Alchemy items are infused: nonpermanent effects end at next daily preparations; active afflictions persist
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistAdvancedAlchemyTest::testAdvancedAlchemyItemsExpireAtDailyPrep()`
- **Setup:** Create infused items via Advanced Alchemy; trigger next daily prep; inspect item state
- **Expected:** Infused items marked expired; nonpermanent effects ended; active affliction items unaffected
- **Roles:** authenticated player

### TC-ALC-18 — Quick Alchemy: creates one item, consumes 1 reagent
- **AC:** `[NEW]` Quick Alchemy [one-action, manipulate]: spend 1 reagent batch to create one item from formula book
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistQuickAlchemyTest::testQuickAlchemyCreatesItemAndConsumesReagent()`
- **Setup:** Alchemist with 3 reagents, formula in book; invoke Quick Alchemy
- **Expected:** 1 item created (infused, temporary); reagent_count = 2
- **Roles:** authenticated player

### TC-ALC-19 — Quick Alchemy: item expires at start of next turn
- **AC:** `[NEW]` Quick Alchemy items expire at start of alchemist's next turn if not used
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistQuickAlchemyTest::testQuickAlchemyItemExpiresAtStartOfNextTurn()`
- **Setup:** Create item via Quick Alchemy; advance combat turn; inspect item
- **Expected:** Item marked expired at start of alchemist's next turn (if not used)
- **Roles:** authenticated player

### TC-ALC-20 — Quick Alchemy: level cap enforced
- **AC:** `[NEW]` Quick Alchemy can only create items of level ≤ advanced alchemy level
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistQuickAlchemyTest::testQuickAlchemyItemLevelCapEnforced()`
- **Setup:** Level-2 Alchemist; attempt Quick Alchemy with level-3 formula
- **Expected:** Error returned; item not created
- **Roles:** authenticated player

### TC-ALC-21 — Quick Alchemy: blocked for item not in formula book
- **AC:** `[NEW]` Attempting Quick Alchemy for an item not in formula book: blocked with error
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistQuickAlchemyTest::testQuickAlchemyBlockedForUnknownFormula()`
- **Setup:** Attempt Quick Alchemy with item ID not in character's formula book
- **Expected:** Error returned; item not created
- **Roles:** authenticated player

### TC-ALC-22 — Formula book: starting formulas present for research field
- **AC:** `[NEW]` Alchemist starts with formula book containing level-0 and level-1 items per research field starter list
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistFormulaBookTest::testStartingFormulasPresent()`
- **Setup:** Create level-1 Alchemist with each research field; inspect formula_book field
- **Expected:** level-0 and level-1 formulas appropriate to each field present at character creation
- **Roles:** authenticated player

### TC-ALC-23 — Formula book: formulas can be added
- **AC:** `[NEW]` Formulas can be added to the formula book (via crafting, purchasing, finding)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistFormulaBookTest::testFormulaCanBeAdded()`
- **Setup:** Call formula-add service with valid formula ID; inspect formula_book
- **Expected:** Formula added to formula_book; Advanced/Quick Alchemy can now create that item
- **Roles:** authenticated player

### TC-ALC-24 — Level 5: Field Discovery grants batch flexibility per field
- **AC:** `[NEW]` Level 5: Field Discovery — each advanced alchemy batch may produce any 3 items (field-appropriate)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testFieldDiscoveryAtLevel5()`
- **Setup:** Alchemist at level 5 (all three fields); call advanced alchemy with 3 different item types in one batch
- **Expected:** All 3 items created; not required to be identical; each field restricts to appropriate item types
- **Roles:** authenticated player

### TC-ALC-25 — Level 5: Powerful Alchemy — Quick Alchemy item DCs use class DC
- **AC:** `[NEW]` Level 5: Powerful Alchemy — Quick Alchemy items with saving throws use alchemist's class DC
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testPowerfulAlchemyClassDCAtLevel5()`
- **Setup:** Alchemist at level 5; create bomb via Quick Alchemy; inspect item's saving throw DC
- **Expected:** Item DC = alchemist class DC (not item's default DC)
- **Roles:** authenticated player

### TC-ALC-26 — Level 7: Perpetual Infusions — 2 designated items free via Quick Alchemy
- **AC:** `[NEW]` Level 7: Perpetual Infusions — designate 2 items (field-eligible, in formula book); create via Quick Alchemy for free (no reagent cost)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testPerpetualInfusionsAtLevel7()`
- **Setup:** Alchemist at level 7 with designated perpetual items; invoke Quick Alchemy for each; check reagent_count before/after
- **Expected:** Perpetual items created; reagent_count unchanged; non-designated item via Quick Alchemy still consumes reagent
- **Roles:** authenticated player

### TC-ALC-27 — Level 7: Perpetual Infusion item swap at level-up
- **AC:** `[NEW]` Perpetual Infusion item swap at level-up: old selection replaced; new items from eligible list only
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testPerpetualInfusionSwapAtLevelUp()`
- **Setup:** Designate perpetual items; level up; attempt to swap to new eligible item and to an ineligible item
- **Expected:** Eligible swap accepted; ineligible swap rejected; new designation active
- **Roles:** authenticated player

### TC-ALC-28 — Level 9: Double Brew — spend up to 2 reagents for up to 2 items in one action
- **AC:** `[NEW]` Level 9: Double Brew — Quick Alchemy may spend up to 2 batches to create up to 2 items in one action
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testDoubleBrewAtLevel9()`
- **Setup:** Alchemist at level 9 with 3 reagents; call Quick Alchemy with 2 batches; inspect items and reagent_count
- **Expected:** 2 items created (need not be identical); reagent_count reduced by 2; both expire at start of next turn
- **Roles:** authenticated player

### TC-ALC-29 — Level 11: Perpetual Potency — upgrades Perpetual Infusions item level eligibility
- **AC:** `[NEW]` Level 11: Perpetual Potency — upgrades eligible item levels (Bomber → 3rd-level, Chirurgeon → 6th-level, Mutagenist → 3rd-level)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testPerpetualPotencyAtLevel11()`
- **Setup:** Alchemist at level 11 for each field; attempt to designate perpetual items at upgraded levels
- **Expected:** Higher-level items accepted as perpetual designations; pre-Potency items still valid
- **Roles:** authenticated player

### TC-ALC-30 — Level 13: Greater Field Discovery (Mutagenist) — 2 simultaneous mutagens, 3rd removes one benefit
- **AC:** `[NEW]` Level 13: Greater Field Discovery (Mutagenist) — may be under 2 mutagen effects; 3rd causes loss of one prior benefit (player choice), all drawbacks persist
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testMutagenistGreaterFieldDiscoveryAtLevel13()`
- **Setup:** Mutagenist at level 13; apply 2 mutagens (both benefits active); apply 3rd; player selects which benefit to lose; inspect active effects
- **Expected:** 2 mutagen benefits active; after 3rd: one benefit removed (player-chosen), all drawbacks remain; non-mutagen polymorph: both benefits lost, both drawbacks persist
- **Roles:** authenticated player

### TC-ALC-31 — Level 13: Greater Field Discovery (Chirurgeon) — elixirs of life via Quick Alchemy heal max HP
- **AC:** `[NEW]` Level 13: Chirurgeon — elixirs of life created via Quick Alchemy heal maximum HP (no roll)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testChirurgeonGreaterFieldDiscoveryAtLevel13()`
- **Setup:** Chirurgeon at level 13; create elixir of life via Quick Alchemy; apply to character; inspect HP restored
- **Expected:** HP restored = maximum value for that elixir tier (no dice roll)
- **Roles:** authenticated player

### TC-ALC-32 — Level 15: Alchemical Alacrity — spend up to 3 reagents, create 3 items, one auto-stowed
- **AC:** `[NEW]` Level 15: Quick Alchemy may spend up to 3 reagent batches to create up to 3 items; one auto-stowed
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testAlchemicalAlacrity()`
- **Setup:** Alchemist at level 15 with 4 reagents; call Quick Alchemy with 3 batches
- **Expected:** 3 items created; 1 item flagged as auto-stowed; reagent_count reduced by 3
- **Roles:** authenticated player

### TC-ALC-33 — Level 17: Perpetual Perfection — upgrades Perpetual Potency to 11th-level items for all fields
- **AC:** `[NEW]` Level 17: Perpetual Perfection — upgrades eligible level to 11th for all research fields
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testPerpetualPerfectionAtLevel17()`
- **Setup:** Alchemist at level 17 for each field; attempt to designate level-11 item as perpetual
- **Expected:** Level-11 perpetual designation accepted for all fields
- **Roles:** authenticated player

### TC-ALC-34 — Level-gated features do not appear before required level
- **AC:** `[TEST-ONLY]` Level-gated class features do not appear before the required level
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistLevelFeaturesTest::testLevelGatedFeaturesNotGrantedEarly()`
- **Setup:** Alchemist at level 4 (below level-5 gate); inspect available features and perpetual infusions
- **Expected:** Field Discovery, Powerful Alchemy, Perpetual Infusions absent; Double Brew absent below level 9
- **Roles:** authenticated player

### TC-ALC-35 — Class feat granted at level 1 and every even level
- **AC:** `[NEW]` Alchemist gains class feat at level 1 and every even level (2, 4, 6 … 20)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistFeatProgressionTest::testClassFeatSchedule()`
- **Setup:** Level Alchemist from 1 to 4; inspect class_feats_available count at each level
- **Expected:** Class feat slots granted at levels 1, 2, 4 (and projected through 20 per schedule)
- **Roles:** authenticated player

### TC-ALC-36 — Skill feat granted at level 2 and every 2 levels
- **AC:** `[NEW]` Alchemist gains skill feat at level 2 and every 2 levels thereafter
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistFeatProgressionTest::testSkillFeatSchedule()`
- **Setup:** Level Alchemist from 1 to 4; inspect skill_feats_available at each level
- **Expected:** Skill feat slots granted at level 2 and level 4 (not level 1 or 3)
- **Roles:** authenticated player

### TC-ALC-37 — Additive feat: only one additive per item; second spoils item
- **AC:** `[NEW]` Additive trait feats add one substance to a bomb or elixir; only one additive per item; a second additive spoils the item
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistAdditiveFeatTest::testSecondAdditiveSpoisItem()`
- **Setup:** Create infused item; apply one additive (success); attempt to apply second additive to same item
- **Expected:** First additive applied; second additive causes item to be spoiled (unusable); no exception/crash
- **Roles:** authenticated player

### TC-ALC-38 — Additive actions only available when creating infused items
- **AC:** `[NEW]` Additive actions are usable only when creating infused alchemical items
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistAdditiveFeatTest::testAdditiveActionsRequireInfusedItem()`
- **Setup:** Attempt additive action on a non-infused purchased item
- **Expected:** Error returned; additive action blocked
- **Roles:** authenticated player

### TC-ALC-39 — Additive level cap: combined item level must not exceed advanced alchemy level
- **AC:** `[NEW]` Additive level adds to modified item's level; combined level must not exceed advanced alchemy level
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistAdditiveFeatTest::testAdditiveLevelCap()`
- **Setup:** Level-3 Alchemist; create level-2 item; apply level-2 additive (combined = 4, exceeds cap 3)
- **Expected:** Additive blocked with error; item unchanged
- **Roles:** authenticated player

### TC-ALC-40 — Alchemist class DC uses INT; invalid key ability rejected
- **AC:** `[TEST-ONLY]` Alchemist class DC uses INT correctly; character with incorrect key ability is rejected
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistClassTest::testInvalidKeyAbilityRejected()`
- **Setup:** Attempt to create Alchemist character specifying STR as key ability
- **Expected:** Validation error; character creation fails with message referencing INT requirement
- **Roles:** authenticated player

### TC-ALC-41 — Infused item expiry enforced: nonpermanent effects end, afflictions persist
- **AC:** `[TEST-ONLY]` Infused item expiry is enforced; items do not persist beyond their duration
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistAdvancedAlchemyTest::testInfusedItemExpiryEnforced()`
- **Setup:** Character has infused item with active affliction effect; trigger expiry (next daily prep or next turn for Quick Alchemy); inspect effect state
- **Expected:** Nonpermanent effects removed; affliction (slow-acting poison) effect continues its own duration
- **Roles:** authenticated player

### TC-ALC-42 — Chirurgeon Perpetual Infusion: 10-minute immunity tracks correctly
- **AC:** `[NEW]` Chirurgeon Perpetual Infusion immunity: 10-minute immunity correctly tracks per character and does not block other elixir HP healing
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistResearchFieldTest::testChirurgeonPerpetualInfusionImmunity()`
- **Setup:** Chirurgeon uses perpetual infusion healing elixir; attempt second use within 10 minutes; attempt non-perpetual elixir HP healing
- **Expected:** Second perpetual use blocked (immunity); non-perpetual elixir HP healing succeeds normally
- **Roles:** authenticated player

### TC-ALC-43 — Player cannot modify another player's Alchemist character
- **AC:** (security — inherits from character system)
- **Suite:** `module-test-suite`
- **Test class/method:** `AlchemistClassTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Alchemist owned by user A; attempt Quick Alchemy or research field change as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (non-owner)

### TC-ALC-44 — QA audit still passes after module deployment
- **AC:** QA automated audit must pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler`
- **Expected:** 0 violations, 0 failures; no new unexpected 403/404 regressions
- **Roles:** all configured roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| "class description surfaces alchemical items define identity" completeness | TC-ALC-02 checks for keyword presence; whether the description communicates the identity well enough is a subjective editorial/UX review |
| Research field flavor text and bonus accuracy (Bomber: Calculated Splash/Expanded Splash prereqs) | Verifying feat prerequisite gating for Calculated/Expanded Splash requires those feats to exist in the system; automation can only verify that Bomber bonus slot is granted, not the downstream feat tree |
| Mutagenist higher-level drawback suppression ("can benefit from drawbacks being ignored per higher-level features") | The specific higher-level feature enabling this is not defined in the AC (only "per higher-level features"); cannot automate until the feature is specified |
| Complete formula book starter lists for each research field | Automation (TC-ALC-22) verifies that at least one level-0 and one level-1 formula exist per field; full completeness against PF2E source requires manual editorial review |
| Alchemical Weapon Expertise / Iron Will / Juggernaut / Evasion / Medium Armor proficiency steps | These are proficiency progression steps covered by the `dc-cr-character-class` proficiency test pattern; only the Alchemist-specific values (Expert at 7, Master at 11/15/19) need targeted level-feature tests |

---

## Regression risk areas

1. **`dc-cr-equipment-system` dependency**: Advanced Alchemy creates alchemical items — if equipment entity structure is not stable, item creation tests may fail. Flag dependency status at Stage 0 activation.
2. **`dc-cr-character-class` overlap**: Alchemist class node is seeded by `dc-cr-character-class`; if class seeding changes, Alchemist-specific features must still bind correctly.
3. **HP calculation overlap**: Alchemist HP = 8 + CON mod; verify this does not conflict with ancestry HP bonus or background HP additions in the character creation pipeline.
4. **Reagent count integrity**: level-up must recalculate reagent_count ceiling (level increases); verify reagent count is not capped to the level-1 value.
5. **Perpetual Infusions + Double Brew interaction**: Double Brew uses reagent batches; Perpetual Infusions are free — verify that perpetual items cannot be double-brewed at no cost (perpetual is one-at-a-time free, not batch-free).
6. **QA audit regression**: no new routes per security AC; if any are added, they must be probed in qa-permissions.json before release.
