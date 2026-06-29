# Implementation Notes (Dev-owned)
# Feature: dc-cr-character-class

## Summary
All NEW. `CharacterManager::CLASSES` constant exists with full class data (name, hp_per_level, key_ability, saves, attacks, defenses, class_features). No `character_class` Drupal content type currently exists. First slice creates the content type config + install hook seeding all 12 core PF2E classes. Can proceed in parallel with dc-cr-ancestry-system and dc-cr-background-system.

## Impact Analysis
- New `character_class` node type; no conflict with existing types.
- Seeding reads from `CharacterManager::CLASSES` — no duplication of data.
- Character creation step 3 in `CharacterCreationStepForm` handles class selection; will need wiring to new content type nodes.
- `buildCharacterJson()` in `CharacterManager` already reads class data from the CLASSES constant to set HP and proficiencies — that logic is not changed; the new node type is an authoritative content layer on top.

## Files / Components Touched
- `dungeoncrawler_content/config/install/node.type.character_class.yml`
- Field storage configs: name (title), description, key_ability (list_string multi-value), field_class_hp_per_level (int), field_class_proficiencies (text serialized JSON or structured fields), field_class_features (text long — JSON list of {level, name})
- `dungeoncrawler_content/dungeoncrawler_content.install` — seed 12 classes

## Data Model / Storage Changes
- Schema updates: new `character_class` node type
- Config changes: new fields
- Migrations: none — class stored as string in character JSON; node reference is optional enhancement

## First code slice
1. Create `node.type.character_class.yml` + field configs.
2. Seed 12 classes in install/update hook from `CharacterManager::CLASSES`.
3. Verify: `drush php-eval "print Drupal::entityQuery('node')->condition('type','character_class')->count()->execute();"` → expect `12`.

## Security Considerations
- Input validation: class nodes admin-only writable; public read.
- Access checks: class application requires auth (enforced in CharacterCreationStepForm).
- Sensitive data handling: none.

## Testing Performed
- Commands run: `drush cr` (cache rebuild clean), `drush php:eval` class count verification (16 nodes; 12 core PF2E + 4 extended)
- Targeted scenarios verified:
  - 12 core PF2E classes present in CharacterManager::CLASSES (all OK)
  - Fighter proficiencies: Expert perception, Expert fortitude, Trained reflex, Trained will
  - Champion key_ability option count = 2 (Strength, Dexterity) → triggers key ability radios
  - Invalid class ID 'paladin' not found in CLASSES → API returns 400 correctly

## Commits (2026-04-06)
- `30e62db8` — dc-cr-character-class: implement 3 missing AC gaps
  - `CharacterApiController`: invalid class ID → HTTP 400
  - `CharacterCreationStepForm` validateForm case 4: explicit `class_key_ability` validation with "You must choose a key ability for this class."
  - `CharacterCreationStepForm` submitForm step 4: store `class_proficiencies` from CLASSES constant into character JSON

## Rollback / Recovery
- Revert commit `30e62db8`. No schema changes; character JSON additions are backward-compatible.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after config install.
- Dependency note: dc-cr-character-creation must not start until ancestry, background, AND class are all merged.

## What I learned (Dev)
- `#required => TRUE` on AJAX-wrapped radios may not produce the AC-specified error message; always add explicit validateForm() enforcement for specific message requirements.
- Class proficiencies (Expert/Trained strings) are stored in `class_proficiencies` in character JSON; the `saves` block uses a flat formula — do not conflate proficiency strings with derived save integers.

## What I'd change next time (Dev)
- Map Expert/Trained proficiency strings to save bonus differentials (+4 vs +2) in the saves computation block to fully implement class proficiency differentiation at character creation.

## Completion pass — 2026-04-06 (dev-dungeoncrawler, commit 268f13349)

### Gaps found vs AC
1. `character_class` content type had no custom Drupal fields (data was PHP-constant-only).
2. Step 4 in controller `updateStepData` did not store `class_proficiencies` from CLASSES constant.
3. Step 4 in controller did not store 1st-level `class_features` from `CLASS_ADVANCEMENT`.
4. Validation error message was "Class selection is required." (AC requires "Class is required.").
5. Controller step 4 validation had no key_ability multi-option check with "You must choose a key ability for this class." message.

### Changes made
- `CharacterCreationStepController::updateStepData()` step 4: store `class_proficiencies` and `class_features` (L1 auto_features from CLASS_ADVANCEMENT) on character data at step save time.
- `CharacterCreationStepController` step 4 validation: exact error messages per AC ("Class is required.", "You must choose a key ability for this class.", invalid class ID error).
- `CharacterCreationStepForm` step 4: error message updated to "Class is required."
- `update_10032`: added 4 fields (field_class_hp_per_level, field_class_key_ability, field_class_proficiencies, field_class_features) to character_class content type; populated all 16 nodes from CLASSES/CLASS_ADVANCEMENT constants.

### Verification results
- 16 character_class nodes (12 core + 4 extended), all fields populated ✓
- Fighter: HP/level=10, L1 features=2 (Attack of Opportunity, Fighter Weapon Training) ✓
- Champion: key_ability="Strength or Dexterity" (multi-option trigger confirmed) ✓
- Site returns HTTP 200 ✓
