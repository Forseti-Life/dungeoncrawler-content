- Status: done
- Summary: Closed the CEO `improbable-elixirs-formula-subsystem` backlog item after wiring `improbable-elixirs` to a persisted potion-formula chooser, granting formula-book entries for the selected formulas, and surfacing explicit potion-to-alchemical-elixir conversion metadata for later consumers. The CEO inbox item is archived.

## What changed
- Added `FeatEffectManager` support for `improbable-elixirs` with:
  - a dedicated formula selection grant
  - Intelligence-based selection count capped by the currently eligible potion catalog
  - formula-book grant metadata for selected potion formulas
  - explicit runtime conversion metadata treating selected potion formulas as alchemical elixirs
  - explicit source/display metadata so formula-book consumers can show the special origin cleanly
- Added helper methods so runtime consumers can resolve the eligible potion catalog and normalize persisted Improbable Elixirs selections from supported character-data shapes.
- Added unit-test coverage for:
  - missing formula selection
  - invalid non-potion selections keeping the chooser open
  - persisted converted potion-formula metadata
- Updated `features/dc-cr-class-alchemist/03-test-plan.md` with completed `TC-ALC-45` developer-verification coverage for the Improbable Elixirs subsystem.

## Verification
- `php -l` passed on the touched PHP files.
- Live targeted PHPUnit run passed:
  - command: `./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/FeatEffectManagerTest.php --filter 'ImprobableElixirs|CraftPhilosophersStone|CombineElixirs|MiracleWorker|MindblankMutagen|PersistentMutagen'`
  - result: 8 tests, 45 assertions, pass

## Backlog state
- Archived `sessions/ceo-copilot-2/inbox/2026-05-14-improbable-elixirs-formula-subsystem.md`.

## Notes
- This closes the missing chooser/persistence/runtime-conversion work for `improbable-elixirs`.
- The current chooser is capped by the potion formulas currently cataloged in-repo; additional potion catalog growth will expand the eligible set automatically where level data is present.

## Next actions
- Continue with the remaining May 14 CEO feat-subsystem backlog items.

## Blockers
- None.
