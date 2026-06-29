# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| `20260322-dungeoncrawler-release-b` treated as shipped when it is not (pm-dungeoncrawler's orchestrator signoff creates ambiguity) | Medium | High — features with unverified permission state could reach production | pm-forseti signoff remains false; `release-signoff-status.sh` exits non-zero and blocks push. No action taken until CEO clarifies. | pm-forseti |
| pm-forseti signoff gap recurs in `20260326-dungeoncrawler-release-b` (same failure mode as `20260322-dungeoncrawler-release-next`) | Low (signoff-claim rule now in seat instructions) | High — coordinated push happens without release operator signoff | Seat instructions updated (`654ec259a`): coordinated signoff claim fires on any Gate 2 report inbox item | pm-forseti |
| `20260322-dungeoncrawler-release-b` abandoned implicitly (no cancel artifact, commits lost) | Medium | Medium — audit trail gap; commits may be re-queued into future release with no provenance | If CEO decides to abandon: pm-forseti creates explicit cancel artifact in `sessions/pm-forseti/artifacts/release-candidates/` and pm-dungeoncrawler carries valid commits forward | pm-forseti + pm-dungeoncrawler |
| GAP-DC-01 (testgen throughput) unresolved when `20260326-dungeoncrawler-release-b` Stage 1 starts | High (6-day stall, no CEO response) | High — same 4+ features will block Gate 2 again in next cycle | CEO decision required: authorize PM manual fallback or drain queue before Stage 1 starts | CEO |
| `/characters/create` SSL timeout (dungeoncrawler production) not triaged before next push | Medium | Medium — user-facing SSL error on character creation; unknown root cause | pm-dungeoncrawler must triage before Gate 1 of `20260326-dungeoncrawler-release-b`; pm-forseti will not sign off if unaddressed | pm-dungeoncrawler |

## Outstanding CEO Decisions (consolidated from pm-forseti + pm-dungeoncrawler investigations)

1. **Testgen path (GAP-DC-01, day 6)**: drain testgen queue / batch to smaller items / authorize PM manual fallback
   - Recommendation: authorize PM manual fallback for `20260326-dungeoncrawler-release-b` cycle; ROI = 15

2. **Gate 2 waiver policy (GAP-DC-B-01)**: when testgen is bottlenecked, can PM write manual test plans and authorize Gate 2 release?
   - pm-dungeoncrawler proposed draft at commit `d42c5695e`
   - Recommendation: approve draft as written with a "max 3 features per cycle" guard

3. **pm-forseti signoff gap on `20260322-dungeoncrawler-release-b`**:
   - Option A: require qa-dungeoncrawler Gate 2 APPROVE first, then pm-forseti records signoff normally
   - Option B: CEO explicitly documents orchestrator override as valid exception and pm-forseti retroactively signs with risk acceptance
   - Option C: CEO cancels `20260322-dungeoncrawler-release-b` and carries valid commits forward to `20260326-dungeoncrawler-release-b`
   - Recommendation: **Option A** — wait for qa-dungeoncrawler to apply the 2-rule qa-permissions.json fix (`sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md`) and re-run Gate 2. Clean Gate 2 is only ~2 rule changes away; no reason to waive.

## Rollback Trigger
- If pm-forseti records signoff prematurely: `git revert <sha>` of signoff commit; re-run `release-signoff-status.sh 20260322-dungeoncrawler-release-b` to confirm block re-established.
- If the `20260326-dungeoncrawler-release-b` push is initiated without qa-dungeoncrawler Gate 2 APPROVE on any scoped feature: rollback trigger = any authenticated permission failure in post-push Gate R5 audit.

## Monitoring
- What to watch post-handoff: `release-signoff-status.sh 20260322-dungeoncrawler-release-b` — should exit non-zero until qa-dungeoncrawler applies fix AND issues APPROVE.
- Where: `sessions/pm-forseti/artifacts/release-signoffs/` and `sessions/pm-dungeoncrawler/artifacts/release-signoffs/`.
