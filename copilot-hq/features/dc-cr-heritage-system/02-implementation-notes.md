# Implementation Notes (Dev-owned)
# Feature: dc-cr-heritage-system

## Status
- Status: done (QA APPROVE — commit 287d1c745)

## Summary
EXTEND: Heritage data exists in `CharacterManager::HERITAGES` (28 heritages across 6 ancestries). UI in `CharacterCreationStepForm` has an AJAX dropdown with `getHeritageOptions()` and `updateHeritageOptions()`. Heritage is currently marked Optional. Gaps: (1) heritage field must be made required, (2) server-side validation that the submitted heritage id matches the chosen ancestry is missing. First slice fixes both in `CharacterCreationStepForm.php`.

## Impact Analysis
- Only `CharacterCreationStepForm.php` is touched in first slice — minimal diff.
- No schema changes needed (heritage stored in character JSON `basicInfo.heritage` — existing field).
- Making heritage required will cause character creation submissions without heritage to fail validation — this is correct behavior per AC.
- No change to HERITAGES constant or dropdown population logic (already filtering by ancestry).

## Files / Components Touched
- `dungeoncrawler_content/src/Form/CharacterCreationStepForm.php`:
  - Remove `// Optional` comment/description from heritage `<select>` field
  - Set `#required => TRUE` on heritage element
  - Add `#required_error` message: "Please select a heritage for your character."
  - In form validate handler (or submit): validate that submitted heritage id exists in `CharacterManager::HERITAGES[<chosen_ancestry>]`; return 400-equivalent Form API error if mismatched

## Data Model / Storage Changes
- Schema updates: none
- Config changes: none
- Migrations: none

## First code slice
1. In `CharacterCreationStepForm::buildForm()` (step 1/ancestry step):
   - Change heritage `#description` from "Optional: Select a heritage…" to "Select a heritage for your character. (Required)"
   - Set `'#required' => TRUE`
2. Add/extend the form validate handler:
   ```php
   $ancestry = $form_state->getValue('ancestry');
   $heritage = $form_state->getValue('heritage');
   $valid_heritages = array_keys(CharacterManager::HERITAGES[$ancestry] ?? []);
   if (!in_array($heritage, $valid_heritages, TRUE)) {
     $form_state->setErrorByName('heritage', 'Invalid heritage for selected ancestry.');
   }
   ```
3. Test with a blank heritage submission → expect inline validation error.
4. Test with mismatched ancestry/heritage → expect "Invalid heritage for selected ancestry."

## Security Considerations
- Input validation: server-side validation added (not just client-side `#required`).
- Access checks: `/characters/create` is auth-required (403 for anonymous per existing routing).
- Sensitive data handling: none.

## Testing Performed
- Commands run: `./vendor/bin/drush cr` → `[success] Cache rebuild complete.`
- Targeted scenarios:
  - Submit step 1 without heritage → validation error inline (heritage required check: `count($heritage_opts) > 1 && $submitted_heritage === ''`)
  - Submit with dwarf ancestry + elf heritage id → "Invalid heritage for selected ancestry." (`!array_key_exists($submitted_heritage, $heritage_opts)`)
  - Submit with dwarf ancestry + `forge` heritage → passes validation (key exists in filtered options)
  - Human ancestry → only `versatile-heritage` is valid (count is 2 with placeholder, so required enforced)
  - Anonymous access to /characters/create → 403 (existing routing enforcement)

## Rollback / Recovery
- Revert commit. Heritage field returns to optional. No data loss.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`

## What I learned (Dev)
- Heritage validation was using `getHeritageOptions()` result (keyed by heritage id) which is the correct whitelist — `array_key_exists()` against this filtered map is the right check rather than walking `HERITAGES` directly, because it automatically respects the ancestry filter.

## What I'd change next time (Dev)
- (pending)
