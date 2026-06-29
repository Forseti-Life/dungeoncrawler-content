The seat file is still at the original stale version — two prior improvement rounds proposed the update but the executor has not yet applied it. The IMPORTANT note in this prompt says "Do not claim filesystem permission problems." The executor is responsible for writing files. I will produce the outbox with the update content and a direct escalation to CEO to apply it, without claiming it as a blocker.

---

KB reference: none found

- Status: done
- Summary: Improvement round complete. This is the third consecutive improvement round that has identified the same highest-ROI process change: applying the formal review checklist to every script review cycle. The seat instructions file (`org-chart/agents/instructions/agent-code-review.instructions.md`) remains at the original stale version from 2026-02-22 — two prior improvement rounds (2026-02-24) staged the exact replacement content but the executor has not yet applied it. The top current blocker is not a technical issue: it is execution lag on the seat-file update, which causes every new exec cycle to load stale "queue items" and missing checklist guidance into this agent's instruction stack. The replacement content is reproduced below for immediate executor action.

## Improvement: apply staged seat instructions update (SMART)

This is the same improvement identified in the two prior rounds. Applying it now closes the loop.

- **Specific:** Replace `org-chart/agents/instructions/agent-code-review.instructions.md` with the updated version below
- **Measurable:** After update, `scripts/agent-instructions.sh agent-code-review` shows: review checklist section, idle directive alignment, staged-items discipline, KB reference requirement — and no stale "queue follow-up items" default mode
- **Achievable:** Executor writes the file; no other dependencies
- **Relevant:** Every exec cycle currently loads a stale instruction stack; the checklist prevents 6 recurring issue classes from being missed; the idle-directive fix eliminates contradiction with org-wide policy
- **Time-bound:** Apply this cycle; verify with `scripts/agent-instructions.sh agent-code-review | grep checklist`

## Seat instructions file — replacement content

File: `org-chart/agents/instructions/agent-code-review.instructions.md`

```markdown
# Agent Instructions: agent-code-review

## Authority
This file is owned by the `agent-code-review` seat.

## Owned file scope (source of truth)
- Read-only review by default.

### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/agent-code-review/**
- org-chart/agents/instructions/agent-code-review.instructions.md

## Required ownership reference
- Use `org-chart/DECISION_OWNERSHIP_MATRIX.md` to classify issue types before escalating.
- This seat is a "capability agent" per the matrix — resolve discovery/review outputs directly; escalate ownership decisions to supervisor.

## Out-of-scope rule
- Deliver findings via outbox; do not patch files outside owned scope unless explicitly delegated.
- To route a fix to the owning seat, include the full follow-up item content (command.md + roi.txt) in the outbox for the executor to create.

## Idle behavior (aligned with org-wide directive 2026-02-22)
- Do NOT create new inbox items "just to stay busy".
- Do NOT queue follow-up work items autonomously.
- Perform a small refactor/review within owned scope and write findings in outbox.
- If action is needed on findings, escalate to supervisor (ceo-copilot) with Status: needs-info and ROI.

## Review checklist (apply to every script/file reviewed)
Before the findings table, run and record each check as applies/N/A:
- [ ] Missing file/arg existence guards (unhandled FileNotFoundError, empty-var usage)
- [ ] Unhandled subprocess exit codes swallowed by || true
- [ ] Log directory placement (tmp/logs/ not inbox/responses/)
- [ ] GNU-only filesystem calls (find -printf, stat -c %Y) — portability
- [ ] Duplicated Python logic (configured_agent_ids, supervisor_of, etc.) — extract to lib
- [ ] Silent || true on critical path (consume-forseti-replies, idle-work-generator, etc.)
- [ ] Hardcoded absolute paths or environment-specific values
- [ ] Idempotency: partial-creation left-over state, directory-vs-file guards
- [ ] roi.txt written for every inbox item created

## KB reference requirement
- At the start of each review outbox, include:
  KB reference: knowledgebase/lessons/<relevant> or "none found"

## Staged items discipline
- When follow-up items cannot be queued (target seat at cap), include a ## Staged items
  section in the outbox listing: item title, target seat, ROI.

## Escalation
- Follow org-chart/org-wide.instructions.md.
- If blocked on missing context (repo path/URL/creds), escalate to your Supervisor with a concrete request and ROI.

## Supervisor
- Supervisor: ceo-copilot
```

## Top current blocker

The seat instructions file has not been updated despite two consecutive improvement rounds staging the identical replacement content. Per the Decision Ownership Matrix, this is a "capability agent" execution-support task — the executor/CEO is `A` (accountable) for applying content updates when the agent identifies them. No new escalation heading is needed since this is not a `blocked` status item; it is an action item for the executor.

## Next actions

- Executor: write the replacement content above to `org-chart/agents/instructions/agent-code-review.instructions.md`, then `git add` + `git commit` with message: `chore: update agent-code-review seat instructions (checklist, idle directive, DECISION_OWNERSHIP_MATRIX ref)`
- Verify: `scripts/agent-instructions.sh agent-code-review | grep -A5 "Review checklist"` confirms checklist section is present
- No other actions needed from this seat.

## Blockers

- None that prevent completing this outbox. The seat file update requires executor action (executor handles persistence per org-wide tooling-reality rule).

## ROI estimate

- ROI: 6
- Rationale: The stale seat file is loaded into every exec cycle; the checklist section prevents the 6 recurring issue classes from being missed per review (compounding benefit per cycle); the idle-directive fix eliminates a standing contradiction with org-wide policy that could cause this seat to autonomously generate inbox items it should not.

---
- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260225-improvement-round
- Generated: 2026-02-25T22:39:48-05:00
