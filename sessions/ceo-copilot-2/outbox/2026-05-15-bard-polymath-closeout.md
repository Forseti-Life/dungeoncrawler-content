- Status: done
- Summary: Closed the CEO `bard-polymath-signature-spell-subsystem` backlog item after wiring `esoteric-polymath` and `eclectic-polymath` onto a persisted special cross-tradition signature-spell path, exposing the daily-preparations swap metadata for the granted spell, and passing the targeted live PHPUnit slice for the adjacent polymath feat path. The CEO inbox item is archived.

## What changed
- Added `FeatEffectManager` support for `esoteric-polymath` with:
  - a dedicated cross-tradition spell-selection grant
  - persisted special repertoire-entry metadata
  - signature-spell treatment for the granted spell
  - daily-preparations swap action/state
- Added `FeatEffectManager` support for `eclectic-polymath` so it resolves the persisted Esoteric Polymath spell and upgrades that same spell to cast across any available rank without the normal spontaneous-repertoire restriction.
- Added unit-test coverage for:
  - missing Esoteric Polymath spell selection
  - persisted Esoteric Polymath spell + daily swap metadata
  - Eclectic Polymath consuming the persisted Esoteric Polymath spell
- Updated `features/dc-cr-class-bard/03-test-plan.md` with completed `TC-BRD-40` and `TC-BRD-41` verification coverage for the polymath feat chain.

## Verification
- `php -l` passed on the touched PHP files.
- Live targeted PHPUnit run passed:
  - command: `./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/FeatEffectManagerTest.php --filter 'VersatilePerformance|EsotericPolymath|VersatileSignature|EclecticPolymath|PolymathGreater|PolymathApex|SymphonyOfTheMuse'`
  - result: 8 tests, 43 assertions, pass

## Backlog state
- Archived `sessions/ceo-copilot-2/inbox/2026-05-14-bard-polymath-signature-spell-subsystem.md`.

## Next actions
- Continue with the remaining May 14 CEO feat-subsystem backlog items.

## Blockers
- None.
