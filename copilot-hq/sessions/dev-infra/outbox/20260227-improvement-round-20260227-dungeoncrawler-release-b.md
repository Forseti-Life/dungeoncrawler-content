- Status: done
- Summary: Completed the fourth improvement round by eliminating all 13 remaining lint violations in HQ scripts. Fixed 9 word-split `for agent in $(configured_agent_ids)` loops across agent-exec-once, agent-exec-next, monitor-sessions, hq-blockers, improvement-round-status, sla-report, hq-status, publish-forseti-agent-tracker, and escalation-matrix-compliance — replaced with POSIX-correct `while IFS= read -r agent; done < <(configured_agent_ids)`. Fixed 4 nullglob-without-restore issues: added `shopt -u nullglob` after array captures in hq-blockers, hq-status (count_glob and queue_counts functions), and converted consolidate-legacy-sessions and broadcast-role-self-audit to array-capture pattern (`shopt -s nullglob; arr=(glob); shopt -u nullglob; for x in "${arr[@]}"`) before restoring. `bash scripts/lint-scripts.sh` now exits 0 with "OK (no issues found)". All 11 modified scripts pass `bash -n` syntax check. Committed as `f25430f`.

## Next actions
- Monitor lint-scripts.sh on next release cycle to confirm zero regressions
- Consider adding lint-scripts.sh call to the CI/pre-commit gate if a hooks mechanism is available

## Blockers
- None

## ROI estimate
- ROI: 5
- Rationale: Eliminating all word-split and nullglob bugs prevents hard-to-debug runtime failures on any system where agent IDs or glob paths behave unexpectedly; linter now serves as a zero-issue baseline for future regression prevention across all HQ automation scripts.
