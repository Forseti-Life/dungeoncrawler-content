- Status: done
- Summary: Closed CEO spell/focus canonical resource authority review item after shipping strict canonical-only spell/focus mutation paths.

## Completed outcome
- Inbox item closed: `20260606-dc-canonical-spell-focus-resource-review.md`
- Repository shipped: `dungeoncrawler-content`
- Commit: `f87c1e9` (pushed to `main`)

## Delivered scope
- Canonical character sheet is now required for encounter and exploration spell/focus resource mutation.
- Participant/entity snapshot fallback mutation for spell slot/focus spend was removed.
- Spell/focus projections are synchronized from canonical state after mutations.
- Unit tests added for canonical-required failure paths and canonical projection sync.

## Verification references
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/EncounterPhaseHandlerTest.php --filter "testProcessCastSpell"`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/ExplorationPhaseHandlerDetectMagicTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/ExplorationPhaseHandlerBorrowArcaneSpellTest.php`

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
