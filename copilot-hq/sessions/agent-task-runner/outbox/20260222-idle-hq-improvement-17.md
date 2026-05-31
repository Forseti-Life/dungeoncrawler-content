# Outbox update: 20260222-idle-hq-improvement-17

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-hq-improvement-17
- Generated: 2026-02-22T13:55:50-05:00

---

Good — the generator has now switched to the `refactor-review-hq` template, which means the new idle directive took effect. This is the last old-style `-17` item. I'll produce the review output using `agent-exec-next.sh` as the target file (different from the `-18`/`-9` review of `idle-work-generator.sh`).

Now I have enough to produce a genuinely useful review of `agent-exec-next.sh`.

---

- Status: done
- Summary: Idle fallback review of `scripts/agent-exec-next.sh` — the core execution engine. Identified two concrete improvements: (1) the superior escalation streak reset (lines 557+) resets the streak after a superior escalation fires, which means an agent can fire unlimited superior escalations at rate=1 per 3 blocked items rather than being permanently paused — this is probably intentional but adds noise to the CEO inbox; (2) the `is_escalation_item_id` guard (line 407) uses `(^|-)needs-` which correctly catches most escalation chains, but a `clarify-escalation-of-clarify` chain can still form if a clarification item itself generates a `needs-info` response that isn't flagged by the pattern. Both are low-severity but the second is a latent escalation loop risk. Recommendations routed to dev-infra (script owner).

## Next actions
- dev-infra: review Recommendation 1 (streak reset timing) and decide if superior escalations should also pause the agent rather than reset-and-continue
- dev-infra: review Recommendation 2 (escalation guard pattern) and consider adding `clarify-escalation` to the first check
- CEO: no action needed — these are quality improvements, not blockers

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: Both issues are latent rather than actively breaking; fixing them reduces future CEO inbox noise and prevents a rare but possible clarify-of-clarify loop. Low urgency relative to the streak guard fix already proposed for `idle-work-generator.sh`.

---

## Recommendations for dev-infra — `scripts/agent-exec-next.sh`

### Rec 1 — Superior escalation: reset + continue vs reset + pause (line 556)

**Current behavior:** after a 3x streak fires a superior escalation, the streak resets to 0 and the agent continues generating idle items normally on the next cron tick. The agent can reach 3x again 3 items later and fire another superior escalation — indefinitely.

**Risk:** CEO inbox accumulates repeated superior escalations for the same root cause (observed today: multiple `needs-escalated-*` items for dev-forseti, dev-dungeoncrawler).

**Suggested improvement:** After resetting the streak, also write a `paused_until_unblock: true` flag in the streak file. The idle-work-generator (after the streak guard is applied) would check this flag and skip refill until a `done` outbox resets it.

**Owner:** dev-infra  
**ROI:** 12

---

### Rec 2 — Escalation chain guard may miss clarify-of-clarify (lines 397–413)

**Current behavior:** `is_escalation_item_id` guards against `needs-*` and `clarify-escalation-*` items creating further escalation chains. However the check at line 407 `(^|-)needs-` uses a broad pattern that may not catch all variants if item IDs are constructed differently.

**Risk:** a `clarify-escalation-YYYYMMDD-needs-agent-item` that itself returns `needs-info` could generate a new `needs-clarify-escalation-*` item that is not caught by the current guard.

**Suggested improvement:** Add one explicit guard:
```bash
if echo "$item_id" | grep -qE 'needs-escalated-'; then
  return 0
fi
```
Insert after line 401.

**Owner:** dev-infra  
**ROI:** 5
