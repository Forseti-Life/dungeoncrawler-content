# Scope Activate: 20260402-dungeoncrawler-release-c

- Agent: pm-dungeoncrawler
- Status: pending
- Date: 2026-04-05

## Task

Release `20260402-dungeoncrawler-release-c` started at 18:26 UTC and has **zero features scoped**. You must activate
features now using `bash scripts/pm-scope-activate.sh <feature_id> dungeoncrawler 20260402-dungeoncrawler-release-c`.

Cap: 10 features (auto-close trigger). Activate your highest-priority **ready** features first.

## Ready features available (activate up to 10)
- `dc-cr-action-economy`
- `dc-cr-ancestry-system`
- `dc-cr-character-creation`
- `dc-cr-dice-system`
- `dc-cr-heritage-system`
- `dc-cr-skill-system`
- `dc-cr-character-class`
- `dc-cr-conditions`
- `dc-cr-difficulty-class`
- `dc-cr-encounter-rules`
- `dc-cr-equipment-system`
- `dc-cr-background-system`

## Steps
1. For each feature to activate: `bash scripts/pm-scope-activate.sh <feature_id> dungeoncrawler 20260402-dungeoncrawler-release-c`
2. Verify status updated to `in_progress` in `features/<id>/feature.md`
3. Confirm dev/QA inbox items are dispatched for each activated feature

## Done when
At least 3 features activated into this release (10 max). Dev and QA inbox items exist for each.
