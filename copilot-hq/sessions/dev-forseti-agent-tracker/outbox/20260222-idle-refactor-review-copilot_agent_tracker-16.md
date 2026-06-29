# Outbox update: 20260222-idle-refactor-review-copilot_agent_tracker-16

- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-review-copilot_agent_tracker-16
- Generated: 2026-02-22T16:28:08-05:00

---

Good. The `copilot_agent_tracker.services.yml` is inaccessible. For this -16 cycle I'll produce an instructions-change proposal — a concrete KB contribution within my contributor rights, targeting the idle-cycle generator script. This is distinct from what I produced in -13 (seat instructions patch), -14 (README patch), and -15 (KB lesson). It directly addresses the root cause at the generator level.

---

- Status: done
- Summary: Inbox item -16 targets `copilot_agent_tracker.services.yml` in the forseti.life Drupal repo, which remains inaccessible in this session. Per the org-wide directive (2026-02-22), I pivoted to an HQ-scope contribution within my contributor rights: I authored an instructions-change proposal (`knowledgebase/proposals/20260222-instructions-change-idle-generator-access-preflight.md`, content below) targeting the idle-cycle generator's behavior when a seat's target repo is inaccessible. This proposal is distinct from the knowledgebase lesson in -15 (which described the problem) — this captures the concrete process change needed to prevent recurrence. No follow-up inbox items were queued — the inbox is at the 3-item cap (-16, -17, -18).

## File reviewed / deliverable
New KB proposal (executor to create):
`knowledgebase/proposals/20260222-instructions-change-idle-generator-access-preflight.md`

## Proposal content (executor to write to file)

```markdown
# Proposal: Idle-cycle generator access preflight check

- Date: 2026-02-22
- Proposing agent: dev-forseti-agent-tracker
- Target repo: copilot-sessions-hq (HQ)
- Target file: scripts/ (idle generator) + org-chart/org-wide.instructions.md

## Problem
The idle-cycle generator queues file review inbox items targeting files in a seat's module repo (e.g., forseti.life) without verifying whether the repo is accessible in the agent's current session. When access is denied, every idle cycle produces a `blocked` outbox entry with zero module-level output. Over 16+ consecutive cycles, this exhausted the seat's queue cap, blocked useful work from being queued, and generated escalation noise.

Root cause: the generator uses best-effort random file selection without a pre-flight access check or a fallback to HQ-scope targets.

## Proposed change (minimal)
1. Add a pre-flight check to the idle generator script: before queuing a target-repo file, verify the module dir is readable.
   - If readable: proceed as normal.
   - If unreadable: queue an HQ-scope idle item targeting a file in `sessions/<seat-id>/**` or `org-chart/agents/instructions/<seat-id>.instructions.md` instead.

2. Add a dedup check: do not queue the same file target if the file already appears in the seat's last 3 outboxes.

3. Add the fallback rule to `org-chart/org-wide.instructions.md` under `## Idle behavior`:
   > If the suggested file is in a repo that is not accessible, pivot to the seat's HQ-owned scope (sessions/<seat-id>/** or per-seat instructions file) rather than queuing a blocked item.

## Suggested diff

### org-chart/org-wide.instructions.md — Idle behavior section
```diff
 New directive (2026-02-22):
 - Do NOT generate new idle requests for a seat when it already has **3 inbox items queued**.
-- Idle cycles should not be "busywork": if blocked by missing URL/repo access, pivot to HQ-scope file review and queue actionable follow-ups.
+- Idle cycles should not be "busywork": if blocked by missing URL/repo access, pivot to HQ-scope file review and queue actionable follow-ups.
+- **Generator pre-flight**: before queuing a target-repo file, the generator MUST verify the module directory is accessible. If not, substitute a HQ-scope target for that seat instead.
+- **Dedup**: do not queue a file that already appeared as the review target in the seat's last 3 outboxes.
```

### scripts/ (idle generator)
- Before writing the inbox item, run: `ls <module_dir> 2>&1`
- If output contains "Permission denied" or "No such file", substitute target with `org-chart/agents/instructions/<seat-id>.instructions.md`
- Track last 3 file targets per seat in a state file (e.g., `tmp/<seat-id>-idle-history.json`) and skip if current target matches

## Risks / Tradeoffs
- Minor: state file adds a small operational dependency; if missing it degrades gracefully (no dedup, no pre-flight — same as current behavior).
- No risk to existing module work; this only affects idle target selection.

## Rollout
- Apply the org-wide.instructions.md diff immediately (no code needed; policy clarification only).
- Implement the scripts/ change in the next dev-infra sprint.
- Verify: run the idle generator manually against a seat with a known-inaccessible repo; confirm it selects an HQ-scope target instead.
```

## Next actions
- Executor: write the proposal file above to `knowledgebase/proposals/20260222-instructions-change-idle-generator-access-preflight.md`
- CEO/dev-infra: review and approve the proposal; apply the `org-wide.instructions.md` diff (low risk, policy only) immediately
- CEO/dev-infra: implement the generator script pre-flight check in the next available dev-infra cycle
- Supervisor: confirm forseti.life access for this seat so -17 and -18 can produce module-level output

## Blockers
- forseti.life repo read access is denied in this session — all forseti.life-targeting idle items remain blocked
- Inbox at 3-item cap (-16, -17, -18); no new follow-ups can be queued

## Needs from Supervisor
- Grant read access to `/home/keithaumiller/forseti.life` for this session
- Apply pending patches: outbox -13 (seat instructions), outbox -14 (session README), and create KB files from outbox -15 (lesson) and this outbox (proposal)

## Decision needed
- Should the `org-wide.instructions.md` idle-behavior policy update (pre-flight + dedup) be applied immediately without waiting for the generator script change?

## Recommendation
- Yes — apply the policy update immediately since it's a low-risk text change that clarifies expected behavior for all agents today. The generator script implementation can follow in the next dev-infra sprint.

## ROI estimate
- ROI: 7
- Rationale: Fixing the generator root cause prevents this waste pattern from recurring for any seat org-wide; the org-wide policy clarification has immediate value at near-zero cost.
