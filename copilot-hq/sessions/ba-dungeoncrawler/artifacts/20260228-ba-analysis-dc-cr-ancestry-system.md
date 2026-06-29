# BA Analysis: dc-cr-ancestry-system

**Date:** 2026-02-28  
**Release:** 20260228-dungeoncrawler-release-next  
**Scope:** dc-cr-ancestry-system — PF2E ancestry selection during character creation

---

## Scope and non-goals

**In scope:**
- Select ancestry at step 2 of character creation
- Apply ancestry stat block to character: HP grant, size, speed, ability boosts/flaw, languages, traits, vision/senses
- Select one heritage per ancestry at step 2
- Select one level-1 ancestry feat at step 2
- Store all selections on the character entity

**Non-goals (this release):**
- Ancestry content entity migration (PHP constants acceptable for MVP per gap analysis recommendation)
- Higher-level ancestry feats (level 5+) — dc-cr-ancestry-feat-schedule deferred
- dc-cr-ancestry-traits (creature tags for targeting) — listed as `next` bucket in PM tracker
- dc-cr-dwarf-ancestry, dc-cr-dwarf-heritage-ancient-blooded — deferred per PM tracker

---

## Current implementation state (verified in codebase)

### What is implemented ✅

| Component | Location | State |
|---|---|---|
| `ANCESTRIES` constant (14 ancestries) | `CharacterManager.php` line 21 | ✅ All stat block fields present: hp, size, speed, boosts, flaw, languages, traits, vision |
| `HERITAGES` constant (6 ancestries) | `CharacterManager.php` line 41 | ✅ Dwarf, Elf, Gnome, Goblin, Halfling, Human have heritages |
| `ANCESTRY_FEATS` constant (7 ancestries) | `CharacterManager.php` line 81 | ✅ Human, Dwarf, Elf, Gnome, Goblin, Halfling, Orc have level-1 feats |
| Step 2 ancestry selection UI | `CharacterCreationStepForm.php` line 370 | ✅ Renders as select + ancestry detail cards |
| Step 2 heritage selection UI | `CharacterCreationStepForm.php` line 463 | ✅ Renders conditionally; optional (`#required => FALSE`) |
| Step 2 ancestry feat selection UI | `CharacterCreationStepForm.php` lines 490–530 | ✅ Renders as radio group; `#required => TRUE` when feats exist |
| Ancestry stat block applied at `buildCharacterJson()` | `CharacterManager.php` lines 790–884 | ✅ HP, size, speed, languages, traits, vision all written to character JSON |
| Ancestry boosts/flaw applied at `buildCharacterJson()` | `CharacterManager.php` lines 813–832 | ✅ Named boosts applied; flaw deducted |

### What is missing / broken ❌

| Gap | Where | Detail |
|---|---|---|
| `ancestry_feat` NOT saved to character | `CharacterCreationStepController.php` line 312: `field_mappings[2] = ['ancestry', 'heritage']` | Step 2 renders a required feat selection, but the controller field_mappings exclude `ancestry_feat`. The selected feat is discarded; `buildCharacterJson()` always produces `ancestry_feat: {name: '', description: ''}` |
| Heritage mechanical effects not structured | `CharacterManager::HERITAGES` — each entry has only `benefit` (plain text string) | Heritage is stored as a name string on the character (`heritage: ''`). No structured fields for resistance type/value, granted ability, or special rule. Heritage benefits are never applied to character stats. |
| `HERITAGES` missing for 8 ancestries | `CharacterManager.php` | Half-Elf, Half-Orc, Leshy, Orc, Catfolk, Kobold, Ratfolk, Tengu have no heritage entries. Selecting one of these ancestries shows no heritage options. |
| `ANCESTRY_FEATS` missing for 7 ancestries | `CharacterManager.php` | Half-Elf, Half-Orc, Leshy, Catfolk, Kobold, Ratfolk, Tengu have no feat entries. Ancestry feat section does not render for these ancestries at step 2. |
| Free ancestry boosts not player-selectable | `CharacterCreationStepController.php` | Human has `boosts: ['Free', 'Free']`; Tengu has `boosts: ['Dexterity', 'Free']`. In `buildCharacterJson()`, 'Free' boost entries are silently skipped (line 817: `if ($boost !== 'Free') {`). No step 2 sub-form lets the player choose their free ancestry boosts. Human characters lose both ancestry-level free boost choices entirely. |
| Heritage not required at step 2 | `CharacterCreationStepForm.php` line 477: `#required => FALSE` | PF2E rules require exactly one heritage per ancestry. The current form marks it as optional. A character can proceed with empty heritage. |

---

## Ancestry scope gap: 6 vs. 14

**Feature brief states:** "six core ancestries" (Dwarf, Elf, Gnome, Goblin, Halfling, Human).  
**`ANCESTRIES` constant contains:** 14 ancestries (adds Half-Elf, Half-Orc, Leshy, Orc, Catfolk, Kobold, Ratfolk, Tengu).

This is a scope ambiguity. The additional 8 ancestries are already in the data layer but have incomplete support (missing heritages, missing feats for most). PM decision needed.

**BA recommendation:** Scope this release to the 6 core ancestries. The UI should only render the 6 core options. The other 8 can remain in `ANCESTRIES` for future use but should not be selectable until heritages and feats are added for each. This avoids shipping a character with missing game mechanics.

---

## Unresolved PM decisions (before coding)

### Decision 1 (required): Ancestry scope — 6 core or all 14?

| Option | What ships | Risk |
|---|---|---|
| A — 6 core (Dwarf, Elf, Gnome, Goblin, Halfling, Human) | Complete ancestry support for all six (feats, heritages, stat blocks all present) | Limited ancestry variety at launch |
| B — 14 (all current ANCESTRIES entries) | Broader selection but 8 ancestries missing heritages; 7 missing feats; all 8 missing free boost selectors | Players selecting Orc, Catfolk, etc. get incomplete characters |

**BA recommendation: Option A.** Restrict step 2 to 6 core ancestries for this release. Expand when heritages/feats are complete for additional ancestries.

### Decision 2 (required): Free ancestry boost selection — where?

Human (2 free), Tengu (1 free), and potentially future ancestries have 'Free' boosts in their stat block. These need player selection.

| Option | Scope | Where |
|---|---|---|
| A — Sub-step at step 2 | After ancestry selection, show a "choose your free ancestry boost(s)" sub-form | Most accurate to PF2E rules (ancestry boosts are distinct from background/class boosts) |
| B — Merge into step 5 free boosts | Apply 'Free' ancestry boosts as additional slots in the existing step-5 pool | Simpler but conflates ancestry boosts with general ability boosts; not rules-accurate |
| C — Defer (restrict to non-free-boost ancestries for now) | Block Human/Tengu from selection until sub-step exists | Simplest; safe; reduces scope |

**BA recommendation: Option C for this release.** Since Human is in the 6-core list, this recommendation changes: implement Option A (sub-step at step 2) for Human because excluding Human would significantly hurt the product. A minimal two-boost selector (choose 2 different abilities) at step 2, rendered only when ancestry has `'Free'` boosts, is a contained addition.

### Decision 3 (required): Heritage required or optional?

PF2E rules: every character has exactly one heritage from their ancestry. The current form marks it optional.

**BA recommendation:** Set `#required => TRUE` for heritage at step 2. This is a minor form change. Heritage selection is core to the game system and should not be skippable.

---

## Implementation slice order (recommended for Dev)

**Slice 1 — Add `ancestry_feat` to step 2 field_mappings** (~1 hour)
- File: `CharacterCreationStepController.php` line 312: change `2 => ['ancestry', 'heritage']` to `2 => ['ancestry', 'heritage', 'ancestry_feat']`
- File: `CharacterManager::buildCharacterJson()` — update `ancestry_feat` struct to populate `name` and `description` from `ANCESTRY_FEATS` when `$options['ancestry_feat']` is provided
- Verify: complete step 2 for Dwarf, select 'Stonecunning' → character JSON has `ancestry.ancestry_feat.name = 'Stonecunning'`

**Slice 2 — Make heritage required; restrict to 6 core ancestries** (~2 hours, requires PM Decisions 1 + 3)
- File: `CharacterCreationStepForm.php` line 477: change `'#required' => FALSE` to `'#required' => TRUE` for heritage
- File: `CharacterCreationStepController::prepareAncestries()` or a separate filter list: restrict rendered ancestries to the 6 core list
- File: `CharacterCreationStepForm.php`: heritage radio group should only render when `HERITAGES[$ancestry]` exists (already conditional — this will be satisfied by restricting to 6 core ancestries)
- Verify: select Human → Versatile Heritage is the only option and is selected automatically or required; select an ancestry not in the 6-core list → that ancestry does not appear

**Slice 3 — Add free ancestry boost selector at step 2** (~4 hours, requires PM Decision 2)
- New sub-form section in `CharacterCreationStepForm.php` at step 2: if any boost in `$ancestry_data['boosts']` === 'Free', render a set of ability checkboxes limited to count('Free') selections
- New field: `ancestry_boosts` (array of selected ability names) saved to character_data at step 2
- File: `CharacterCreationStepController.php` line 312: add `'ancestry_boosts'` to field_mappings[2]
- File: `CharacterManager::buildCharacterJson()`: apply `ancestry_boosts` array as +2 to selected abilities when present
- Verify: Human player selects STR and DEX → character has STR 12, DEX 12 from ancestry boosts; step 5 still shows 4 free boosts (ancestry boosts are separate)

**Slice 4 — Structured heritage effects** (requires PM architecture decision from gap analysis + heritage benefit specs)
- Out of scope until: PM decides architecture (PHP constants vs. Drupal entities), and heritage benefit fields are specced per ancestry
- Prerequisite: dc-cr-heritage-system analysis (separate inbox item may be needed)

---

## Acceptance criteria (full definition of done for dc-cr-ancestry-system)

1. Step 2 renders exactly 6 ancestry choices: Dwarf, Elf, Gnome, Goblin, Halfling, Human
2. Selecting an ancestry shows the matching stat block (HP, size, speed, ability boosts/flaw, languages, vision)
3. Heritage selection is required; heritage options are filtered to the selected ancestry
4. Ancestry feat selection is required; feat options are filtered to the selected ancestry
5. Selected ancestry, heritage, and ancestry feat are all written to `character_data` and persisted in the character JSON
6. Human ancestry allows player to choose 2 free ability boosts at step 2 (or step 5 if PM approves Option B)
7. Completing step 2 produces correct derived stats: HP = ancestry_hp (no class yet); ability scores include ancestry boosts/flaw applied

**Verification commands / flow:**
```
# Happy path: Dwarf with Forge Dwarf heritage + Stonecunning feat
Step 1: name = "Test", concept = "warrior"
Step 2: ancestry = Dwarf, heritage = forge, ancestry_feat = stonecunning
→ character_data.ancestry.name === "Dwarf"
→ character_data.ancestry.heritage === "forge"  
→ character_data.ancestry.ancestry_feat.name === "Stonecunning"
→ character_data.ability_scores.constitution.score === 12 (10 + ancestry boost)
→ character_data.ability_scores.wisdom.score === 12
→ character_data.ability_scores.charisma.score === 8 (10 - flaw)

# Human free boost path
Step 2: ancestry = Human, select ancestry boosts: STR + DEX
→ character_data.ancestry_boosts = ["strength", "dexterity"]
→ character_data.ability_scores.strength.score === 12
→ character_data.ability_scores.dexterity.score === 12

# Edge case: heritage required
Step 2: ancestry = Elf, heritage = (none submitted)
→ form validation error: "Heritage is required"

# Edge case: ancestry feat required when feats exist
Step 2: ancestry = Goblin, ancestry_feat = (none submitted)
→ form validation error: "Ancestry Feat is required"
```

---

## Dependencies for downstream features

| Downstream feature | What it needs from dc-cr-ancestry-system |
|---|---|
| dc-cr-heritage-system | `ancestry` selection must be persisted first; heritage list must be filtered by ancestry |
| dc-cr-ancestry-feat-schedule | `ancestry_feat` persisted at character creation is the level-1 slot; further slots at levels 5/9/13/17 |
| dc-cr-character-creation | Step 2 completion gate: all three (ancestry, heritage, ancestry_feat) must be valid before proceeding to step 3 |
| dc-cr-conditions | `traits[]` from ancestry (e.g., 'Dwarf', 'Humanoid') used for condition targeting and effect filtering |
| dc-cr-encounter-rules | `speed` from ancestry used for Stride action; `senses` (darkvision) used for perception checks |
