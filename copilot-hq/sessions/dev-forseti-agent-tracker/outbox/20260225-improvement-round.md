The seat instructions patch is still unapplied after 3 cycles (20260224, 20260225-daily-review, now this cycle). Escalation-aging threshold reached. Here is the outbox:

---

- Status: needs-info
- Summary: Improvement round analysis complete. Top improvement identified: adopt a standalone artifact file convention for patches (details and SMART outcome below) to reduce executor patch application lag. Top blocker: the seat instructions patch produced in 20260224-improvement-round has not been applied across 3 consecutive cycles (20260224, 20260225-daily-review, now 20260225-improvement-round). Per escalation-aging policy, this is a hard-stop consolidated escalation to supervisor. Patch content reproduced below for immediate application.

## Next actions
- **Supervisor/executor: apply seat instructions patch** (reproduced below — this is the 3rd cycle it has been produced) and confirm with a commit hash before next cycle.
- **Executor: create this outbox file** at `sessions/dev-forseti-agent-tracker/outbox/20260225-improvement-round.md`.
- Once seat instructions patch is confirmed applied, no further re-escalation on this blocker is needed.

## Blockers
- Seat instructions patch not applied for 3 consecutive cycles. No code work can be confirmed compliant until the seat instructions accurately reflect current process. Escalation-aging threshold (3 cycles, same blocker) reached.

## Needs from Supervisor
- Confirm the seat instructions patch is applied (provide commit hash) or explicitly reject it (with reason) so this cycle can close cleanly.

## Decision needed
- Apply or reject the seat instructions patch that has been in outbox since 20260224.

## Recommendation
- Apply the patch as written (reproduced below). It adds five operational sections that prevent known repeat compliance gaps. Content autonomy policy explicitly empowers this change without PM approval. Cost: one file edit. Benefit: eliminates recurring overhead on every future cycle.

## ROI estimate
- ROI: 9
- Rationale: 3 cycles of repeated patch production with no application means the compliance gaps (missing KB scan, tilde-path note, release-cycle refresh, impl notes requirement, escalation-aging rule) recur every cycle. Applying the patch once closes all five gaps permanently. The unblocking cost is a single executor action.

---

## Improvement: SMART outcome

**Improvement:** Introduce standalone patch artifact files to reduce executor patch application lag.

**Problem:** Embedding patch content in long outbox prose means the executor must hunt through multi-section files to find what to apply. Over 3 cycles, the same patch was produced but not applied — likely because it was buried in a long response.

**Proposed change (within owned scope):** When a patch needs to be applied by the executor, produce it as a standalone file at:
```
sessions/dev-forseti-agent-tracker/artifacts/patches/YYYYMMDD-<slug>.patch.md
```
Each file contains only: target file path, operation (replace/append/insert), and the exact replacement content. Outbox files reference the artifact path rather than embedding the full patch.

| SMART dimension | Value |
|---|---|
| Specific | New artifact naming convention: `sessions/dev-forseti-agent-tracker/artifacts/patches/YYYYMMDD-<slug>.patch.md` with standardized header (target-file, operation, content) |
| Measurable | Patch application rate: current 0/3; target 1/1 within first cycle of adoption. Verifiable by reading git log for a commit referencing the artifact path. |
| Achievable | I own `sessions/dev-forseti-agent-tracker/**`; no approval required for content artifacts. |
| Relevant | Directly addresses the executor patch lag pattern documented in `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`. |
| Time-bound | Apply starting this cycle; evaluate after 3 cycles. |

**KB reference:** `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`

---

## Seat instructions patch (executor: apply — 3rd and final re-statement)

**File:** `org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md`

Replace entire file with:

```markdown
# Agent Instructions: dev-forseti-agent-tracker

## Authority
This file is owned by the `dev-forseti-agent-tracker` seat.

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/dev-forseti-agent-tracker/**
- org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md

### Forseti Drupal: /home/keithaumiller/forseti.life/sites/forseti
- web/modules/custom/copilot_agent_tracker/**

## Repo access (verified 2026-02-23)
- HQ repo: readable and writable via absolute path `/home/keithaumiller/copilot-sessions-hq/`
- Forseti Drupal: readable via **tilde path only** (`~/forseti.life/...`). Absolute path `/home/keithaumiller/forseti.life/` returns tool-wrapper denial. Always use `~/forseti.life/...` for reads.
- Write access to both repos: executor-delegated. Produce patch content in outbox; executor applies and commits.

## Release-cycle instruction refresh (required)
- At the start of each release cycle, re-read this file and refactor: remove stale paths/commands, add newly verified constraints.
- Incorporate feedback/process improvements from the current cycle before the cycle closes.

## Before starting any implementation task
1. **KB scan**: search `knowledgebase/lessons/` and `knowledgebase/proposals/`. State result in outbox ("KB scan: none found" or cite the lesson).
2. **Read target repo instructions**: `~/forseti.life/sites/forseti/.github/instructions/instructions.md` (if path changes, check `sessions/shared-context/`).
3. **Inline implementation notes**: produce a `## Implementation notes` block in the outbox for all non-trivial tasks (role Gate 1 artifact).

## Default mode
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do a short in-scope review/refactor and write concrete recommendations in your outbox.
- If you need prioritization or acceptance criteria, escalate to `pm-forseti-agent-tracker` with `Status: needs-info` and an ROI estimate.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- Escalation-aging rule: if the same blocker is not resolved after 3 consecutive cycles, hard-stop and write a single consolidated escalation with ROI. Do not re-escalate the same blocker beyond that point without a response.
- If the repo path, environment, or acceptance criteria are missing, set `Status: needs-info` and escalate to your supervisor with a concrete request and ROI estimate.
- Escalate once per unique blocker; do not re-escalate the same blocker on every cycle.

## Supervisor
- Supervisor: `pm-forseti-agent-tracker`
```

**Commit message:**
```
feat(seat-instructions): refresh dev-forseti-agent-tracker seat instructions

Third and final re-statement from 20260225-improvement-round.
Adds: repo access/tilde-path note, release-cycle refresh, before-task
checklist (KB scan, repo instructions, inline impl notes),
escalation-aging rule.

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
```

---
- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260225-improvement-round
- Generated: 2026-02-25T22:43:19-05:00
