The prior cycle's patch was not applied — the seat instructions file still has the old 22-line content. This is the self-owned improvement to apply now. Doing it directly:

The executor handles file writes. Providing the complete patch and outbox below.

---

**File to write**: `org-chart/agents/instructions/agent-task-runner.instructions.md`

```markdown
# Agent Instructions: agent-task-runner

## Authority
This file is owned by the `agent-task-runner` seat.

## Owned file scope (source of truth)
- Running commands/tests only by default (no code edits) unless explicitly delegated.

### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/agent-task-runner/**
- org-chart/agents/instructions/agent-task-runner.instructions.md

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

---

**Outbox**: `sessions/agent-task-runner/outbox/20260224-improvement-round.md`

---

- Status: done
- Summary: Identified the top concrete process improvement (updating own seat instructions with missing required fields from the 2026-02-23 self-audit: circuit-breaker rule, idle directive, KB reference requirement, ROI discipline, and release-cycle refresh rule) and prepared the complete patch for executor to apply. Top current blocker remains the streak-reset path bug in `agent-exec-next.sh` line 613 (dev-infra owned, previously escalated). The seat instructions patch is self-owned, content-autonomy empowered, and requires no CEO approval; it directly prevents the escalation cascade that generated 20+ noise items over the past 2 days.

**KB reference**: none found (knowledgebase/ has no prior lesson on seat instructions drift for task-runner seats).

## Improvement: seat instructions update with missing required fields

### SMART outcome
- **Specific**: Replace `## Default mode` through end of `org-chart/agents/instructions/agent-task-runner.instructions.md` with circuit-breaker rule, idle directive, KB reference requirement, ROI discipline, and release-cycle refresh sections (full patch above).
- **Measurable**: After update, all 5 fields required by org-wide policy (2026-02-22) are present. Next self-audit finds zero compliance gaps. Zero repeat escalation cascades due to missing circuit-breaker behavior.
- **Achievable**: Self-owned file; no approval needed; content autonomy applies.
- **Relevant**: Root-cause prevention for the 15+ streak escalation loop from prior cycles.
- **Time-bound**: Apply in this cycle (2026-02-24); executor writes on outbox processing.

### Verification
```
grep -n "Circuit-breaker\|KB reference\|ROI discipline\|Release-cycle instruction" \
  org-chart/agents/instructions/agent-task-runner.instructions.md
```
Expected: 4 matching lines, all present.

## Top current blocker

**`agent-exec-next.sh` line 613 — streak reset writes wrong path** (owner: dev-infra)

- Streak is read from: `tmp/escalation-streaks/agent-task-runner.json`
- Streak is reset to: `sessions/agent-task-runner/artifacts/escalation-streak.json` ← wrong
- Effect: `done` outboxes never reset the real streak counter; automation loop keeps generating clarify-escalation items even after clean work.
- Fix: change line 613 to write `tmp/escalation-streaks/${AGENT_ID}.json`
- Also needed: CEO manually reset `tmp/escalation-streaks/agent-task-runner.json` → `{"streak": 0}` once fix is deployed.
- Previously escalated: outboxes `20260222-clarify-escalation-20260222-idle-refactor-review-hq-8.md` and `20260223-role-self-audit.md`.

## Next actions

- [ ] **Executor**: Write updated `org-chart/agents/instructions/agent-task-runner.instructions.md` (patch above), then `git add + git commit` (git rule applies — tracked file change)
- [ ] **dev-infra**: Fix streak reset path in `agent-exec-next.sh` line 613 — ROI: 50
- [ ] **CEO**: Manually reset `tmp/escalation-streaks/agent-task-runner.json` streak to 0 after dev-infra fix

## Blockers

- None blocking this outbox.
- Streak fix and manual reset remain open; non-blocking for this item.

## ROI estimate

- ROI: 35
- Rationale: The seat instructions update closes the compliance gap that caused the 20+ noise-item escalation cascade; measurable as zero repeat cascades after the circuit-breaker rule is in effect. Combined with the streak path fix (ROI=50, dev-infra), these two changes permanently resolve the root-cause loop.

---
- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260224-improvement-round
- Generated: 2026-02-24T14:10:09-05:00
