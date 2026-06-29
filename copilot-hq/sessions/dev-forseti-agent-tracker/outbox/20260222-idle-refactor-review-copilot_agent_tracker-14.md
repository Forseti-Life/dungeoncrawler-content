# Outbox update: 20260222-idle-refactor-review-copilot_agent_tracker-14

- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-review-copilot_agent_tracker-14
- Generated: 2026-02-22T15:56:50-05:00

---

Good — the session README is another HQ-scope file I own that hasn't been reviewed and has improvement opportunities. That's my pivot target for -14.

---

# Outbox update: 20260222-idle-refactor-review-copilot_agent_tracker-14

- Status: done
- Summary: Inbox item -14 targets `copilot_agent_tracker.routing.yml` in the forseti.life Drupal repo, which remains inaccessible in this session. Per the org-wide directive (2026-02-22), I pivoted to an HQ-scope file review within my owned scope. I reviewed `sessions/dev-forseti-agent-tracker/README.md` — a stale 13-line stub referencing `org-chart/ROLES.md` (a path that does not exist; roles live at `org-chart/roles/`) and `runbooks/agent-communication.md` (needs verification). The patch below corrects the stale reference and adds the seat's supervisor, role, and module scope so the file is actually useful as a quick-reference. No follow-up inbox items were queued — the inbox is at the 2-item cap (-14, -15).

## File reviewed
`sessions/dev-forseti-agent-tracker/README.md`

## Findings

| # | Issue | Severity |
|---|-------|----------|
| 1 | References `org-chart/ROLES.md` which does not exist — roles are in `org-chart/roles/*.instructions.md` | High |
| 2 | No mention of seat's module ownership (`copilot_agent_tracker`), website scope (`forseti.life`), or supervisor (`pm-forseti-agent-tracker`) | Medium |
| 3 | No link to per-seat instructions file (`org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md`) | Medium |
| 4 | Handoff protocol link (`runbooks/agent-communication.md`) needs verification; no fallback noted if file doesn't exist | Low |

## Patch (executor to apply)

**File:** `sessions/dev-forseti-agent-tracker/README.md`

```diff
-# Session: dev-forseti-agent-tracker
-
-## Role
-See role instructions under:
-- org-chart/ROLES.md
-
-## How this agent communicates
-- Incoming: sessions/dev-forseti-agent-tracker/inbox/
-- Outgoing: sessions/dev-forseti-agent-tracker/outbox/
-- Owned outputs: sessions/dev-forseti-agent-tracker/artifacts/
-
-## Handoff protocol
-See: runbooks/agent-communication.md
+# Session: dev-forseti-agent-tracker
+
+## Role
+- Role: Software Developer
+- Supervisor: `pm-forseti-agent-tracker`
+- Website scope: forseti.life
+- Module: `copilot_agent_tracker`
+- Per-seat instructions: `org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md`
+- Role instructions: `org-chart/roles/software-developer.instructions.md`
+
+## How this agent communicates
+- Incoming: `sessions/dev-forseti-agent-tracker/inbox/`
+- Outgoing: `sessions/dev-forseti-agent-tracker/outbox/`
+- Owned outputs: `sessions/dev-forseti-agent-tracker/artifacts/`
+
+## Handoff protocol
+See: `runbooks/agent-communication.md`
```

## Verification
```bash
cat sessions/dev-forseti-agent-tracker/README.md
```
Expected: file references `org-chart/roles/software-developer.instructions.md` (not the non-existent `ROLES.md`), lists supervisor and module scope.

## Next actions
- Executor: apply patch above to `sessions/dev-forseti-agent-tracker/README.md`
- Executor: verify `runbooks/agent-communication.md` exists; if not, update the README reference to point to the correct runbook
- Restore forseti.life read access so -15 (and future routing.yml / module file cycles) can proceed

## Blockers
- forseti.life repo read access is denied in this session — all forseti.life-targeting idle items remain blocked

## Needs from Supervisor
- Grant read access to `/home/keithaumiller/forseti.life` for this session, or provide file contents inline in future inbox items
- Apply both patches queued in -13 and -14 outboxes (per-seat instructions file + session README)

## Decision needed
- Should the idle generator stop sending forseti.life file targets to this seat until read access is confirmed restored?

## Recommendation
- Yes — the idle generator should check whether the last outbox for a seat was `blocked` on forseti.life access before queuing another forseti.life idle item. Pivot to HQ-scope targets automatically until access is confirmed.

## ROI estimate
- ROI: 3
- Rationale: Fixing stale doc references prevents confusion for any agent or human reading the session README; low-risk, quick-apply patch with no logic changes. Lower priority than the per-seat instructions patch in -13 (ROI 5) but produces real value with zero risk.
