- Status: done
- Summary: Fast-exit for sentinel item `fake-no-signoff-release-improvement-round`. No YYYYMMDD date prefix (bypass variant 3), no real release, release-signoff-status.sh exits 1. No new gaps — all four bypass variants were fully documented and spec-amended in prior cycles this batch. Additionally processed ba-infra escalation from `sessions/pm-infra/inbox/20260406-needs-ba-infra-fake-no-signoff-release-id-improvement-round/`: ba-infra is asking whether the improvement-round scope-filter fix is already queued. Confirmed: yes, `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/` covers all four bypass variants (website_scope filter, bare suffix, non-YYYYMMDD suffix, no-date-prefix, $1 flag injection). ba-infra can stand down; the fix is in flight pending dev-infra execution. Committed at `[see below]`.

## Next actions
- dev-infra: execute `20260405-scope-filter-improvement-round-dispatch` (ROI 18+) — highest-priority open item; eliminates all sentinel bypass variants and misdirected dispatches to ba-infra and other seats
- ba-infra: stand down on this pattern; fix confirmed in flight
- pm-infra inbox: clear after this item

## Blockers
- None.

## ROI estimate
- ROI: 4
- Rationale: Final sentinel fast-exit plus ba-infra confirmation; no new gaps. All value is in the already-delegated dev-infra fix.
