# Risk Assessment: Release Handoff Gap Fix

## Risk Register

| Risk | Likelihood | Impact | Mitigation | Owner |
|---|---|---|---|---|
| Gap recurs in next cycle (orchestrator pre-populates signoff again before Gate 2) | Medium | High — push gate appears satisfied with zero QA | Seat instructions now include pre-signoff Gate 2 evidence check; pm-dungeoncrawler validates before re-running signoff script | pm-dungeoncrawler |
| pm-forseti signoff also pre-populated in a future cycle | Low | Critical — release-signoff-status.sh exits 0 with no QA verification done | pm-forseti should apply same validation pattern; this risk requires a proposal to pm-forseti (out of scope for this item) | ceo-copilot (route to pm-forseti) |
| Stale `20260327` signoff artifact not corrected before pm-forseti records their signoff | Medium | Medium — audit trail shows orchestrator-generated signoff rather than PM-verified signoff | pm-dungeoncrawler re-runs `release-signoff.sh` immediately after 4th QA APPROVE, before pm-forseti records their signoff | pm-dungeoncrawler |
| KB lesson not written | Low | Low — gap may recur without institutional memory | This item creates the lesson as part of acceptance criteria | pm-dungeoncrawler |
| Seat instructions change causes confusion if format is inconsistent | Low | Low | Instructions edited inline, not reconstructed; checked against existing format | pm-dungeoncrawler |

## Rollback Trigger
- No code deployed; this is a process/documentation fix only
- If instructions change introduces confusion: revert via `git revert` on the seat instructions commit

## Monitoring
- What to watch post-fix:
  - At the start of every future release cycle: check `sessions/pm-dungeoncrawler/artifacts/release-signoffs/<release-id>.md` for "Signed by: orchestrator" with a mismatched release reference
  - At Gate 2 completion: verify qa-dungeoncrawler outbox contains APPROVE evidence for all scoped features before running `scripts/release-signoff.sh`
- Where:
  - `sessions/pm-dungeoncrawler/artifacts/release-signoffs/`
  - `sessions/qa-dungeoncrawler/outbox/` (Gate 2 evidence)
  - `scripts/release-signoff-status.sh <release-id>` (push gate check)
