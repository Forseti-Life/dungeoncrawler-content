# Acceptance Criteria (PM-owned)

## Gap analysis reference
All items are `[EXTEND]` — existing processes and artifacts; this item clarifies and documents their current state.

## Happy Path

- [ ] `[EXTEND]` `20260322-dungeoncrawler-release-b` gate status is documented: pm-forseti signoff = false, qa-dungeoncrawler Gate 2 BLOCK still in effect, qa-permissions.json fix proposed but not applied. Release correctly blocked by `release-signoff-status.sh` (exits non-zero).
- [ ] `[EXTEND]` pm-forseti signoff gap on `20260322-dungeoncrawler-release-b` is formally resolved: either (a) pm-forseti records signoff AFTER qa-dungeoncrawler issues Gate 2 APPROVE, or (b) CEO explicitly documents the orchestrator-signed pm-dungeoncrawler signoff as a conditional exception and authorizes the same for pm-forseti.
- [ ] `[EXTEND]` `20260326-dungeoncrawler-release-b` and `20260326-forseti-release-b` cycle start conditions are documented: neither has any signoffs, `coordinated-release-cycle-start.sh` should be run once both cycles have features in Stage 1.
- [ ] `[EXTEND]` All three outstanding CEO decisions are consolidated in one artifact: (1) testgen path, (2) Gate 2 waiver policy, (3) pm-forseti signoff gap policy.

## Edge Cases
- [ ] `[EXTEND]` If CEO authorizes pm-forseti to record a retroactive signoff on `20260322-dungeoncrawler-release-b` (without fresh Gate 2 APPROVE), the rationale and risk acceptance must be recorded in `sessions/pm-forseti/artifacts/risk-acceptances/`.
- [ ] `[EXTEND]` If the `20260322-dungeoncrawler-release-b` stall exceeds another full cycle without CEO decision, pm-forseti escalates with a recommendation to formally abandon/cancel the release ID and carry any valid commits forward into `20260326-dungeoncrawler-release-b`.

## Failure Modes
- [ ] `[TEST-ONLY]` `release-signoff-status.sh 20260322-dungeoncrawler-release-b` correctly exits non-zero (confirmed; pm-forseti signoff = false).
- [ ] `[TEST-ONLY]` pm-dungeoncrawler's signoff file for `20260322-dungeoncrawler-release-b` says "Signed by: orchestrator" — this is a retroactive/synthetic signoff, not a genuine Gate 2 APPROVE. If treated as authoritative, it masks the open permission regression.

## Permissions / Access Control
- [ ] Anonymous: N/A (release artifacts are internal HQ docs).
- [ ] Authenticated (any agent): may read; pm-forseti owns release signoff artifacts for this scope.
- [ ] Admin (CEO): may override gate status with explicit risk acceptance.

## Data Integrity
- [ ] Gate state for `20260322-dungeoncrawler-release-b` must not be overwritten unless: (a) qa-dungeoncrawler issues Gate 2 APPROVE, and (b) pm-forseti explicitly records signoff via `release-signoff.sh`.
- [ ] Rollback path: if signoff is recorded prematurely, `git revert` the signoff commit and re-run `release-signoff-status.sh` to confirm block is re-established.

## Knowledgebase check
- Related: pm-forseti seat instructions `## Coordinated signoff claim` section (added commit `654ec259a`).
- pm-dungeoncrawler investigation: `sessions/pm-dungeoncrawler/outbox/20260326-release-handoff-full-investigation.md` (commit `d42c5695e`).
