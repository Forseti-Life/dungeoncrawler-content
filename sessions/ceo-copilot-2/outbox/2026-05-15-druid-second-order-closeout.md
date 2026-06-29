- Status: done
- Summary: Closed the CEO `druid-second-order-subsystem` backlog item after wiring `order-explorer` to a persisted second-order chooser, granting structured focus-pool and feat-prerequisite metadata for the selected order, and surfacing explicit secondary-anathema scope for later consumers. The CEO inbox item is archived.

## What changed
- Added `FeatEffectManager` support for `order-explorer` with:
  - a dedicated second-order selection grant
  - validation that the chosen second order differs from the druid's primary order
  - focus-pool bonus metadata from the selected second order
  - granted level-1 order feat access metadata for the selected second order
  - explicit unlocked order-prerequisite metadata for later feat/runtime consumers
  - explicit secondary-order anathema text and “remove only second-order feats” scope metadata
- Added druid order helper methods so runtime consumers can resolve canonical druid order ids, normalized order metadata, and valid second-order selections from persisted character data.
- Added unit-test coverage for:
  - missing second-order selection
  - invalid primary-order state still requesting a second-order selection instead of granting benefits
  - persisted second-order benefits and anathema-scope metadata
- Updated `features/dc-cr-class-druid/03-test-plan.md` with developer-verification coverage notes for `TC-DRU-25`.

## Verification
- `php -l` passed on the touched PHP files.
- Live targeted PHPUnit run passed:
  - command: `./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/FeatEffectManagerTest.php --filter 'OrderExplorer|StormBorn|WildShapeDruid|Goodberry|HealAnimal|TempestSurge|LeshyFamiliarDruid|AnimalCompanionDruid'`
  - result: 11 tests, 49 assertions, pass

## Backlog state
- Archived `sessions/ceo-copilot-2/inbox/2026-05-14-druid-second-order-subsystem.md`.

## Notes
- This closes the missing chooser/persistence/runtime-access subsystem work for `order-explorer`.
- Full enforcement of secondary-order anathema suspension depends on later systems consuming the recorded runtime metadata.

## Next actions
- Continue with the remaining May 14 CEO feat-subsystem backlog items.

## Blockers
- None.
