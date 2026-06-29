- Status: done
- Summary: Closed the CEO `sorcerer-blood-magic-damage-mapping-subsystem` backlog item after extending the canonical sorcerer bloodline metadata with stable blood-magic damage mappings, correcting the elemental legacy fire-vs-bludgeoning rule during review, wiring `bloodline-resistance` onto the shared resolver path, and passing the targeted live PHPUnit suite for the affected sorcerer feat paths. The CEO inbox item is archived.

## What changed
- Extended `CharacterManager::SORCERER_BLOODLINES` with canonical `blood_magic_damage_type` metadata for every supported bloodline.
- Added subtype-aware blood-magic damage mappings for:
  - draconic bloodlines (dragon lineage / direct damage-type variants)
  - elemental bloodlines (element / direct damage-type variants)
- Reused the shared sorcerer bloodline metadata helpers in `FeatEffectManager` to implement:
  - `bloodline-resistance`
  - broader subtype resolution for genie, draconic, elemental, and direct damage-type persistence shapes
- Added full-catalog unit coverage for `bloodline-resistance` across the currently supported sorcerer bloodlines.
- Corrected the elemental legacy mapping so blood magic resolves to `fire` for fire elementalists and `bludgeoning` for air/earth/water elementalists.
- Updated `features/dc-cr-class-sorcerer/03-test-plan.md` with the completed `TC-SOR-40` verification case for canonical blood-magic damage mapping.

## Verification
- `php -l` passed on the touched PHP files.
- Live targeted PHPUnit run passed:
  - command: `./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/FeatEffectManagerTest.php --filter 'BloodlineBreadth|GreaterBloodline|BloodlineResistance|TrueBlood|ArcaneEvolution|CrossbloodedEvolution|GreaterMentalEvolution'`
  - result: 12 tests, 124 assertions, pass

## Backlog state
- Archived `sessions/ceo-copilot-2/inbox/2026-05-14-sorcerer-blood-magic-damage-mapping-subsystem.md`.

## Next actions
- Continue with the remaining May 14 CEO feat-subsystem backlog items.

## Blockers
- None.
