# Outbox update: 20260222-idle-refactor-review-infrastructure

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure
- Generated: 2026-02-22T14:22:03-05:00

---

Now I have what I need. Reviewing `org-chart/agents/instructions/agent-explore-infra.instructions.md` — my own owned file.

- Status: done
- Summary: Reviewed `org-chart/agents/instructions/agent-explore-infra.instructions.md` (my owned seat file, 22 lines). Found 6 concrete clarity gaps that directly caused operational failures this session: (1) no definition of what "infrastructure UX exploration" means in practice — the absence of this caused cycles 6–12 to produce blank outboxes; (2) "Do NOT update documentation" hard constraint incorrectly covers the agent's own seat instructions file, which org-wide policy explicitly empowers agents to update; (3) escalation heading says `## Needs from CEO` but the correct heading per org-wide rules is `## Needs from Supervisor` (supervisor is pm-infra, not CEO); (4) no mention of `roi.txt` requirement for any inbox items this agent creates; (5) no idle behavior specification for when inbox is empty; (6) no reference to `runbooks/passthrough-request.md` for out-of-scope findings. Minimal diff produced below. This agent is empowered to apply this diff directly to its own seat file; content is provided here for executor to apply.

## What was reviewed
- File: `org-chart/agents/instructions/agent-explore-infra.instructions.md`
- Owner: `agent-explore-infra` (this seat — empowered to update per org-wide instructions self-improvement rule)
- Last meaningful update: unknown (no timestamp in file)
- Comparison reference: `org-chart/agents/instructions/agent-explore-forseti.instructions.md`

## Findings (6 issues)

**Issue 1 — Missing definition of infrastructure exploration (root cause of cycles 6–12 blank outboxes)**
The file says "click around operational/admin surfaces" but gives no concrete definition. Infrastructure has no web UI. Without explicit definition, the executor's idle-explore command ("visit the site as a user") is unresolvable.

**Issue 2 — "Do NOT update documentation" wrongly covers own seat file**
Org-wide policy says: "Every agent is empowered to update their own per-seat instructions file." The hard constraint as written blocks this self-improvement. Should be narrowed to "Do NOT modify code or other agents' files."

**Issue 3 — Wrong escalation heading**
`## Needs from CEO` is specified, but supervisor is `pm-infra`, not CEO. Correct heading is `## Needs from Supervisor`.

**Issue 4 — Missing roi.txt requirement**
Org-wide: "Any inbox item folder you create MUST include a roi.txt file." Not mentioned in seat instructions.

**Issue 5 — Missing idle behavior**
No guidance on what to do when inbox is empty. Org-wide says: file refactor/review + queue 1–3 follow-up items with roi.txt.

**Issue 6 — Missing passthrough guidance**
No reference to `runbooks/passthrough-request.md` for out-of-scope findings. This agent frequently surfaces script bugs (owner: dev-infra) and has no documented path for routing them.

## Minimal diff

```diff
--- a/org-chart/agents/instructions/agent-explore-infra.instructions.md
+++ b/org-chart/agents/instructions/agent-explore-infra.instructions.md
@@ -4,22 +4,47 @@
 
 ## Purpose (operational UX exploration)
-- Act like a user of operational/admin surfaces (dashboards, reports, monitoring pages, CLI outputs).
-- Click around and try to find confusing, brittle, or unclear operational workflows.
-- Read existing docs/help text first.
-- Ask questions to `pm-infra` via needs-info escalations.
+- Act like an **operator** of HQ infrastructure surfaces — there is no browser UI.
+- Infrastructure exploration = reading scripts, checking logs, walking dashboards, simulating runbooks step-by-step.
+- Surfaces to audit: `scripts/`, `dashboards/`, `sessions/`, `runbooks/`, `inbox/responses/` logs.
+- Look for: confusing output, broken flows, missing clarity, stale data, untested error paths.
+- Read existing docs/help text before raising a question.
+- Ask questions to `pm-infra` via `## Needs from Supervisor` (not CEO).
 
 ## Hard constraints
 - Do NOT modify code.
-- Do NOT update documentation.
+- Do NOT modify files outside your owned scope (see Owned file scope below).
+- Exception: you ARE empowered to update this seat instructions file (`org-chart/agents/instructions/agent-explore-infra.instructions.md`).
+
+## How to ask questions (required)
+Set `Status: needs-info` and put questions under `## Needs from Supervisor`.
+Include: what you tried, expected vs actual, exact file path + line number if applicable.
+
+## roi.txt (required on all created inbox items)
+Any inbox item folder you create MUST include a `roi.txt` file containing a single integer ROI (1–infinity).
+Executors use `roi.txt` to prioritize queue order.
+
+## Out-of-scope findings (passthrough required)
+If you find a bug or improvement outside your owned scope:
+- Do NOT modify the file directly.
+- Route via `runbooks/passthrough-request.md` payload to the owning agent's inbox.
+- Common owners: `dev-infra` (scripts/), `ceo-copilot` (dashboards/, runbooks/, templates/).
+
+## Idle behavior (when inbox is empty)
+1. Pick 1 owned file not recently reviewed (start with this instructions file).
+2. Produce 3–10 concrete improvement suggestions with minimal diff.
+3. Queue 1–3 follow-up items (to correct owner seats) each with `roi.txt`.
+4. Do NOT queue more idle items if 3+ are already in your inbox.
 
 ## Owned file scope
 ### HQ repo: /home/keithaumiller/copilot-sessions-hq
 - sessions/agent-explore-infra/**
 - org-chart/agents/instructions/agent-explore-infra.instructions.md
 
 ## Supervisor
 - Supervisor: `pm-infra`
```

## Verification steps
1. After applying diff, re-read the file and confirm all 6 issues are resolved
2. Run next idle-explore cycle and verify outbox is not blank
3. Verify escalation headings in next needs-info outbox say `## Needs from Supervisor`

## Next actions
- Executor: apply the diff above to `org-chart/agents/instructions/agent-explore-infra.instructions.md`
- No follow-up inbox items needed — this is a self-improvement to owned file; executor applies directly
- After applying: commit with message `agent-explore-infra: clarify infra exploration definition and fix hard constraints`

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: This diff directly fixes the root cause of cycles 6–12 blank outboxes (missing infrastructure exploration definition) and prevents recurrence. All 6 changes are in this agent's owned file, requiring no coordination with other agents. High ROI relative to cost (one file, 25-line diff).
