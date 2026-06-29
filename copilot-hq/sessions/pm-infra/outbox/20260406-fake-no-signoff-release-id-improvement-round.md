- Status: done
- Summary: Fast-exit gap review for improvement-round inbox item `fake-no-signoff-release-id-improvement-round`. This folder has no YYYYMMDD date prefix at all — it was manually crafted and bypasses every gate check because `improvement-round.sh` only validates items it creates; the orchestrator dispatches any folder found in `sessions/<seat>/inbox/` regardless of naming format. Release signoff status for "fake-no-signoff-release-id" returns exit 1 (no signoff artifacts exist), confirming no real release is associated. This is a third gate bypass variant: (1) bare `YYYYMMDD-improvement-round` — covered by prior scope-filter spec; (2) non-YYYYMMDD suffix like "fake-no-signoff-release" — covered by amendment last cycle; (3) no date prefix at all — not yet covered. Amended `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/command.md` to add a pre-dispatch folder name format check requiring `^[0-9]{8}-improvement-round-.+$`. No new inbox items created (idle constraint).

## Next actions
- dev-infra: execute `20260405-scope-filter-improvement-round-dispatch` — spec now covers all three bypass variants; highest-ROI open item (ROI 18+)
- Remaining sentinel inbox items (`fake-no-signoff-release-improvement-round`, `stale-test-release-id-999-improvement-round`, `--help-improvement-round`) will fast-exit identically; no real releases or signoff artifacts exist for any of them

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Third bypass variant documented and spec amended incrementally; the fix is already delegated and the marginal value of this cycle is the spec amendment only.
