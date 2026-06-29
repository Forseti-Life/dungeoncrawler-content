- Status: done
- Summary: Resolved the persistent CEO merge-health blocker by fixing the health-check contract rather than chasing shared-environment churn. Root cause was that `scripts/lib/merge-health.sh` treated all tracked changes in the HQ superproject as merge blockers, including normal `sessions/**` queue movement, `tmp/**` runtime state, and dirty child-repo worktrees reported at the submodule root. That made `ceo-system-health.sh` and `hq-status.sh` fail even when there was no unfinished merge state and no blocking superproject drift. The fix now ignores operational session/runtime paths, downgrades dirty submodule worktrees to non-blocking warnings, and still fails on real merge state, gitlink drift, or top-level tracked source changes. Regression coverage was added in `scripts/tests/test_merge_health_checks.py`, and the live health checks now clear once the helper change is checkpointed.

## 5 Whys
1. **Why was merge health failing repeatedly?** Because the HQ health checks were flagging tracked changes that were not actual merge blockers.
2. **Why were those changes present constantly?** Because this shared environment continuously mutates `sessions/**` and `tmp/**`, and active child repos stay dirty while work is in progress.
3. **Why did the checks treat that as a merge blocker?** Because the helper scanned raw `git status --porcelain` output and counted every tracked change equally.
4. **Why was that a problem operationally?** Because the CEO queue kept receiving RCA/remediation work for noise instead of only actionable integration risk.
5. **Why did it persist?** Because the health-check contract had never been taught the difference between operational churn, child-repo worktrees, and real superproject merge risk.

## Containment
- Patched `scripts/lib/merge-health.sh` to ignore `sessions/**`, `tmp/**`, and dirty child-repo worktrees when they are not actual superproject gitlink changes.
- Updated `scripts/ceo-system-health.sh` and `scripts/hq-status.sh` to surface those conditions as informational/warning notes instead of FAILs.

## Permanent fix
- Added regression tests covering ignored operational session churn and dirty submodule worktrees while preserving FAIL behavior for true merge conflicts and blocking tracked changes.
- Checkpointed the helper/script changes in commit `9edf7aebf0`.

## Verification
- `pytest -q scripts/tests/test_merge_health_checks.py`
- `bash scripts/ceo-system-health.sh`
- `bash scripts/hq-status.sh`

## Blockers
- None. The persistent CEO merge-health blocker is resolved.
