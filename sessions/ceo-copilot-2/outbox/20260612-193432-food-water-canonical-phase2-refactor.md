- Status: in_progress
- Summary: Completed phase-2 review/refactor pass to enforce strict canonical survival reads for food/water state.

## Delivered in this phase
- Removed canonical survival read fallbacks to legacy top-level mirrors in:
  - `CharacterStateService::normalizeSurvivalResourceState()`
  - `DowntimePhaseHandler::readCanonicalSurvivalState()`
  - `ExplorationPhaseHandler::readCanonicalSurvivalState()`
  - `EncounterPhaseHandler::readCanonicalSurvivalStateFromCanonicalState()`
- Canonical survival state now resolves only from `resources.survival`.
- Added strict-standardization regression tests:
  - `CharacterStateServiceTest::testGetStateIgnoresLegacySurvivalMirrorFields`
  - `DowntimePhaseHandlerTest::testAdvanceStarvationIgnoresLegacyCanonicalMirrorFields`

## Verification references
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/CharacterStateServiceTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/DowntimePhaseHandlerTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/ExplorationPhaseHandlerDetectMagicTest.php /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/ExplorationPhaseHandlerBorrowArcaneSpellTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/EncounterPhaseHandlerTest.php --filter "testProcessCastSpell|testProcessIntentConsumeItemSyncsCanonicalSurvivalProjection"`

## Remaining to close item
- Optional closeout decision: archive inbox item once all stakeholders accept strict non-backward-compatible canonical contract.

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
