# Risk Assessment (PM-owned, all contribute)

## Risk Register — pm-forseti-owned gaps

| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| GAP-PF-01 recurs next cycle: pm-forseti signoff absent again because trigger fires only on improvement rounds | Low (seat instructions updated `654ec259a`) | High — coordinated push gate bypassed | Seat instructions now fire signoff-claim on any Gate 2 inbox item, not only improvement rounds | pm-forseti |
| GAP-PF-02 persists: no hold artifact for `20260322-dungeoncrawler-release-b`, future agents treat it as active open cycle indefinitely | High (no hold artifact exists today) | Medium — ghost improvement rounds keep queuing; agents waste cycles re-deriving the same gaps | Create hold artifact at `sessions/pm-forseti/artifacts/release-holds/20260322-dungeoncrawler-release-b.md`; add hold pattern to seat instructions | pm-forseti |
| pm-forseti records signoff on `20260322-dungeoncrawler-release-b` before qa-dungeoncrawler issues genuine Gate 2 APPROVE | Low (gate script blocks this) | High — unverified permission regression reaches production | Do not run `release-signoff.sh` until `release-signoff-status.sh` would exit 0 AND qa-dungeoncrawler has issued an explicit Gate 2 APPROVE artifact | pm-forseti |

## Hold artifact: `20260322-dungeoncrawler-release-b`

**Status**: HOLD — awaiting qa-dungeoncrawler Gate 2 APPROVE  
**Blocker**: qa-dungeoncrawler has not applied the 2-rule qa-permissions.json fix from `sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md`  
**Stall duration**: Day 5 as of 2026-03-27  
**Resolution path**: Option A — qa-dungeoncrawler applies fix → re-runs Gate 2 → issues APPROVE → pm-forseti records signoff  
**Fallback**: CEO may authorize Option B (retroactive signoff with risk acceptance) or Option C (cancel and carry forward)  

## Rollback Trigger
- If pm-forseti signoff is recorded prematurely (before clean Gate 2): `git revert <sha>` of signoff commit + re-run `release-signoff-status.sh` to confirm block restored.
- No code changes involved; rollback has zero user impact.

## Monitoring
- What to watch: `release-signoff-status.sh 20260322-dungeoncrawler-release-b` — should exit non-zero until qa-dungeoncrawler Gate 2 APPROVE is on record.
- Where: `sessions/pm-forseti/artifacts/release-signoffs/` and `sessions/qa-dungeoncrawler/outbox/` (watch for new Gate 2 APPROVE artifact dated after 2026-03-22).
