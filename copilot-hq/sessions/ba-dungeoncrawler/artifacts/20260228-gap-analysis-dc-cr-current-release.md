# BA Handoff: Gap Analysis — dc-cr Current Release Features
**Date:** 2026-02-28  
**Release:** 20260228-dungeoncrawler-release-next  
**Features scoped:** dc-cr-action-economy, dc-cr-ancestry-system, dc-cr-dice-system, dc-cr-difficulty-class, dc-cr-background-system, dc-cr-character-class, dc-cr-character-creation, dc-cr-conditions, dc-cr-encounter-rules, dc-cr-equipment-system, dc-cr-heritage-system, dc-cr-skill-system

---

## Methodology

Reviewed all 12 feature.md files plus live codebase at:
- `forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/`
- `forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/`

---

## Summary Table

| Feature | Impl state | Key gap in code | Unresolved decision | BA recommendation |
|---|---|---|---|---|
| dc-cr-action-economy | Partial — `actions_remaining` tracked and validated in `EncounterAiIntegrationService` | `reaction_available` boolean not tracked per-turn; reactions not reset each turn | None | Add `reaction_available: bool` to turn state model in encounter engine; reset to `true` at start of each turn |
| dc-cr-ancestry-system | Partial — data in `CharacterManager::ANCESTRIES` (PHP const), step 2 captures ancestry | Not a Drupal content entity; can't be managed via CMS admin without code changes | **Architecture: PHP constants vs. Drupal content entities** (see § Architecture Decision below) | Keep constants for MVP; defer content entity migration post-launch |
| dc-cr-dice-system | Solid — `NumberGenerationService` has `rollNotation()`, `rollPathfinderDie()`, `rollPercentile()`, `rollMultiple()` | No HTTP API endpoint (`POST /dice/roll`); no per-roll audit log with context (character id, roll type) | None | Wrap `NumberGenerationService::rollNotation()` in a controller endpoint; add optional context param for logging |
| dc-cr-difficulty-class | **Correction (2026-02-28):** `CombatCalculator::calculateDegreeOfSuccess()` is **fully implemented** (used by ActionProcessor). `Calculator::determineDegreeOfSuccess()` is a stub but is NOT used by the combat pipeline. | Four-degree logic is already present; no implementation needed for basic encounter resolution | None | No action needed for combat. `Calculator::determineDegreeOfSuccess()` stub can be removed or delegated to `CombatCalculator` if needed outside combat |
| dc-cr-background-system | Partial — `CharacterManager::BACKGROUNDS` hardcoded (9 backgrounds), step 3 captures `background` + `background_boosts` | Skill training grant from background is NOT written to `character.skills` during step 3; only ability boosts are captured | None | Step 3 save handler must also write the background's `skill` field to `character.skills[skill] = 'trained'` |
| dc-cr-character-class | Partial — 12 classes hardcoded in `CharacterManager::CLASSES` with `trained_skills` counts; step 4 captures class | No step for skill training selection — player never chooses which skills to be trained in | **Is skill selection its own creation step, or applied at step finalization?** (PM decision) | Add a dedicated skill selection step immediately after step 4 (new step 4b or step 5 shift) where player picks N skills = `class.trained_skills + Intelligence modifier` |
| dc-cr-character-creation | Partial — 8-step flow exists; steps 1-8 mapped in `updateStepData()` | No step for skill selection; no step for ancestry feat selection (step 5 = free ability boosts, then jumps to step 6 = flavor) | Same as dc-cr-character-class (skill step) | Insert skill selection after step 4 (class); insert ancestry feat selection before or after; finalize derived stats (HP, saves, perception) at step 8 completion |
| dc-cr-conditions | Partial — `ConditionManager` implements `applyCondition()`, `removeCondition()`, `applyConditionEffects()` | `applyConditionEffects()` switch handles `frightened` and `flat_footed`; `dying` processing exists; full PF2E conditions catalog (grabbed, stunned, slowed, enfeebled, clumsy, drained, sickened, etc.) not confirmed present | None | Dev to audit `applyConditionEffects()` switch against full PF2E conditions appendix; log missing conditions as separate sub-tasks |
| dc-cr-encounter-rules | Partial — `calculateMAP()`, `calculateAttackBonus()`, `calculateInitiative()`, `sortInitiativeOrder()` all exist | `EncounterAiIntegrationService::validateRecommendation()` validates `action_cost`; `CombatCalculator::calculateDegreeOfSuccess()` is implemented and used — encounter resolution is functional for strikes | None | No degree-of-success blocker. Next gap: verify 2-action/3-action activity cost enforcement in ActionProcessor |
| dc-cr-equipment-system | Partial — `InventoryManagementService` with weapon/armor categorization and `BULK_MAP`; step 7 = equipment selection from templates | `traits[]`, weapon group, armor dex cap, damage dice fields not confirmed present on equipment item schema | None | Dev to confirm `dc_campaign_item_instances` table and template item tables have all combat-relevant fields (traits, weapon_group, damage_dice, dex_cap); add missing fields |
| dc-cr-heritage-system | Partial — `CharacterManager::HERITAGES` hardcoded (multiple per ancestry); step 2 captures heritage | Same architecture concern as ancestry (PHP const, not content entity) | Same as dc-cr-ancestry-system architecture decision | Match dc-cr-ancestry-system decision; keep constants for MVP |
| dc-cr-skill-system | Partial — proficiency rank bonuses fully implemented in `CharacterCalculator::calculateProficiencyBonus()`; skills on character entity | No `POST /character/skill-check` API endpoint; skill training selection not applied to character entity during creation | None | Add skill check API endpoint (input: character_id, skill, dc → output: roll, total, degree_of_success); ensure skill training from class + background is written to character entity at creation finalization |

---

## Architecture Decision Required (PM-owned)

**Issue type (matrix):** Acceptance criteria ambiguity / product intent conflict  
**Affects:** dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-background-system, dc-cr-character-class

The feature stubs describe these as **Drupal content types** (`ancestry`, `background`, `character_class`, `heritage`). The current implementation stores all data as **PHP constants** in `CharacterManager`.

| Option | Pros | Cons |
|---|---|---|
| Keep PHP constants (current) | Zero migration work; all 4 features are ~80% done today | Content not manageable via Drupal admin; adding new ancestry requires code deploy; BA/PM can't update data independently |
| Migrate to Drupal content entities | CMS-manageable; aligns with feature stubs; proper field API | Significant rework; need migration scripts; adds complexity to API layer |

**PM decision needed:** Which architecture for this release? Recommend keeping constants for MVP launch and scheduling content entity migration as a post-launch epic. If PM agrees, update all four feature.md files to say "data source: CharacterManager constants" and mark the entity migration as a separate future feature.

---

## Dev implementation sequencing (recommended)

1. ~~**Fix `Calculator::determineDegreeOfSuccess()`**~~ — **Correction:** `CombatCalculator::calculateDegreeOfSuccess()` is already fully implemented and used by ActionProcessor. No fix needed. `Calculator::determineDegreeOfSuccess()` stub is on an unused code path for combat.

2. **Add `reaction_available` to turn state** — dc-cr-action-economy. Small, well-scoped change to encounter turn model.

3. **Apply background skill training in step 3 save handler** — dc-cr-background-system. Write `background.skill → character.skills[skill] = 'trained'` at step 3.

4. **Insert skill selection step into character creation** — resolves dc-cr-character-class + dc-cr-character-creation + dc-cr-skill-system gap simultaneously. New step: player selects N skills where N = `class.trained_skills + INT modifier`.

5. **Add `POST /dice/roll` endpoint** — dc-cr-dice-system. Wraps existing `NumberGenerationService`.

6. **Add `POST /character/skill-check` endpoint** — dc-cr-skill-system. Uses dice engine + proficiency calc + DC comparison.

7. **Audit ConditionManager conditions coverage** — dc-cr-conditions. Compare switch statement against PF2E conditions appendix list.

8. **Confirm equipment item schema fields** — dc-cr-equipment-system. Verify or add: `traits[]`, `weapon_group`, `damage_dice`, `dex_cap`.

---

## Acceptance criteria (per feature, by Dev/QA)

### dc-cr-action-economy
- Turn state has `actions_remaining` (0-3) and `reaction_available` (bool)
- Both reset at start of each turn
- Verify: start turn → actions_remaining=3, reaction_available=true; take 2 actions → actions_remaining=1; use reaction → reaction_available=false

### dc-cr-dice-system
- `POST /dice/roll` with body `{"expression":"2d6+3"}` returns `{"dice":[3,5],"modifier":3,"total":11}`
- Supports d4, d6, d8, d10, d12, d20, d% (d100)
- Verify: integration test hitting the endpoint with each notation

### dc-cr-difficulty-class
- **Correction (2026-02-28):** `CombatCalculator::calculateDegreeOfSuccess()` is fully implemented (four-degree logic + nat-20/nat-1 shifts). The stub at `Calculator::determineDegreeOfSuccess()` is not used by the combat pipeline. This feature is substantially complete for combat use.
- Verify: inspect `CombatCalculator::calculateDegreeOfSuccess()` — crit_success/success/failure/crit_failure cases present with nat-20/nat-1 bumps ✅

### dc-cr-background-system
- After step 3 save: `character.skills[background.skill] = 'trained'`
- Background ability boosts applied to ability scores
- Verify: create character with Acolyte background → character.skills.Religion = 'trained'

### dc-cr-character-class
- After step 4: `character.class` set
- New skill selection step renders N skill choices = class.trained_skills + INT mod
- Selected skills written to `character.skills[]`
- Verify: Fighter (trained_skills=3) + INT mod 0 → player picks exactly 3 skills

### dc-cr-character-creation
- 8-step flow completes with: name, ancestry, heritage, background, class, ability scores, skills, equipment, flavor
- Final character has derived stats: HP (ancestry HP + class HP per level + CON mod), AC (10 + DEX mod + armor + proficiency + level), perception (trained + WIS mod + level)
- Verify: complete creation flow start to finish; final character JSON has all stat fields populated

### dc-cr-conditions
- All PF2E conditions from the conditions appendix are representable in ConditionManager
- Each condition applies correct stat modifications
- Verify: apply frightened 2 → -2 to attack rolls, DCs, saves; end of turn → frightened reduces by 1; at frightened 0 → removed

### dc-cr-encounter-rules
- Initiative roll (Perception check) generates sorted turn order
- Attack roll = d20 + attack bonus vs. target AC; uses `determineDegreeOfSuccess()`
- MAP applied: 0 / -5 / -10 (or -4/-8 for agile)
- Conditions (flat-footed, grabbed) modify AC/checks correctly
- Verify: run a 2-combatant mock encounter through 3 rounds; all degrees of success output correctly

### dc-cr-equipment-system
- Equipment items have: `type`, `traits[]`, `weapon_group`, `damage_dice`, `damage_type`, `bulk`, `price`, `ac_bonus` (armor), `dex_cap` (armor)
- Starting equipment from step 7 written to character inventory
- Verify: equip a longsword → combat engine reads 1d8 slashing, martial, sword weapon group

### dc-cr-heritage-system
- Step 2 enforces exactly one heritage per ancestry
- Heritage-granted ability is stored on character entity
- Verify: select Ancient-Blooded Dwarf heritage → character.heritage = 'ancient-blooded-dwarf'; heritage ability listed in character abilities

### dc-cr-ancestry-system
- Step 2 applies ancestry HP grant, size, speed, ability boosts/flaw, starting languages, traits to character entity
- Six ancestries available: dwarf, elf, gnome, goblin, halfling, human
- Verify: select Dwarf → HP grant = 10, speed = 20, CON/WIS boosts applied, CHA flaw applied, languages = [common, dwarven]

### dc-cr-skill-system
- 17 skills on character entity with proficiency ranks (untrained/trained/expert/master/legendary)
- `POST /character/skill-check` accepts character_id, skill, dc → returns roll, total, degree_of_success
- Verify: trained Athletics (Trained=+2+level), DC 15 → correct degree returned
