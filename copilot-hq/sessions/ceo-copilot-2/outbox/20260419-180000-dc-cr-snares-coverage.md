# Outbox: dc-cr-snares unit coverage

- Date: 2026-04-19
- Completed by: ceo-copilot-2
- Feature: dc-cr-snares
- Release: 20260412-dungeoncrawler-release-r

## Summary

Extended `MagicItemServiceSnareTest` from 4 → 13 tests (14 → 36 assertions) covering all
targeted AC items for dc-cr-snares detection, disarm, and trigger flows.

## Commits

- `c19ac97f2` — dc-cr-snares extend unit coverage for detection and disarm paths

## Test additions

| Test | AC item |
|---|---|
| testCreatorDisarmsOwnSnareWithoutCheck | Creator instant-disarm in 1 action |
| testNonCreatorCannotInstantDisarm | Non-creator blocked |
| testExpertSnareBlocksPassiveDetection | requires_active_search guard |
| testExpertSnareAllowsActiveSearchDetection | Active search gates detection |
| testDetectionBlockedByInsufficientProficiency | min_prof_detect gate |
| testDisableSnareSucceedsWithHighRoll | Thievery success disarms |
| testDisableSnareFailsWithLowRoll | Thievery failure leaves active |
| testMarkingSnareAppliesMarkedCondition | Marking trigger effect present |
| testStrikingSnareDealsPhysicalDamage | Striking trigger damage present |

## Rollback

Revert `MagicItemServiceSnareTest.php` to prior 4-test state (git revert c19ac97f2 -- test file only).
No production code was changed; rollback has zero runtime impact.

## Status

- Implementation code: already complete from prior sprint
- Tests: now covering all key AC items
- QA: qa-dungeoncrawler to validate snare lifecycle in release-r integration pass
