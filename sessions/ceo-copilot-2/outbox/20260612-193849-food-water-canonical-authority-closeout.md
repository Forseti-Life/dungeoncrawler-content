- Status: done
- Summary: Closed CEO food/water canonical resource authority item after shipping strict canonical survival state and projection sync behavior.

## Completed outcome
- Inbox item closed: `20260606-dc-canonical-food-water-resource-review.md`
- Repository shipped: `dungeoncrawler-content`
- Commits (pushed to `main`):
  - `c337e96` — canonical survival authority implementation across downtime/exploration/encounter and test coverage
  - `d26b9aa` — strict canonical survival read refactor (removed legacy fallback reads) with regression tests

## Delivered scope
- Canonical food/water survival state is authoritative at `resources.survival`.
- Downtime starvation/thirst progression and consumable recovery paths read/write the same canonical survival contract.
- Runtime entity/participant survival fields are synchronized projections and no longer canonical authority.
- Healing gates and survival-condition effects are aligned with canonical survival damage-phase state.
- Regression tests cover advancement, threshold transitions, consume/recover resets, projection sync, and strict handling of legacy mirror fields.

## Verification references
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/CharacterStateServiceTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/DowntimePhaseHandlerTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/ExplorationPhaseHandlerDetectMagicTest.php /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/ExplorationPhaseHandlerBorrowArcaneSpellTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/EncounterPhaseHandlerTest.php --filter "testProcessCastSpell|testProcessIntentConsumeItemSyncsCanonicalSurvivalProjection"`

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
