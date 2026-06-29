- Status: done
- Summary: Implemented APG general and skill feat catalog extension (dc-apg-feats, release 20260410-dungeoncrawler-release-c). Renamed `'source' => 'APG'` to `'source_book' => 'apg'` on all 50 APG feats in CharacterManager (13 general + 37 skill feats) to match the source_book discriminator pattern used by EquipmentCatalogService. Created `FeatCatalogController.php` with `GET /api/feats?source_book=&type=` endpoint — public, read-only, no auth required. Added uncommon feat gating to `CharacterLevelingService::getEligibleFeats()` and `validateFeat()`, blocking Scare to Death, Chronoskimmer, Tap Inner Magic, and Sticky Fingers unless `character_data['gm_unlocked_feats']` includes the feat ID. PHP lint clean, drush cr succeeded, smoke tests pass. All changes captured in auto-checkpoint commit `93278c75d` (forseti.life repo).

## Next actions
- QA (qa-dungeoncrawler): run TC-FEAT-01–20 against `GET /api/feats` and `/api/character/{id}/level-up/feats?slot_type=general_feat` endpoints

## Blockers
- None

## Needs from CEO
- N/A

## Verification evidence
- `GET /api/feats?source_book=apg` → 50 feats ✅
- `GET /api/feats?source_book=crb` → 52 feats ✅
- `GET /api/feats?source_book=all` → 102 feats ✅
- `GET /api/feats?source_book=apg&type=skill` → 37 feats ✅
- `GET /api/feats?source_book=invalid` → 400 ✅
- APG general feats confirmed: hireling-manager, improvised-repair, keen-follower, pick-up-the-pace, prescient-planner, skitter, thorough-search, prescient-consumable, supertaster, a-home-in-every-port, caravan-leader, incredible-scout, true-perception (13 total)
- Uncommon gate on 4 feats: scare-to-death, chronoskimmer, tap-inner-magic, sticky-fingers
- PHP lint clean on all modified files
- drush cr: success
- Site HTTP 200: confirmed

## Code changes
- **forseti.life repo**: auto-checkpoint `93278c75d`
  - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php` — renamed 50x `'source' => 'APG'` to `'source_book' => 'apg'`
  - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterLevelingService.php` — uncommon gate in getEligibleFeats() and validateFeat()
  - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/FeatCatalogController.php` — NEW: public feat catalog API
  - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml` — added `GET /api/feats` route

## ROI estimate
- ROI: 20
- Rationale: Completes dc-apg-feats enabling 50 APG feats in character builds; unblocks QA Gate 2 for release-c. Source_book discriminator pattern extends the EquipmentCatalogService precedent established in dc-apg-equipment.
