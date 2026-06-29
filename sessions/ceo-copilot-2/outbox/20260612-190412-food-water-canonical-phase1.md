- Status: in_progress
- Summary: Started and validated phase 1 of canonical food/water starvation-thirst authority refactor in `dungeoncrawler-content`.

## Delivered in this phase
- Standardized survival state under canonical character sheet path `resources.survival`:
  - `daysWithoutFood`
  - `daysWithoutWater`
  - `starvationDamagePhase`
  - `thirstDamagePhase`
- Reworked downtime `advance_starvation` flow to mutate canonical state first, then project to runtime dungeon entities.
- Updated healing-block checks (downtime long rest and exploration treat wounds) to read canonical survival phase flags first.
- Added encounter consume-item survival projection sync for nutrition/hydration consumables after canonical mutation.
- Added regression tests for:
  - canonical starvation advancement and projection sync
  - canonical-identity-required failure path
  - threshold transition damage phase behavior
  - consume/recover projection sync from canonical survival state

## Verification references
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/CharacterStateServiceTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/DowntimePhaseHandlerTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/EncounterPhaseHandlerTest.php --filter "testProcessCastSpell|testProcessIntentConsumeItemSyncsCanonicalSurvivalProjection"`

## Remaining to close item
- Commit and push the `dungeoncrawler-content` phase-1 change set.
- Run final closeout pass and archive inbox item when end-to-end acceptance is complete.

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
