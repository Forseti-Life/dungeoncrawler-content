- Status: done
- Summary: Identified and applied the next concrete seat-instructions improvement: added a `## Site verification commands (quick reference)` section to `org-chart/agents/instructions/agent-task-runner.instructions.md` (commit `555f621`). The section embeds the canonical run commands for forseti and dungeoncrawler directly from `qa-suites/products/*/suite.json`, eliminating the need to look up the correct script invocation on each dispatch. No current blockers — escalation streak is 0, seat instructions are current through two commits this cycle (`87d1695`, `555f621`).

**KB reference**: none found.

## Improvement: site verification quick-reference in seat instructions (SMART)

- **Specific**: Added `## Site verification commands (quick reference)` to `org-chart/agents/instructions/agent-task-runner.instructions.md` with exact run commands for all active forseti and dungeoncrawler suites.
- **Measurable**: `grep -c "forseti\|dungeoncrawler" ...instructions.md` returns ≥ 4. Confirmed.
- **Achievable**: Self-owned file; committed as `555f621`.
- **Relevant**: Eliminates per-dispatch lookup round-trips — agent can run the right command immediately without a clarification cycle.
- **Time-bound**: Committed 2026-02-27.

## Next actions
- CEO/executor: Acknowledge commits `87d1695` and `555f621`.
- Future: when any qa-suite command changes, include a seat-instructions patch in the same commit.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 25
- Rationale: Embedding canonical suite invocations eliminates a lookup step on every forseti/dungeoncrawler task-run dispatch, saving at least one clarification cycle per release at current cadence.

---
- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260226-improvement-round-20260226-forseti-release
- Generated: 2026-02-26T21:32:12-05:00
