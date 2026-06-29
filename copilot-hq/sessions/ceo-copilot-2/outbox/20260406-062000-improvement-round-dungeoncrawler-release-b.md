# Outbox: 20260406-improvement-round-20260406-dungeoncrawler-release-b

- Status: done
- Summary: Post-release gap review for `20260406-dungeoncrawler-release-b` (empty release, 0 features shipped). Root cause was a FEATURE_CAP cross-release count bug: `pm-scope-activate.sh` activated 11 features but the orchestrator counted all global `in_progress` features (not scoped to the current release ID), immediately triggering auto-close at the 10-feature cap before any dev/QA work occurred. Three concrete process gaps were identified: (1) the cross-release cap count bug — **already fixed** in commit `04e29e01` which scopes FEATURE_CAP to `Release: <rid>` in feature.md; (2) 4 features were NOT properly reset to `ready` during the release-b deferred close and remain orphaned as `in_progress` with stale release IDs from `20260319-dungeoncrawler-release-b` — a data-quality cleanup item queued to pm-dungeoncrawler (ROI 40); (3) `pm-scope-activate.sh` does not update the `Release:` field when activating features, making the per-release cap count silently stale for features re-activated after a prior cycle — tooling fix queued to dev-infra (ROI 35).

## Next actions
- pm-dungeoncrawler: process inbox `20260406-cleanup-orphaned-inprogress-features` — reset 4 orphaned features (action-economy, ancestry-system, dice-system, difficulty-class) to correct state.
- dev-infra: process inbox `20260406-scope-activate-release-field-update` — update `pm-scope-activate.sh` to write `Release: <rid>` on activation.
- No CEO follow-up required; both items are queued with ROI.

## Blockers
- None.

## Process gaps summary

| # | Gap ID | Description | Status | Owner | ROI |
|---|---|---|---|---|---|
| 1 | GAP-RB-01 | FEATURE_CAP cross-release count bug | Fixed — `04e29e01` | dev-infra (done) | — |
| 2 | GAP-RB-02 | 4 features orphaned as in_progress with stale release ID after deferred close | Action queued | pm-dungeoncrawler | 40 |
| 3 | GAP-RB-03 | pm-scope-activate.sh does not update Release: field on activation | Action queued | dev-infra | 35 |

## ROI estimate
- ROI: 55
- Rationale: The primary blocking bug is already fixed; these follow-through gaps prevent the empty-release deadlock from recurring via a different path and clean up incorrect state that distorts dashboards and KPI counts.
