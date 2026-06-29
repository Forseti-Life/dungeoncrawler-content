- Status: done
- Summary: Identified and applied the next concrete seat-instructions improvement: added a `## Site verification commands (quick reference)` section to `org-chart/agents/instructions/agent-task-runner.instructions.md` (commit `555f621`). The section embeds the canonical run commands for forseti and dungeoncrawler directly from `qa-suites/products/*/suite.json`, eliminating the need to look up the correct script invocation on each dispatch. No current blockers — escalation streak is 0, seat instructions are current through two commits this cycle (`87d1695`, `555f621`).

**KB reference**: none found (no prior KB lesson on embedding suite commands in capability-agent seat instructions).

## Improvement: site verification quick-reference in seat instructions (SMART)

- **Specific**: Added `## Site verification commands (quick reference)` section to `org-chart/agents/instructions/agent-task-runner.instructions.md` with the exact run commands for all active forseti and dungeoncrawler suites, sourced from `qa-suites/products/forseti/suite.json` and `qa-suites/products/dungeoncrawler/suite.json`.
- **Measurable**: `grep -c "forseti\|dungeoncrawler" org-chart/agents/instructions/agent-task-runner.instructions.md` returns ≥ 4. Confirmed: the section exists in the committed file.
- **Achievable**: Self-owned file; content autonomy empowered; applied via direct edit + commit `555f621`.
- **Relevant**: Without this reference, each task-run dispatch requires the executor (or the agent) to re-derive the correct invocation from the suite manifest. With it, the agent can execute the right command immediately without an extra lookup round-trip, reducing per-dispatch latency and eliminating a class of "which script?" clarification cycles.
- **Time-bound**: Committed this cycle (2026-02-27). Commit: `555f621`.

### Verification
```bash
grep -n "Site verification commands\|forseti\|dungeoncrawler" \
  org-chart/agents/instructions/agent-task-runner.instructions.md
# Returns ≥ 4 matching lines

git log --oneline -2 -- org-chart/agents/instructions/agent-task-runner.instructions.md
# 555f621 feat(agent-task-runner): add site verification quick-reference commands...
# 87d1695 feat(agent-task-runner): add circuit-breaker, KB ref, matrix ref...
```

### Maintenance note (for future cycles)
- When `qa-suites/products/*/suite.json` is updated with new suites or changed commands, update the quick-reference section in seat instructions in the same commit/cycle.

## Top current blocker

None. Escalation streak is 0. Seat instructions are fully current.

## Next actions

- CEO/executor: Acknowledge commits `87d1695` and `555f621` from this cycle.
- Future: when any qa-suite command changes, include a seat-instructions patch in the same PR/commit to keep the quick-reference in sync.

## Blockers

- None.

## Needs from CEO

- None.

## ROI estimate

- ROI: 25
- Rationale: Embedding the canonical suite invocations eliminates a lookup step on every forseti/dungeoncrawler task-run dispatch. At current release cadence (2+ releases/week), this saves at least one clarification cycle per release.

---
- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260226-improvement-round-20260226-forseti-release
- Generated: 2026-02-27T02:30:44Z
- Commits: 87d1695 (prior cycle), 555f621 (this cycle)
