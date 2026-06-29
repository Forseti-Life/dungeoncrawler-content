# Release Notes: 20260407-dungeoncrawler-release-b

- Release: 20260407-dungeoncrawler-release-b
- Site: dungeoncrawler
- Status: scope-locked — pending Gate 2 QA APPROVE
- Opened: 2026-04-07T17:53:10+00:00
- Auto-close triggered: 2026-04-07T18:23:37Z (FEATURE_CAP: 10/10 features in_progress)
- PM signoff: pending Gate 2 QA APPROVE

## Summary

Release-b scope is locked at 10 features (auto-close threshold). All 10 features are in_progress and tagged to this release. QA has 10 suite-activate inbox items pending — suite.json has 0 TCs until QA processes those items. Dev implementation and Gate 2 QA APPROVE are required before PM signoff can be issued.

Total test cases in scope: 163

## Features in scope

| Feature | TCs | Notes |
|---|---|---|
| dc-cr-conditions | 25 | foundational; no external deps |
| dc-cr-equipment-system | 22 | foundational; unblocks skills chain |
| dc-cr-difficulty-class | 17 | foundational; unblocks DC-check features |
| dc-cr-elf-ancestry | 18 | depends on dc-cr-languages (co-activated) |
| dc-cr-xp-award-system | 19 | PM decision: double-XP catch-up triggers on any level below party level (no minimum gap) |
| dc-cr-darkvision | 15 | standalone |
| dc-cr-languages | 14 | foundational; satisfies elf-ancestry dep |
| dc-cr-low-light-vision | 14 | standalone |
| dc-cr-elf-heritage-cavern | 13 | depends on dc-cr-elf-ancestry (co-activated) |
| dc-home-suggestion-notice | 6 | standalone; home page UX feature |

## Gate status

- Gate 1 (scope lock): COMPLETE — 10 features activated, QA suite-activate items dispatched
- Gate 1b (code review): pending
- Gate 2 (QA APPROVE): pending — QA must process 10 suite-activate inbox items, then Dev implements, then QA verifies
- PM signoff: blocked on Gate 2

## Open items before signoff

1. QA must process 10 suite-activate inbox items (dispatched 2026-04-07T18:12Z) to populate suite.json
2. Dev must implement all 10 features with commit hashes and rollback steps
3. QA must run Gate 2 verification and issue APPROVE (no new items for Dev)
4. PM to run: `./scripts/release-signoff.sh dungeoncrawler 20260407-dungeoncrawler-release-b`

## PM decisions on record

- dc-cr-xp-award-system: double-XP catch-up triggers on any level below party level (no minimum gap), per PF2E RAW

## BA data-extraction gaps (unresolved, pre-dev)

- dc-cr-xp-award-system: accomplishment XP values (minor/moderate/major) need BA extraction into 01-acceptance-criteria.md
