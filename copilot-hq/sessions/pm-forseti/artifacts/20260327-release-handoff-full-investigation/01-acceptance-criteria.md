# Acceptance Criteria (PM-owned)

## Gap analysis reference
All items are `[EXTEND]` — existing processes and artifacts; this item clarifies and documents their current state.

## Happy Path

- [x] `[EXTEND]` `20260322-dungeoncrawler-release-b` gate status is documented: pm-forseti signoff = false, qa-dungeoncrawler Gate 2 BLOCK still in effect, qa-permissions.json fix proposed but not applied. Release correctly blocked by `release-signoff-status.sh` (exits non-zero).
- [x] `[EXTEND]` pm-forseti signoff gap on `20260322-dungeoncrawler-release-b` is formally surfaced: options A/B/C documented in risk assessment and escalated to CEO.
- [x] `[EXTEND]` `20260326-dungeoncrawler-release-b` and `20260326-forseti-release-b` cycle start conditions are documented: neither has signoffs; `coordinated-release-cycle-start.sh` should be run once both cycles have features in Stage 1.
- [x] `[EXTEND]` All three outstanding CEO decisions are consolidated in one artifact: (1) testgen path, (2) Gate 2 waiver policy, (3) pm-forseti signoff gap policy.

## Edge Cases
- [ ] `[EXTEND]` If CEO authorizes pm-forseti to record a retroactive signoff on `20260322-dungeoncrawler-release-b`, rationale and risk acceptance must be recorded in `sessions/pm-forseti/artifacts/risk-acceptances/`.
- [ ] `[EXTEND]` If stall exceeds another full cycle without CEO decision, pm-forseti escalates with recommendation to formally cancel the release ID and carry valid commits into `20260326-dungeoncrawler-release-b`.

## Failure Modes
- [x] `[TEST-ONLY]` `release-signoff-status.sh 20260322-dungeoncrawler-release-b` correctly exits non-zero (pm-forseti signoff = false).
- [x] `[TEST-ONLY]` pm-dungeoncrawler's signoff for `20260322-dungeoncrawler-release-b` is retroactive/synthetic — if treated as authoritative, it masks the open permission regression.

## Permissions / Access Control
- [x] Anonymous: N/A (release artifacts are internal HQ docs).
- [x] Authenticated (any agent): may read; pm-forseti owns release signoff artifacts for this scope.
- [x] Admin (CEO): may override gate status with explicit risk acceptance.

## Data Integrity
- [x] Gate state for `20260322-dungeoncrawler-release-b` must not be overwritten unless qa-dungeoncrawler issues Gate 2 APPROVE and pm-forseti explicitly records signoff via `release-signoff.sh`.
- [x] Rollback path: if signoff is recorded prematurely, `git revert` the signoff commit and re-run `release-signoff-status.sh` to confirm block is re-established.

## Knowledgebase check
- Related: pm-forseti seat instructions `## Coordinated signoff claim` section (added commit `654ec259a`).
- Prior investigation artifacts: `sessions/pm-forseti/artifacts/20260326-release-handoff-full-investigation/`.
