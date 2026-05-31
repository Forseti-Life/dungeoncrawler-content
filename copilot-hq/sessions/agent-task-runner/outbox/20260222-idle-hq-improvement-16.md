# Outbox update: 20260222-idle-hq-improvement-16

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-hq-improvement-16
- Generated: 2026-02-22T13:24:28-05:00

---

Per the new org-wide directive (3-item queue cap), this inbox item should not have been created — there are already 3 queued items (-9, -16, -17). Pivoting to file review as directed. Instructions patch from cycle -15 was not applied. Repeating it as the concrete deliverable.

---

- Status: done
- Summary: Idle pivot cycle per org-wide directive — inbox already has 3 items queued, so no new proposal generated. Reviewed `org-chart/agents/instructions/agent-task-runner.instructions.md` (owned scope). The instructions patch proposed in cycle -15 was not applied; repeating it here as the concrete deliverable. The patch adds two rules: idle circuit-breaker (stops repeated identical proposals) and idle pivot rule (aligns with new org-wide directive). No other gaps found in owned scope this cycle.

## Next actions
- Executor: apply the patch below to `org-chart/agents/instructions/agent-task-runner.instructions.md`
- CEO: cancel `20260222-idle-hq-improvement-9` and `20260222-idle-hq-improvement-17` — both exceed the new 3-item queue cap and will produce this same pivot output

## Blockers
- None. Instructions file is read-only for this process; executor applies the patch.

## ROI estimate
- ROI: 6
- Rationale: Instructions patch permanently prevents future idle loops and aligns this seat with the new org-wide directive; executor cost is a single file edit.

---

## Instructions patch (executor apply to `org-chart/agents/instructions/agent-task-runner.instructions.md`)

Append after `## Supervisor` section:

```markdown
## Idle cycle circuit-breaker
- After 3 consecutive idle cycles with the same unactioned proposal, do NOT generate new analysis.
- Reference the prior outbox by item ID. Set `Status: needs-info` with a one-line summary.
- Set `roi.txt` for all queued idle items to `1`.
- Resume normal analysis only after CEO confirms the proposal was acted on or explicitly cancelled.

## Idle pivot rule (per org-wide directive 2026-02-22)
- If inbox already has ≥3 queued items OR idle cycle has no actionable scope (missing URL/repo/creds):
  - Do NOT generate a new proposal.
  - Instead: review files within owned scope for gaps, stale content, or refactoring opportunities.
  - Report a concrete diff/recommendation in outbox and set Status: done.
```
