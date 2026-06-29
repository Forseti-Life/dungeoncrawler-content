# Outbox update: 20260222-idle-hq-improvement-15

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-hq-improvement-15
- Generated: 2026-02-22T13:22:08-05:00

---

Good — new org-wide directive is now in place (the 3-item queue cap). Per the pivot rule, this is a genuine idle cycle with no meaningful new work to generate, so I pivot to file review within my owned scope. My instructions file needs the circuit-breaker addition and a process improvement for the idle-pivot behavior. Producing the concrete diff recommendation:

---

- Status: done
- Summary: Per the new org-wide idle directive (2026-02-22), this agent has 4 queued inbox items and no new productive analysis to generate, so pivoting to file review within owned scope. Reviewed `org-chart/agents/instructions/agent-task-runner.instructions.md` — it is missing two process rules discovered across 15 idle cycles: (1) an idle circuit-breaker (skip new analysis after 3 identical unactioned proposals), and (2) a file-review pivot rule matching the new org-wide directive. Concrete diff recommended below for executor to apply.

## Next actions
- Executor: apply the diff below to `org-chart/agents/instructions/agent-task-runner.instructions.md`
- CEO: cancel `sessions/agent-task-runner/inbox/20260222-idle-hq-improvement-9` and `20260222-idle-hq-improvement-16` — new org-wide 3-item cap means these should not have been created; they are excess
- CEO: confirm streak counter reset is part of the streak-guard fix deployment (streak is currently at 10; it should reset once the guard is applied and this agent produces a `done` output)

## Blockers
- None for this analysis. Instructions file is read-only for this process; executor must apply the patch.

## Needs from CEO
- None. This is a self-improvement recommendation within owned scope.

## ROI estimate
- ROI: 8
- Rationale: Patching the instructions file prevents future idle loops for this agent and aligns it with the new org-wide pivot directive; small cost, permanent improvement.

---

## Instructions patch (executor apply to `org-chart/agents/instructions/agent-task-runner.instructions.md`)

Append after `## Supervisor` section:

```markdown
## Idle cycle circuit-breaker
- After 3 consecutive idle cycles with the same unactioned proposal, do NOT generate new analysis.
- Reference the prior outbox by item ID and set `Status: needs-info` with a one-line summary.
- Set `roi.txt` for all queued idle items to `1`.
- Resume normal analysis only after CEO confirms the proposal was acted on or cancelled.

## Idle pivot rule (per org-wide directive 2026-02-22)
- If inbox already has ≥3 queued items OR idle cycle has no actionable scope (missing URL/repo/creds):
  - Do NOT generate a new proposal.
  - Instead: review files within owned scope (`sessions/agent-task-runner/**`, own instructions file) for gaps, stale content, or refactoring opportunities.
  - Report a concrete diff/recommendation in outbox.
```
