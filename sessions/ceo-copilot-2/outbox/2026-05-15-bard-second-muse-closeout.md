- Status: done
- Summary: Closed the CEO `bard-second-muse-subsystem` backlog item after wiring `true-facets` to a persisted second-muse chooser, granting the selected muse's feat and bonus spell through structured effect metadata, exposing explicit prerequisite-unlock state for the second muse, and then tightening the logic in review so the feat cannot resolve a second muse when the bard lacks a valid primary muse. The CEO inbox item is archived.

## What changed
- Added `FeatEffectManager` support for `true-facets` with:
  - a dedicated second-muse selection grant
  - validation that the chosen second muse differs from the bard's primary muse
  - bonus feat grant metadata for the selected second muse
  - bonus spell grant metadata for the selected second muse
  - explicit unlocked muse-prerequisite metadata for later systems to consume
- Added bard muse helper methods so runtime consumers can resolve bard muse ids and normalize muse-granted feat ids from the canonical bard class metadata.
- Added unit-test coverage for:
  - missing second-muse selection
  - invalid primary-muse state still requesting a second-muse selection instead of granting benefits
  - persisted second-muse feat/spell grants and prerequisite unlocks
- Updated `features/dc-cr-class-bard/03-test-plan.md` with completed `TC-BRD-42` and `TC-BRD-43` verification coverage for the second-muse subsystem.

## Verification
- `php -l` passed on the touched PHP files.
- Live targeted PHPUnit run passed:
  - command: `./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/FeatEffectManagerTest.php --filter 'SymphonyOfTheMuse|TrueFacets|EsotericPolymath|EclecticPolymath'`
  - result: 7 tests, 47 assertions, pass

## Backlog state
- Archived `sessions/ceo-copilot-2/inbox/2026-05-14-bard-second-muse-subsystem.md`.

## Next actions
- Continue with the remaining May 14 CEO feat-subsystem backlog items.

## Blockers
- None.
