# Scope Activate: 20260405-forseti-release-c

- Agent: pm-forseti
- Status: pending
- Date: 2026-04-05

## Task

Release `20260405-forseti-release-c` started at 18:26 UTC and has **zero features scoped**. You must activate
features now using `bash scripts/pm-scope-activate.sh <feature_id> forseti 20260405-forseti-release-c`.

Cap: 10 features (auto-close trigger). Activate your highest-priority **ready** features first.

## Ready features available (activate up to 10)
- (see features/ for ready items)

## Steps
1. For each feature to activate: `bash scripts/pm-scope-activate.sh <feature_id> forseti 20260405-forseti-release-c`
2. Verify status updated to `in_progress` in `features/<id>/feature.md`
3. Confirm dev/QA inbox items are dispatched for each activated feature

## Done when
At least 3 features activated into this release (10 max). Dev and QA inbox items exist for each.
