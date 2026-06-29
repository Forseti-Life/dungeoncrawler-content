# Acceptance Criteria — Heritage Selection System
# Feature: dc-cr-heritage-system
# Date: 2026-02-28
# PM: pm-dungeoncrawler

## Gap analysis reference

Existing implementation in `/home/keithaumiller/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/`:
- `Service/CharacterManager.php::HERITAGES` — full heritage data for all 6 ancestries (28 heritages total), keyed by ancestry name, with `id`, `name`, `benefit` fields. **[EXTEND]**
- `Form/CharacterCreationStepForm.php` — heritage `<select>` field (step 1), `getHeritageOptions()` returns heritage dropdown filtered by selected ancestry, `updateHeritageOptions()` AJAX callback fires on ancestry change. UI already present and functional. **[EXTEND]**
- `Service/CharacterStateService.php` — stores `heritage` in `basicInfo` state. **[EXTEND]**
- **Missing**: enforcement that heritage must match chosen ancestry at validation/save time. **[NEW]**
- **Missing**: heritage is presented as `Optional` in current form description ("Optional: Select a heritage…"). PF2E requires exactly one heritage — must be mandatory. **[EXTEND]**
- **Missing**: heritage abilities (e.g., fire resistance for Forge Dwarf) applied mechanically to character stats. `benefit` field is display text only. **[NEW]** (out of scope for this feature — tracked in per-heritage feature stubs)

Feature type: **enhancement** (core UI+data exists; gaps are validation enforcement and required-field status)

## Happy Path

- [ ] `[EXTEND]` Heritage selection is **required** (not optional) in the character creation wizard. If a player attempts to proceed without selecting a heritage, the step fails validation with a clear error: "Please select a heritage for your character."
- [ ] `[EXTEND]` Heritage dropdown displays only heritages whose `parent_ancestry` matches the currently selected ancestry. When ancestry changes (via AJAX), the heritage dropdown resets and repopulates with matching options.
- [ ] `[TEST-ONLY]` Heritage selection is persisted to the character entity's `basicInfo.heritage` field upon step completion. The saved value is the heritage `id` (e.g., `ancient-blooded`, `forge`, `rock`, `strong-blooded` for dwarves).
- [ ] `[NEW]` Heritage selection is validated server-side: the submitted `heritage` id must exist in `CharacterManager::HERITAGES[<chosen_ancestry>]`. A mismatch (e.g., submitting `forge` when ancestry is `Elf`) returns a 400 with message "Invalid heritage for selected ancestry."
- [ ] `[TEST-ONLY]` The character summary/sheet displays the selected heritage name (not id). Example: a character with `ancestry: dwarf` and `heritage: ancient-blooded` shows "Ancient-Blooded Dwarf" in the character profile.
- [ ] `[TEST-ONLY]` A fully completed character creation step (ancestry + heritage both selected) allows progression to the next step (class/background selection).

## Edge Cases

- [ ] `[NEW]` If the player navigates back to step 1 after completing it (e.g., to change ancestry), the heritage field resets to empty and must be re-selected before proceeding. Heritage from a previous ancestry selection is cleared and must not carry over.
- [ ] `[TEST-ONLY]` For `Human` ancestry, only one heritage option is available (`Versatile Heritage`). The dropdown must show exactly one option (plus the "- Select -" placeholder) and auto-select is acceptable.
- [ ] `[TEST-ONLY]` Heritage options for each ancestry: Dwarf (4), Elf (4), Gnome (4), Goblin (4), Halfling (4), Human (1). Total 21 heritage options across all ancestries — verify dropdown counts match `CharacterManager::HERITAGES`.

## Failure Modes

- [ ] `[TEST-ONLY]` Attempting character creation submission with an empty `heritage` field returns a validation error (not a PHP exception). Error message is displayed inline, not as a 500 page.
- [ ] `[NEW]` Direct API call to character save endpoint with a mismatched `heritage`/`ancestry` combination is rejected with a 400 (not silently saved or 500 crash).
- [ ] `[TEST-ONLY]` Saving a character with a valid heritage + ancestry combination persists successfully and is retrievable via `GET /character/<id>` with the correct heritage id in the response.

## Permissions / Access Control

- [ ] `[TEST-ONLY]` Anonymous user cannot access the character creation form (`/dungeoncrawler/character/create` or equivalent) — must return 403 or redirect to login.
- [ ] `[TEST-ONLY]` Authenticated user can complete heritage selection and proceed. Administrator can also complete the flow.
- [ ] `[TEST-ONLY]` A player cannot change another player's heritage after character creation is complete. (Character ownership enforcement — tracked in character-creation AC, noted here for regression.)

## Data Integrity

- [ ] `[TEST-ONLY]` Heritage id stored is from the approved `CharacterManager::HERITAGES` list, never free-form text.
- [ ] Rollback path: heritage is stored in the character node's JSON field (`basicInfo`); revert via `git revert` of any migration affecting the character entity + `drush php-script` to clear malformed entries if needed.

## QA test path guidance

| Scenario | Roles to test | Expected result |
|---|---|---|
| Complete character creation with heritage selected | authenticated | Success; heritage saved |
| Submit step without heritage | authenticated | Validation error inline |
| Change ancestry after selecting heritage | authenticated | Heritage dropdown resets |
| API call with mismatched heritage/ancestry | authenticated | 400 response |
| Check dropdown count per ancestry | authenticated | 4/4/4/4/4/1 options |
| Anon access to character creation | anonymous | 403 or login redirect |

## Knowledgebase check
- `knowledgebase/lessons/20260228-ba-feature-type-defaults-new-without-gap-analysis.md` — gap analysis performed; feature correctly typed as enhancement (UI+data exist, enforcement missing)
- `features/dc-cr-ancestry-system/01-acceptance-criteria.md` — ancestry selection AC; heritage step follows ancestry step in same wizard
- `features/dc-cr-character-creation/01-acceptance-criteria.md` — end-to-end character creation flow; heritage is one step in that pipeline
