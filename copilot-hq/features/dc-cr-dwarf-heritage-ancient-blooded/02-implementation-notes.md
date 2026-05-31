# Implementation Notes — dc-cr-dwarf-heritage-ancient-blooded

## Status
**Character-creation side: DONE** (commit `bf6fde2d`)
**Combat-reaction side: DEFERRED** — requires `CombatEngine::resolveSavingThrow()` (not yet built)

## Files Modified

### CharacterManager.php
Path: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

Changes:
1. **`HERITAGES['Dwarf']['ancient-blooded']`** — added `'granted_abilities' => ['call-on-ancient-blood']`
2. **`HERITAGE_REACTIONS` constant** — new constant with full reaction definition:
   - `id`: `call-on-ancient-blood`
   - `trigger`: triggered when making a saving throw against a magical effect
   - `effect_type`: `circumstance_bonus` on `saving_throw`
   - `bonus`: `+1`
   - `duration`: `end_of_turn`
   - `stacking_type`: `circumstance` (PF2e rule: only highest circumstance applies)
   - `includes_trigger`: `true` (you can use the reaction on the triggering save itself)
3. **`getHeritageGrantedAbilities(string $ancestry_canonical, string $heritage_id): array`** — returns `granted_abilities` for a heritage entry, or `[]` if none
4. **`isValidHeritageForAncestry(string $ancestry_canonical, string $heritage_id): bool`** — returns `true` if the heritage exists under that ancestry in `HERITAGES`

### CharacterCreationStepController.php
Path: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationStepController.php`

Changes in `updateStepData()` step 2 block:
- After ancestry stat processing: calls `CharacterManager::getHeritageGrantedAbilities()` and stores result in `$character_data['granted_abilities']`
- On re-selection: strips any previously granted heritage abilities from `granted_abilities` array, then grants the new ones — prevents stale grants if user changes heritage mid-session

Changes in `validateStepRequirements()` case 2:
- Validates submitted heritage against submitted ancestry using `CharacterManager::isValidHeritageForAncestry()`
- Returns HTTP 400 with error message on mismatch (e.g., selecting `ancient-blooded` for a non-Dwarf character)

## New Routes Introduced
None. Heritage abilities are stored in `character_data['granted_abilities']` (JSON column) and returned via the existing `loadCharacter` endpoint (`GET /api/character/load/{character_id}`).

## Character Data Key
`granted_abilities`: `string[]` — list of ability IDs granted by heritage selection. Stored in `character_data` (JSON in `dc_campaign_characters.character_data`). Populated at step 2; readable at any subsequent step.

## Acceptance Criteria Coverage

| AC Item | Status | Notes |
|---|---|---|
| 1. ancient-blooded available only to Dwarf | DONE | Client UI filtering (existing) + new server-side validation |
| 2. selecting grants call-on-ancient-blood in character_data | DONE | `granted_abilities` key populated at step 2 |
| Edge: non-Dwarf selection rejected with error | DONE | 400 returned from `validateStepRequirements()` |
| 3. Reaction triggers on magical saving throw | DEFERRED | Requires `CombatEngine::resolveSavingThrow()` |
| 4. +1 circumstance bonus applied to save | DEFERRED | Same blocker |
| 5. Circumstance bonuses do not stack | DEFERRED | Enforcement needed in saving throw resolution layer |
| 6. Reaction expenditure tracked (reaction_available = 0) | DEFERRED | `ActionProcessor::executeReactionAction()` infrastructure exists; trigger not hooked |
| 7. Bonus expires end of turn | DEFERRED | Same blocker |

## Combat-Side Blocker
`CombatEngine::resolveSavingThrow()` does not exist. The combat reaction pipeline (`CombatActionController::executeReaction()`) is a stub TODO. AC items 3–7 cannot be implemented until a dedicated combat-encounter-rules slice is shipped. Infrastructure notes:
- `reaction_available` column exists in `combat_participants`
- `ActionProcessor::executeReactionAction()` marks reaction as spent — already implemented
- `ConditionManager::getConditionModifiers()` handles circumstance bonuses via `checks` effect type

Recommend PM creates a follow-on feature (`dc-cr-dwarf-heritage-ancient-blooded-combat`) targeting the `CombatEngine::resolveSavingThrow()` slice.

## Pre-QA Permission Audit
No new routes added. Script `scripts/role-permissions-validate.py` exited 0 (base URL http://localhost:8080 not responding — expected in non-running dev environment). No new permission rules needed.

## PHP Syntax
Both files passed `php -l` with no errors prior to commit.

## Cache
`drush cr` succeeded after changes.
