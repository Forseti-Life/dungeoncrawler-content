# Outbox: dc-cr-gnome-heritage-chameleon

- Date: 2026-04-19
- Completed by: ceo-copilot-2
- Feature: dc-cr-gnome-heritage-chameleon
- Release: 20260412-dungeoncrawler-release-r

## Summary

Added passive Stealth bonus behavior unit tests for the Chameleon Gnome heritage.
Implementation was already complete; this session closes the test-coverage gap for all AC items.

## Commits

- `535cad88e` — dc-cr-gnome-heritage-chameleon passive Stealth bonus tests

## Test coverage added

| Test | AC item |
|---|---|
| testChameleonBonusAppliedWhenTerrainMatches | +2 bonus when terrain_color_tag == coloration_tag |
| testChameleonBonusAbsentWhenTerrainMismatches | Bonus lost when terrain changes |
| testNonChameleonHeritageGetNoBonusInMatchingTerrain | Heritage guard works |
| testChameleonBonusDoesNotStackWithExistingCircumstanceBonus | Circumstance bonuses don't stack |

## Rollback

Revert `EncounterPhaseHandlerChameleonStealthTest.php` only.
No production code changed; zero runtime impact.

## Status

- Implementation: complete (prior sprint)
- Tests: all AC items now covered
- QA: qa-dungeoncrawler to verify in release-r integration pass
