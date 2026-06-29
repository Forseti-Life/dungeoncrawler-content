- Status: done
- Summary: Finalized Dungeoncrawler spellbook/feat write-path persistence so canonical `spells` and `features.*` remain authoritative while legacy mirrors are derived-only compatibility outputs.

## What changed
- Added `CharacterManager::synchronizeCompatibilityMirrors()` as the shared boundary helper for compact feat refs and top-level spell mirrors.
- Routed `CharacterLevelingService` persistence through the shared mirror sync and canonical spell-slot/focus normalization.
- Routed `CharacterStateService` saves through the same compatibility-mirror sync before persisting PC character data.
- Fixed `buildQuickPlaySpellData()` to load its class slot config before deriving cantrip/first-rank selections.
- Added focused regressions covering leveling mirror sync and state-save mirror persistence.

## Verification
- `vendor/bin/phpunit --bootstrap /home/ubuntu/forseti.life/dungeoncrawler-content/tests/bootstrap.php /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/CharacterLevelingServiceTest.php /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/CharacterStateServiceTest.php /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/CharacterManagerSpellCatalogTest.php /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/FeatLibraryServiceTest.php`
- `node tests/hexmap_spell_normalization_test.js`

## Blockers
- None.
