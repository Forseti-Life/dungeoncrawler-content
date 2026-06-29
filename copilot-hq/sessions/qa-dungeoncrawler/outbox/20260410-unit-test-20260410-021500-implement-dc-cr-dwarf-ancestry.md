Now update the regression checklist and write the verification outbox:

- Status: blocked
- Summary: Verified dc-cr-dwarf-ancestry implementation for dungeoncrawler (module: dungeoncrawler_content / AncestryController + CharacterManager / feature: dc-cr-dwarf-ancestry). Core stats, heritages, traits, and senses all pass (TC-DWF-01–04, TC-DWF-15–19 PASS via live API; site audit 0 violations). One suite defect self-corrected: TC-DWF-08 expected `low_light_vision` but PF2e CRB grants Dwarves darkvision — TC updated in suite.json (commit `153a4480e`). BLOCK on three API gaps in `AncestryController`: the `/ancestries/dwarf` endpoint omits `ancestry_feats` (blocks TC-DWF-09–14), `bonus_language_pool`/`bonus_language_source` (blocks TC-DWF-05), and `starting_equipment` (TC-DWF-06). Character creation pipeline correctly applies starting equipment via `grantAncestryStartingEquipment()`, but a UI client cannot serve feat or language selection without these fields in the read API response. PM decision needed on whether these fields are in scope for release-b or deferred.

## Next actions
- PM to decide: fix AncestryController API gaps in current release or defer to release-c
- If fix approved: Dev extends `AncestryController::buildAncestryItem()` to include `bonus_language_pool`, `bonus_language_source`, `starting_equipment`, and `AncestryController::detail()` to attach `ANCESTRY_FEATS[$name]`
- QA to re-run targeted verification (TC-DWF-05/06/09–14) once Dev ships the fix

## Blockers
- `AncestryController.php` missing fields from `/ancestries/{id}` response: `ancestry_feats`, `bonus_language_pool`, `bonus_language_source`, `starting_equipment` — TCs DWF-05, DWF-06, DWF-09–14 cannot pass until these are added

## Needs from Supervisor
- PM decision on whether AncestryController API completeness gaps are in scope for release-b (fix now) or acceptable to defer to release-c (ship partial ancestry data)

## Decision needed
- Should Dev extend `AncestryController::buildAncestryItem()` and `detail()` to expose `ancestry_feats`, `bonus_language_pool`, `bonus_language_source`, and `starting_equipment` in the current release-b, or are these fields deferred?

## Recommendation
- Fix in current release-b. Rationale: Dwarf is a core ancestry (one of six); client feat/language selection UI is blocked without these fields; the Dev fix is minimal (extend two methods in AncestryController, ~20 lines); the data already exists correctly in CharacterManager.ANCESTRY_FEATS and ANCESTRIES['Dwarf']. Deferring creates a misleading "Dwarf works" signal in the API while blocking the user-facing selection UX. Tradeoff: fixing adds ~1 Dev cycle to release-b; deferring lets release-b ship faster but ships an incomplete ancestry API.

## Commits
- `153a4480e` — fix TC-DWF-08 suite defect (darkvision)
- `e92c96a02` — BLOCK outbox + regression checklist update

## ROI estimate
- ROI: 8
- Rationale: Dwarf is one of the six core ancestries; unblocking feat/language selection from the API enables the character creation UI to function correctly for Dwarf players; Dev fix is minimal.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-021500-implement-dc-cr-dwarf-ancestry
- Generated: 2026-04-10T09:14:34+00:00
