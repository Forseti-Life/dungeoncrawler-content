# Closeout Evidence Checklist (Phase 5)

## Contracts touched (with enforcing paths)
1. **Room object canonical contract**
   - `src/Service/RoomGeneratorService.php`
2. **Room exits normalization + persistence contract**
   - `src/Service/RoomGeneratorService.php`
   - `src/Service/RoomLibraryService.php`
   - `src/Service/DungeonGeneratorService.php`
3. **Navigation runtime authority contract**
   - `src/Service/NavigationService.php`
   - `js/v2/services/action-rail-navigate-panel-service.js`
4. **Storyline stage-6/7 parity contract**
   - `src/Service/StorylineGenerationService.php`
   - `src/Service/StorylineManagerService.php`
   - `src/Controller/StorylineExplorerPageController.php`
5. **DB-authoritative runtime content contract (items + actors)**
   - `src/Service/StateValidationService.php`
   - `config/schemas/contract_registry.json`
   - `src/Controller/AnalysisExplorerPageController.php`

## Gate commands executed (2026-07-02 closeout refresh)
1. `node tests/navigation_runtime_cutover_contract_test.js` — **PASS** (7/7)
2. `node tests/action_rail_navigation_distance_contract_test.js` — **PASS** (8/8)
3. `node tests/room_generation_object_contract_test.js` — **PASS** (10/10)
4. `/var/www/html/dungeoncrawler/vendor/bin/phpunit -c phpunit.xml tests/src/Unit/Service/StorylineGenerationServiceTest.php --filter '/ValidateTaskContractRejectsCompositeWithoutChildren|ValidateEntityLinkageAcceptsCanonicalLocationIds|AssertValidGenerationBundleUsesGeneratedObjectiveControlChainValidation|ValidateEntityLinkageRejectsUndeclaredTargetIdActorReference|ValidateEntityLinkageIncludesCanonicalIndexLoadErrors/'` — **PASS** (5 tests)
5. `/var/www/html/dungeoncrawler/vendor/bin/phpunit -c phpunit.xml tests/src/Unit/Service/StorylineManagerServiceTest.php --filter '/ValidateObjectiveControlChainForGeneratedTemplates/'` — **PASS** (2 tests)
6. `/var/www/html/dungeoncrawler/vendor/bin/phpunit -c phpunit.xml tests/src/Unit/Controller/StorylineExplorerPageControllerTest.php --filter '/CollectTaskContractDiagnosticsFlagsCompositeWithoutChildrenAndDuplicateTaskIds|CollectEntityLinkageDiagnosticsValidatesTargetIdAndCanonicalIndexErrors/'` — **PASS** (2 tests)

## Hard-failure posture confirmation
- No fallback compatibility branches were added in scoped contract surfaces.
- Runtime item authority is DB-enforced and fails when canonical DB authority is unavailable or incomplete.
- Room/dungeon persistence remains fail-fast with transactional rollback to prevent partial-write masking.

## Ownership and phase completion status
- Phase evidence has been refreshed and recorded in `status.md`.
- Remaining ship checklist item: commit/push all touched repos for this work item before final `status: done`.

## Unresolved risks (explicit)
- PHPUnit deprecation warnings are present in targeted suites (non-failing); functional gate outcomes are currently green.

## Merge/ship rule
- Do not declare this item complete unless all required gate commands are green and changes are committed/pushed in every touched repo.
