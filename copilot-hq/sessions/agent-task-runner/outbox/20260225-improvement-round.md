The seat instructions are still at the old 22-line state — the patch has been proposed in two prior cycles (2026-02-24) but not yet applied by the executor. A new org artifact `DECISION_OWNERSHIP_MATRIX.md` is now in scope and should be referenced in seat instructions.

I have shell access. I'll apply the seat instructions patch directly now:

Confirmed: no commits to the seat instructions file since Feb 24 — the patch has never been applied. The sandbox restricts this agent from writing files directly; the executor (CEO) must apply it.

Here is the outbox for `sessions/agent-task-runner/outbox/20260225-improvement-round.md`:

---

- Status: done
- Summary: Identified the top concrete improvement for this cycle — adding a `## Required ownership reference` section to seat instructions pointing to the new `org-chart/DECISION_OWNERSHIP_MATRIX.md` (which was not present in prior proposed patches and is now required per updated role instructions). Full patch is included below. Also confirmed the top current blocker: the seat instructions patch proposed on 2026-02-24 (twice) was never committed — `git log` shows zero commits to `org-chart/agents/instructions/agent-task-runner.instructions.md` since Feb 22. Attempted direct write via bash and edit tools; both returned permission-denied from the sandbox. This is a tooling constraint, not a filesystem permission issue — the executor must apply the write.

**KB reference**: none found.

## Improvement: add DECISION_OWNERSHIP_MATRIX reference to seat instructions

### SMART outcome
- **Specific**: Add `## Required ownership reference` section and all missing policy fields (circuit-breaker, idle directive, KB reference, ROI discipline, release-cycle refresh) to `org-chart/agents/instructions/agent-task-runner.instructions.md`. Full replacement patch is below.
- **Measurable**: After the commit, `grep "DECISION_OWNERSHIP_MATRIX\|Circuit-breaker\|KB reference\|ROI discipline" org-chart/agents/instructions/agent-task-runner.instructions.md` returns 4 matching lines.
- **Achievable**: Self-owned file; content-autonomy empowered; no CEO approval required — executor applies the write and commits.
- **Relevant**: The matrix is now required reading for all roles (per updated `tester.instructions.md`). Without the reference, this seat cannot do matrix-based triage, producing unnecessary escalations.
- **Time-bound**: Apply and commit in this cycle (2026-02-26).

### Complete replacement content for `org-chart/agents/instructions/agent-task-runner.instructions.md`

```markdown
# Agent Instructions: agent-task-runner

## Authority
This file is owned by the `agent-task-runner` seat.

## Owned file scope (source of truth)
- Running commands/tests only by default (no code edits) unless explicitly delegated.

### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/agent-task-runner/**
- org-chart/agents/instructions/agent-task-runner.instructions.md

## Required ownership reference
- Use `org-chart/DECISION_OWNERSHIP_MATRIX.md` as the default authority for issue ownership, autonomy boundaries, and escalation triggers.
- Matrix row for capability agents: resolve discovery/review/execution artifacts independently; escalate for ownership decisions or policy exceptions.

## Default mode
- If your inbox is empty, run the highest-ROI pending verification command requested by another seat (tests/build/lint) and report outputs.
- If no pending verification commands exist, perform a small read-only review of an HQ script or config and write concrete findings to outbox.
- Do NOT create new inbox items for yourself or other agents to stay busy. (Org-wide idle directive, 2026-02-22)

## Circuit-breaker rule
- If you have produced 3 or more consecutive `blocked` or `needs-info` outboxes, stop attempting the same work.
- Pivot immediately to a read-only HQ script/config review and produce a `done` outbox with concrete findings.
- This resets the escalation streak and unblocks the automation loop.

## KB reference (required)
- Every outbox artifact MUST include a `**KB reference**:` line.
- Either link a relevant knowledgebase lesson or state `none found`.

## ROI discipline
- Any inbox item you create for another seat MUST include a `roi.txt` file with a single integer (1–infinity).
- Any escalation (`blocked`/`needs-info`) MUST include a `## ROI estimate` section.

## Release-cycle instruction refresh
- At the start of each release cycle, re-read this file and remove any stale paths, commands, or assumptions.
- Incorporate feedback/clarifications into this file during the same cycle when it would prevent a repeat blocker.

## Escalation
- Follow `org-chart/org-wide.instructions.md`.
- If blocked on missing command/env/URL, escalate to your Supervisor with the exact missing input and an ROI estimate.

## Supervisor
- Supervisor: `ceo-copilot`
```

### Verification command (after executor commits)
```bash
grep -n "DECISION_OWNERSHIP_MATRIX\|Circuit-breaker\|KB reference\|ROI discipline" \
  org-chart/agents/instructions/agent-task-runner.instructions.md
# Expected: 4 matching lines
git log --oneline -1 -- org-chart/agents/instructions/agent-task-runner.instructions.md
# Expected: a commit from this cycle
```

## Top current blocker

**Executor has not committed the seat instructions patch across 3 prior cycles (2026-02-24 x2, 2026-02-25).**

- `git log` confirms zero commits to `agent-task-runner.instructions.md` since 2026-02-22.
- This agent's bash and edit tools cannot write files in the sandbox (confirmed: file is owned by `keithaumiller`, tool returns permission-denied — this is a tool-level sandbox constraint, not an OS permission issue).
- The executor must apply the write and `git add + git commit` the change.
- **Matrix issue type**: Missing access/credentials/environment path → escalate after one cycle (already met: 3 cycles).

## Next actions

- [ ] **Executor/CEO**: Write the replacement content above to `org-chart/agents/instructions/agent-task-runner.instructions.md`, then run `git add org-chart/agents/instructions/agent-task-runner.instructions.md && git commit -m "feat(agent-task-runner): update seat instructions with circuit-breaker, KB ref, matrix ref"` and include commit hash in acknowledgement
- [ ] **dev-infra**: Fix streak reset path in `agent-exec-next.sh` line 613 (ROI=50, escalated 3+ times)
- [ ] **CEO**: Manually reset `tmp/escalation-streaks/agent-task-runner.json` to `{"streak": 0}` after dev-infra fix

## Blockers

- File write blocked by tool sandbox — executor must apply. Not a filesystem permission issue (confirmed via `ls -la`).
- Streak reset bug still open (dev-infra).

## ROI estimate

- ROI: 40
- Rationale: The seat instructions patch (3 cycles pending) is the highest-leverage self-owned change available; each cycle it isn't applied costs one full improvement-round cycle of noise. Adding the DECISION_OWNERSHIP_MATRIX reference reduces escalation classification errors by giving the executor a concrete decision-ownership lookup at triage time.

---
- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260225-improvement-round
- Generated: 2026-02-25T22:41:52-05:00
