- Status: done
- Summary: Closed the CEO `sorcerer-bloodline-granted-spells-subsystem` backlog item after implementing the canonical bloodline granted-spell layer, refactoring the sorcerer feat-effect wiring onto shared helpers, and passing the targeted live PHPUnit suite for the affected feat paths. The CEO inbox item is now archived.

## What changed
- Expanded `CharacterManager::SORCERER_BLOODLINES` into canonical metadata with:
  - rank-aware granted spells
  - bloodline spell metadata
  - blood magic descriptions
  - genie subtype-specific granted spell variants
- Added `FeatEffectManager` support for:
  - `bloodline-breadth`
  - `greater-bloodline`
  - shared sorcerer bloodline metadata/subtype resolution
  - normalized spell-id derivation for source spell names
- Refactored older sorcerer feat paths (`cantrip-expansion-sorcerer`, `greater-mental-evolution`, `crossblooded-evolution` label lookup) to reuse the shared bloodline metadata helpers.
- Added unit-test coverage for:
  - imperial `bloodline-breadth`
  - genie subtype-aware `bloodline-breadth`
  - imperial `greater-bloodline`

## Verification
- `php -l` passed on the touched PHP files during implementation/refactor.
- Direct PHP smoke checks passed during development for the affected feat overrides.
- Live targeted PHPUnit run passed:
  - command: `./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/FeatEffectManagerTest.php --filter 'BloodlineBreadth|GreaterBloodline|ArcaneEvolution|CrossbloodedEvolution|GreaterMentalEvolution'`
  - result: 9 tests, 48 assertions, pass

## Backlog state
- Archived `sessions/ceo-copilot-2/inbox/2026-05-14-sorcerer-bloodline-granted-spells-subsystem.md`.

## Next actions
- Continue with the remaining May 14 CEO feat-subsystem backlog items.

## Blockers
- None.
