# Implementation Notes (Dev-owned)
# Feature: dc-cr-ancestry-system

## Summary
NEW content type + EXTEND existing data: `CharacterManager::ANCESTRIES` and `::HERITAGES` contain all required data as PHP constants. No `ancestry` Drupal content type currently exists (confirmed: node types are ai_conversation, article, community_suggestion, page). First slice creates the `ancestry` content type via a config YAML + an install hook that seeds 6 core ancestries by iterating `CharacterManager::ANCESTRIES`. The `GET /ancestries` and `GET /ancestries/{id}` routes follow in subsequent slices.

## Impact Analysis
- New `ancestry` node type does not conflict with existing types.
- `CharacterManager::ANCESTRIES` is not duplicated — seed hook reads from the constant.
- `CharacterCreationStepForm` ancestry step already exists but relies on PHP constants; may need route-based API fallback for API consumers.

## Files / Components Touched
- `dungeoncrawler_content/config/install/node.type.ancestry.yml` — new content type config
- `dungeoncrawler_content/config/install/field.storage.node.field_ancestry_*.yml` — field storage for hp, size, speed, boosts, flaws, languages, senses
- `dungeoncrawler_content/dungeoncrawler_content.install` — `hook_install()` or `hook_update_N()` to seed 6 core ancestry nodes
- `dungeoncrawler_content/dungeoncrawler_content.routing.yml` — add `GET /ancestries` and `GET /ancestries/{id}` routes (slice 2)
- New controller `dungeoncrawler_content/src/Controller/AncestryController.php` (slice 2)

## Data Model / Storage Changes
- Schema updates: new `ancestry` node type with fields: name (title), field_ancestry_hp (int), field_ancestry_size (list_string), field_ancestry_speed (int), field_ancestry_boosts (list_string multi-value), field_ancestry_flaws (list_string), field_ancestry_languages (list_string multi-value), field_ancestry_senses (list_string multi-value)
- Config changes: `core.extension` will include new field configs
- Migrations: none for existing characters (ancestry stored in character JSON by name string, not by node reference — confirm before adding entity reference)

## First code slice
1. Create `node.type.ancestry.yml` in `config/install/`.
2. Create field storage + instance configs for ancestry fields.
3. In `dungeoncrawler_content_install()` or `_update_N()`: iterate `CharacterManager::ANCESTRIES`, create one node per ancestry (6 core: dwarf, elf, gnome, goblin, halfling, human).
4. `drush php-eval` verification: expect count = 6.

## Security Considerations
- Input validation: ancestry content type nodes are admin-only writable; public read via view permission.
- Access checks: route `/ancestries` returns public data (no auth needed for read).
- Sensitive data handling: none.

## Testing Performed
- Commands run:
  - `drush php:eval "print entityQuery...->count()->execute();"` → `6` ✅
  - `drush php:eval` spot-check: Dwarf hp=10, speed=20, boosts=[Constitution,Wisdom], flaw=Charisma ✅
  - `curl http://localhost:8080/ancestries?_format=json` → 200, 6 ancestries, all required keys present ✅
  - `curl http://localhost:8080/ancestries/dwarf?_format=json` → hp:10, speed:20, heritages_count:4 ✅
- Targeted scenarios:
  - After update hook 10030: count=6 confirmed
  - `GET /ancestries` returns all 6 with correct fields (id, name, hp, size, speed, boosts, flaw, languages, senses, traits)
  - `GET /ancestries/dwarf` returns `hp:10, speed:20, boosts:[Constitution,Wis], flaw:[Charisma]`, heritages array present
  - Human: `boosts:[Free,Free], flaw:null, hp:8, speed:25` ✅

## Rollback / Recovery
- Revert commit + `drush php-script` to delete seeded ancestry nodes if needed.
- Uninstalling module leaves character nodes intact (ancestry stored as string in character JSON).

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after config install.

## Stage-0 confirmations (per test plan)

1. **Route paths**: `GET /ancestries` → `dungeoncrawler_content.api.ancestries_list`, `GET /ancestries/{id}` → `dungeoncrawler_content.api.ancestry_detail`. Both registered with `_access: TRUE` (public read). Confirmed: routes match expected URL structure ✅
2. **Storage format**: Ancestry attributes stored as multi-value string fields (`field_ancestry_boosts`, `field_ancestry_flaws`, `field_ancestry_languages`, `field_ancestry_senses`) + single-value int/string fields (`field_ancestry_hp`, `field_ancestry_speed`, `field_ancestry_size`). Human boosts stored as `['Free','Free']`; no flaw entry. Matches AC spec ✅
3. **Boost/flaw mechanics**: Human free-boost logic in `CharacterCreationStepController::validateStepRequirements` (case 2) validates duplicate selections (`ancestry_boosts must be unique`) and boost/flaw conflicts. Step 2 processing block in `updateStepData` applies Free boost selections from `ancestry_boosts` form field indexed positionally. Edge case: re-selection reversal tracked via `_prev_ancestry` and `_prev_ancestry_free_boosts` in character data ✅
4. **Human free-boost API shape**: `GET /ancestries/human` returns `boosts:["Free","Free"], flaw:null`. Client must submit `ancestry_boosts` array with 2 distinct ability names. Validation rejects duplicates (422) and boost/flaw conflicts (422). Confirmed contract matches documented shape ✅

## What I learned (Dev)
- The ancestry content type and 6 seed nodes already existed before this ticket (created by update hook 10016). This ticket added the discrete field storage layer on top of the existing title-only nodes.
- `resolveAncestryCanonicalName()` handles case-insensitive slug resolution (e.g. "dwarf" → "Dwarf"); use it as the single validation gate for ancestry IDs.
- Field storage + instance must both exist before setting values on nodes; FieldStorageConfig::loadByName check prevents duplicate creation on re-run.

## What I'd change next time (Dev)
- Gate the step 2 processing block on `$canonical !== ''` before looking up in ANCESTRIES — this prevents silent no-ops on invalid ancestry IDs that slip past validation somehow.
