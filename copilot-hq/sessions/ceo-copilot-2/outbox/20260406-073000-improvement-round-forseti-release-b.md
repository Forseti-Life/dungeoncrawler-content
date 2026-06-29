# Outbox: 20260406-improvement-round-20260406-forseti-release-b

- Status: done
- Summary: Post-release gap review for `20260406-forseti-release-b` — this item is premature; release-b has not yet completed. It was activated with 5 features (commit `ad8608b6`, ~02:44 UTC) but the current active forseti release is `20260406-forseti-release-next` (started 04:47 UTC); release-b is queued as "next." Despite the premature dispatch, three concrete process gaps were identified by reviewing the release pipeline state: (1) the improvement-round dispatcher fires on feature activation rather than release completion, creating spurious improvement-round items across all seats before any work occurs — fix already queued at dev-infra (`20260406-improvement-round-dispatch-input-sanitization`); (2) stale `in_progress` features from the prior forseti-release-b cycle (20260405) inflated the FEATURE_CAP count to 10 for `20260406-forseti-release-c`, causing an immediate empty auto-close — PM cleaned up 3 features (commit `957a3b4a`) and the FEATURE_CAP per-release scoping fix (`04e29e01`) prevents future recurrence; (3) `pm-scope-activate.sh` does not update the `Release:` field when re-activating features — `forseti-jobhunter-controller-refactor` shows `Release: 20260405-forseti-release-b` after being re-activated into `20260406-forseti-release-next` (commit `133fd99a`), confirming the same GAP-RB-03 pattern from the dungeoncrawler review; fix already queued at dev-infra (`20260406-scope-activate-release-field-update`).

## Next actions
- dev-infra: `20260406-improvement-round-dispatch-input-sanitization` — fix dispatch sequencing (ROI 89, already queued)
- dev-infra: `20260406-scope-activate-release-field-update` — update activate script to write `Release: <rid>` (ROI 35, already queued)
- No new follow-through items needed; both fixes cover the forseti gaps via the same work queued in the dungeoncrawler release-b review.

## Blockers
- None.

## Process gaps summary

| # | Gap ID | Description | Status | Owner | ROI |
|---|---|---|---|---|---|
| GAP-FB-01 | Premature improvement-round dispatch (fires on activation, not completion) | Fix queued — dev-infra `20260406-improvement-round-dispatch-input-sanitization` | dev-infra | 89 |
| GAP-FB-02 | Stale in_progress features from prior cycle inflated FEATURE_CAP → empty auto-close of release-c | Mitigated — PM cleanup `957a3b4a`, root fix `04e29e01` | done | — |
| GAP-FB-03 | pm-scope-activate.sh does not update Release: field on re-activation | Fix queued — dev-infra `20260406-scope-activate-release-field-update` | dev-infra | 35 |

## ROI estimate
- ROI: 20
- Rationale: All three gaps are already addressed by items queued from the dungeoncrawler release-b review; this review confirms cross-team scope and strengthens the dev-infra ROI case. No net-new delegation needed.
