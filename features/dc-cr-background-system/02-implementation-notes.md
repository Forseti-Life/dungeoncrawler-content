# Implementation Notes (Dev-owned)
# Feature: dc-cr-background-system

## Summary
All NEW. `CharacterManager::BACKGROUNDS` constant exists with full background data (name, fixed boost, free boost, skill training, lore, feat). No `background` Drupal content type currently exists. First slice creates the content type config + install hook seeding 5+ core backgrounds from the BACKGROUNDS constant. Can proceed in parallel with dc-cr-ancestry-system and dc-cr-character-class.

## Impact Analysis
- New `background` node type; no conflict with existing types.
- Seeding reads from `CharacterManager::BACKGROUNDS` constant — no duplication.
- Character creation step 2 in `CharacterCreationStepForm` handles background selection; that form will need to be wired to the new content type nodes (currently may use PHP constants directly).

## Files / Components Touched
- `dungeoncrawler_content/config/install/node.type.background.yml` — new content type config
- Field storage configs: name (title), description, fixed_boost (list_string), free_boost (note string), skill_training (string), lore_skill (string), skill_feat (string)
- `dungeoncrawler_content/dungeoncrawler_content.install` — seed 5 core backgrounds (Acolyte, Acrobat, Animal Whisperer, Artisan, Barkeep)

## Data Model / Storage Changes
- Schema updates: new `background` node type with fields per AC spec
- Config changes: none beyond new node type + fields
- Migrations: none for existing characters (background stored as string in character JSON)

## First code slice
1. Create `node.type.background.yml` + field configs in `config/install/`.
2. In `dungeoncrawler_content_install()` or `_update_N()`: iterate BACKGROUNDS, create at least 5 core background nodes.
3. Verify: `drush php-eval "print Drupal::entityQuery('node')->condition('type','background')->count()->execute();"` → expect `>= 5`.

## Security Considerations
- Input validation: background nodes are admin-only writable; public read.
- Access checks: background application to a character requires auth (enforced in CharacterCreationStepForm).
- Sensitive data handling: none.

## Testing Performed
- `drush updatedb --yes` — all 20 hooks (10011–10030) applied cleanly
- `drush cr` — success
- `drush php-eval "print \Drupal::entityQuery('node')->condition('type','background')->accessCheck(FALSE)->count()->execute();"` → 9 (AC requires >= 5) PASS
- `drush updatedb:status` → "No database updates required" PASS
- Background step in CharacterCreationStepForm: dropdown populated from `getBackgroundOptions()`, boosts selector present, validation at case 3 enforces required selection, unique boosts, and exact count of 2
- Fix required: `update_10011` and `update_10015` assumed `combat_encounters` and `combat_participants` tables existed; they were defined in schema but never materialized. Added `tableExists()` guards to create tables before attempting field adds.

## Rollback / Recovery
- Revert commit `664d0eb3`. Background nodes remain in DB; uninstalling the module via drush would remove the node type. Character JSON stores background as string — no foreign key corruption on rollback.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after config install.
- New lesson: update hooks that `addField` to tables defined in `schema()` must first check `tableExists()` and create the table if missing — the DB may not have been initialized on the first install pass.

## What I learned (Dev)
- Schema-defined tables are NOT automatically created when the module is already installed. Only `hook_install()` creates them. Subsequent `_update_N()` hooks must defensively create tables if missing.

## What I'd change next time (Dev)
- Add a `tableExists()` guard to every `_update_N()` that calls `addField()` on any schema-defined table.

## Completion pass — 2026-04-06 (dev-dungeoncrawler, commit ebf67c518)

### Gaps found vs AC
1. `BACKGROUNDS` constant had no `fixed_boost` key; model was 2 free boosts instead of 1 fixed + 1 free.
2. 4 AC-required backgrounds missing: Acrobat, Animal Whisperer, Artisan, Barkeep.
3. Error messages did not match AC exactly ("Background selection is required." / "Background boosts must be unique.").
4. Background content type had no custom Drupal fields (data was PHP-constant-only).

### Changes made
- `CharacterManager::BACKGROUNDS`: added `fixed_boost` to all 9 backgrounds; added 4 new backgrounds (13 total).
- `AbilityScoreTracker::applyBackgroundBoosts()`: refactored to apply fixed boost automatically + 1 free player boost; duplicate-boost error now matches AC exactly.
- `CharacterCreationStepController` step 3: updated to enforce 1 free boost for fixed-boost backgrounds; exact error messages per AC.
- `CharacterCreationStepForm` step 3: UI updated to show fixed boost and ask for 1 free boost.
- `update_10031`: added fields (field_bg_fixed_boost, field_bg_skill_training, field_bg_lore_skill, field_bg_skill_feat) to background content type; seeded 4 missing nodes; populated all 13.

### Verification results
- Background nodes: 13 (includes all 5 AC-required: Acolyte, Acrobat, Animal Whisperer, Artisan, Barkeep) ✓
- All custom fields populated on all 13 nodes ✓
- Duplicate boost error message exact match per AC ✓
- Site returns HTTP 200 post-update ✓
