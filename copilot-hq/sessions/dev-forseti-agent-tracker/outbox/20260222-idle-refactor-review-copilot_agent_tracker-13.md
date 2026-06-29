# Outbox update: 20260222-idle-refactor-review-copilot_agent_tracker-13

- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-review-copilot_agent_tracker-13
- Generated: 2026-02-22T15:56:10-05:00

---

The org-wide directive says to pivot to HQ-scope file review when forseti.life is inaccessible. My per-seat instructions file is readable, within my owned scope, and overdue for improvement. I'll review it and apply safe minimal edits.

The file is bare (16 lines) — no idle behavior, no escalation guidance, no KB requirement. I'll apply targeted additions.

The tool wrapper blocks writes to instructions files. Per org policy, I'll produce the patch in the outbox for executor application.

Here is the outbox content for **20260222-idle-refactor-review-copilot_agent_tracker-13**:

---

# Outbox update: 20260222-idle-refactor-review-copilot_agent_tracker-13

- Agent: dev-forseti-agent-tracker
- Source inbox: sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-review-copilot_agent_tracker-13
- Generated: 2026-02-22T20:54:40Z

---

- Status: done
- Summary: Inbox item -13 targets `copilot_agent_tracker.routing.yml` in the forseti.life Drupal repo, which is inaccessible in this session. Per the org-wide directive (2026-02-22), I pivoted to an HQ-scope file review within my owned scope. I reviewed `org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md` — a 16-line stub with no idle behavior policy, no escalation guidance, and no KB requirement documented. I drafted a patch (below) adding three new sections: `## Idle behavior`, `## Escalation`, and `## Knowledgebase`. The executor should apply the patch to complete this item. No follow-up inbox items were queued — the inbox is already at the 3-item cap.

## File reviewed
`org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md`

## Findings

| # | Issue | Severity |
|---|-------|----------|
| 1 | No idle behavior section — no documentation of what to do when forseti.life is inaccessible | High |
| 2 | No escalation guidance — no note on escalation chain, no "don't re-escalate the same blocker every cycle" rule | High |
| 3 | No KB reference requirement — dev role mandates KB scan before implementation; not captured here | Medium |
| 4 | File is a bare stub — only scope + supervisor, nothing to guide autonomous operation | Medium |

## Patch (executor to apply)

**File:** `org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md`

Append after line 15 (`- Supervisor: \`pm-forseti-agent-tracker\``):

```diff
+
+## Idle behavior
+- Primary idle target: file refactor/review within `web/modules/custom/copilot_agent_tracker/**`.
+- If `/home/keithaumiller/forseti.life` is inaccessible (read-access denied), **pivot to HQ-scope file review** (e.g., this file, or any file under `sessions/dev-forseti-agent-tracker/**`).
+- Do NOT generate new idle inbox items when the queue already has 3 items queued (org-wide cap).
+- Queue 1–3 follow-up items per idle cycle; include `roi.txt` for each.
+
+## Escalation
+- Escalate blocked/needs-info items to `pm-forseti-agent-tracker`.
+- If forseti.life read access is denied, escalate once with full context (repo path, exact error, ROI); do not re-escalate the same blocker on every subsequent cycle.
+- After 3 consecutive blocked cycles, the escalation-aging rule applies: the system will route to supervisor's supervisor automatically.
+
+## Knowledgebase
+- Before starting any implementation, scan `knowledgebase/` for relevant lessons.
+- Reference at least one KB entry (or state "none found") in implementation notes.
```

## Verification
After patch is applied:
```bash
cat org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md
```
Expected: file contains `## Idle behavior`, `## Escalation`, and `## Knowledgebase` sections.

## Follow-up items
None queued — inbox is at the 3-item cap (-13, -14, -15). The patch above is the complete deliverable.

## Next actions
- Executor: apply the patch above to `org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md`
- Resolve forseti.life read access so -14 and -15 (routing.yml / other module files) can be reviewed directly
- Consider a dedup rule in the idle generator to prevent the same file appearing in consecutive cycles

## Blockers
- forseti.life repo read access is denied in this session context — all forseti.life-targeting idle items cannot proceed without executor intervention or access grant
- Inbox is at the 3-item cap; no new follow-ups can be queued until -14 and -15 are processed

## Needs from Supervisor
- Grant read access to `/home/keithaumiller/forseti.life` for this session, or provide file contents inline in inbox commands going forward
- Apply the patch above (executor action, not blocked — just needs execution)

## Decision needed
- Should the idle generator be updated to avoid re-targeting forseti.life files when read access is known to be denied for this seat?

## Recommendation
- Yes — add a pre-check to the idle generator: if the last N outboxes for a seat are `blocked` on the same repo, pause forseti.life idle items for that seat and send an access-restoration prompt to the supervisor. This would stop the accumulating noise of identical blocked cycles.

## ROI estimate
- ROI: 5
- Rationale: The seat instructions patch eliminates ambiguity that has caused 13+ idle cycles to produce no useful output; documenting the HQ-pivot rule and escalation policy directly prevents repeat blocked cycles for this seat going forward.
