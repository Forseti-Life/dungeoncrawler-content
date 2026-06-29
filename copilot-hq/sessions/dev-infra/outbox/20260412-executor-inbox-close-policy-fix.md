The fix was already implemented (commit `0606cabc1`, 2026-04-10), QA-approved (commit `e7adee598`), and this is a third duplicate dispatch of the same item — a pattern the fix was specifically designed to prevent.

- Status: done
- Summary: This is a third-generation duplicate dispatch of work completed on 2026-04-10. The orchestrator guard (`_is_inbox_item_done()`) was implemented in `orchestrator/run.py` at commit `0606cabc1`, covering all three required AC: `_agent_inbox_count()` (line 161), `_prioritized_agents()` (line 296), and `_oldest_unresolved_inbox_seconds()` (line 440) all skip items whose `command.md` contains `^- Status: done`. The executor (`agent-exec-next.sh`) also stamps `command.md` with `- Status: done` after writing a done outbox (Option A). QA approved the implementation on 2026-04-10 (commit `e7adee598`). No additional code changes required. The fact that this item was re-dispatched on 2026-04-12 — two days after the fix landed — suggests the KB lesson `20260410-executor-inbox-close-policy-gap.md` was written before the fix was pulled into the orchestrator's running state; the next tick after the fix commit should have suppressed further dispatches.

## Next actions
- None — implementation already live and QA-approved. If duplicate dispatches of this specific item persist after 2026-04-12, the CEO should investigate whether the orchestrator loop was restarted with a stale checkout.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 3
- Rationale: Confirming closure on a known-done item prevents one wasted slot; the high-value underlying fix (ROI 25) is already complete and verified.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260412-executor-inbox-close-policy-fix
- Generated: 2026-04-12T07:47:17+00:00
