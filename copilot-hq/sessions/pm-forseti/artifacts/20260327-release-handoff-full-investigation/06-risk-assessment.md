# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| `20260322-dungeoncrawler-release-b` treated as shipped when it is not (orchestrator signoff creates ambiguity) | Medium | High — features with unverified permission state could reach production | pm-forseti signoff = false; `release-signoff-status.sh` exits non-zero and blocks push | pm-forseti |
| pm-forseti signoff gap recurs in `20260326-dungeoncrawler-release-b` | Low (signoff-claim rule in seat instructions) | High — push happens without release operator signoff | Seat instructions updated (`654ec259a`): coordinated signoff claim fires on any Gate 2 report inbox item | pm-forseti |
| `20260322-dungeoncrawler-release-b` abandoned implicitly (no cancel artifact, commits lost) | Medium | Medium — audit trail gap; commits re-queued with no provenance | If CEO cancels: pm-forseti creates explicit cancel artifact; pm-dungeoncrawler carries valid commits to `20260326-dungeoncrawler-release-b` | pm-forseti + pm-dungeoncrawler |
| GAP-DC-01 (testgen throughput) unresolved when `20260326-dungeoncrawler-release-b` Stage 1 starts | High | High — same 4+ features will block Gate 2 again | CEO decision required: authorize PM manual fallback or drain queue before Stage 1 | CEO |

## Outstanding CEO Decisions (consolidated)

1. **Testgen path (GAP-DC-01, day 7+)**: drain / batch / authorize PM manual fallback — Recommendation: PM manual fallback; ROI = 15
2. **Gate 2 waiver policy (GAP-DC-B-01)**: approve pm-dungeoncrawler draft (`d42c5695e`) with "max 3 features per cycle" guard
3. **pm-forseti signoff gap on `20260322-dungeoncrawler-release-b`**: Option A (wait for clean Gate 2, only 2 rule changes away) recommended

## Rollback Trigger
- Premature signoff: `git revert <sha>` of signoff commit; re-run `release-signoff-status.sh 20260322-dungeoncrawler-release-b` to confirm block re-established.

## Monitoring
- What to watch: `release-signoff-status.sh 20260322-dungeoncrawler-release-b` — must exit non-zero until qa-dungeoncrawler Gate 2 APPROVE is issued.
- Where: `sessions/pm-forseti/artifacts/release-signoffs/` and `sessions/pm-dungeoncrawler/artifacts/release-signoffs/`.
