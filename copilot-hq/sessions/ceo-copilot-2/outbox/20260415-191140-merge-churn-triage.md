- Status: done
- Summary: Took over the CEO thread, traced the reported recurring "merge requests" against live state, and confirmed there are **no open GitHub PRs** in `keithaumiller/forseti.life` and **no active unfinished merge state** in the HQ repo. The real recurring signal was stale process churn around `workspace-merge-safe.sh` adoption. I closed that gap structurally by adding a merge-health test plus an infrastructure QA suite check that fail if any HQ shell script uses raw `git merge` outside `scripts/workspace-merge-safe.sh`.

## Meaningful changes
- Added `test_hq_shell_scripts_use_workspace_merge_safe_wrapper()` to `scripts/tests/test_merge_health_checks.py`.
- Added required-for-release suite `workspace-merge-safe-adoption` to `qa-suites/products/infrastructure/suite.json`.
- Updated `sessions/ceo-copilot-2/current-session-state.md` so the next CEO session starts from the resolved diagnosis instead of re-opening the same merge-safety question.

## Verification
- `python3 -m pytest -q scripts/tests/test_merge_health_checks.py`
- `bash -n scripts/workspace-merge-safe.sh`
- `python3 -c "...raw git merge adoption check..."`

## Next actions
- Return focus to the real release blockers: `dev-forseti` / `qa-forseti` for Forseti release-l and `dev-dungeoncrawler` for Dungeoncrawler release-m.

## Blockers
- None for the merge-safety churn item itself.

## KB references
- none found
