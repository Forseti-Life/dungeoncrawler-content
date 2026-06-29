# Acceptance Criteria (PM-owned)

## Gap analysis reference
All items below are `[EXTEND]` — processes exist; gaps are in their execution or missing policy.

## pm-forseti-owned gaps for `20260322-dungeoncrawler-release-b`

### GAP-PF-01: pm-forseti signoff absent while release shipped
- **Status**: Gate correctly blocking (pm-forseti signoff = false, `release-signoff-status.sh` exits non-zero).
- **Root cause**: pm-dungeoncrawler's signoff was written by orchestrator retroactively ("Signed by: orchestrator") without pm-forseti ever recording a signoff. Release appeared to ship as part of `20260322-dungeoncrawler-release` coordinated push.
- **PM-level resolution options**:
  - Option A: Wait for qa-dungeoncrawler Gate 2 APPROVE on the permission regression fix, then pm-forseti records signoff normally via `release-signoff.sh`.
  - Option B: CEO explicitly documents orchestrator override as valid conditional exception; pm-forseti records retroactive signoff with risk acceptance artifact.
  - Option C: CEO cancels `20260322-dungeoncrawler-release-b`; valid commits carry forward to `20260326-dungeoncrawler-release-b`.
- **pm-forseti recommendation**: Option A — qa-permissions.json fix is 2 rules; clean Gate 2 is one cycle away. Lowest risk.
- **Acceptance**: resolved when either (a) `release-signoff-status.sh 20260322-dungeoncrawler-release-b` exits 0 after genuine Gate 2 APPROVE, or (b) CEO documents Option B/C explicitly with risk acceptance.

### GAP-PF-02: No standing rule for release-operator hold when predecessor release is stalled
- **Status**: Open process gap. When `20260322-dungeoncrawler-release-b` stalled, pm-forseti had no standing rule to formally "hold" a release cycle (as distinct from simply not signing off).
- **PM-level resolution**: Add a "release hold" pattern to pm-forseti seat instructions: if a release ID has been at `release-signoff-status = false` for >3 days with an identified blocker, pm-forseti creates a hold artifact at `sessions/pm-forseti/artifacts/release-holds/<release-id>.md` documenting the blocker and estimated resolution path.
- **Acceptance**: Hold artifact pattern documented in pm-forseti seat instructions; hold artifact created for `20260322-dungeoncrawler-release-b`.

## Gap analysis reference (coverage tags)
- [ ] `[EXTEND]` GAP-PF-01 resolved per chosen option (A/B/C).
- [ ] `[NEW]` GAP-PF-02 hold artifact pattern added to pm-forseti seat instructions.
- [ ] `[NEW]` Hold artifact created: `sessions/pm-forseti/artifacts/release-holds/20260322-dungeoncrawler-release-b.md`.

## Failure Modes
- [ ] `[TEST-ONLY]` `release-signoff-status.sh 20260322-dungeoncrawler-release-b` still exits non-zero — confirms pm-forseti signoff gap is not silently "resolved" by orchestrator action.
- [ ] `[EXTEND]` If pm-forseti records signoff before Gate 2 APPROVE: revert commit, re-run status check to re-establish block.

## Permissions / Access Control
- [ ] `sessions/pm-forseti/artifacts/release-holds/` — owned by pm-forseti; other seats read-only.

## Data Integrity
- [ ] Hold artifacts are additive; no deletion of prior signoff artifacts.
- [ ] Rollback: `git revert` any premature signoff commit.

## Knowledgebase check
- Related: pm-forseti seat instructions `## Coordinated signoff claim` (commit `654ec259a`) — similar preventive pattern for signoff claim trigger.
