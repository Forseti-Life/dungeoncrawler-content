Good — `getActiveConditions()` gives me what I need. I'll write helper methods `hasCondition` and `getConditionValue` on `ConditionManager`, then implement the three fixes. Let me also check `CombatEngine` constructor:

Now I have full context. Let me implement all three fixes:

**Fix 1 & 2** go in `CombatEngine.startTurn()` — need `hasCondition`, `getConditionValue`, `decrementCondition` helpers on `ConditionManager`.
**Fix 3** goes in `ActionProcessor.executeAction()` — disrupted flag check.

First add the helper methods to `ConditionManager`:

Now fix `CombatEngine.startTurn()` (Fix 1 + Fix 2):

Now Fix 3 — disrupted action handling in `ActionProcessor.executeAction()`:

Disrupted path correctly propagates the "encounter not found" error (encounter_id=0 doesn't exist). Mark req 2180 as implemented (it's a won't-fix architectural note) and reqs 2185/2186/2188/2189:

Commit `613207f8b`. Now write the outbox:

---

- Status: done
- Summary: Action economy fixes (reqs 2185/2186/2188/2189) complete. `ConditionManager` gained three condition helpers; `CombatEngine.startTurn()` now applies quickened/slowed/stunned action adjustments and triggers dying recovery check; `ActionProcessor.executeAction()` short-circuits disrupted actions. Req 2180 marked implemented as architectural won't-fix. Committed `613207f8b`. Outbox written.

## Next actions
- Awaiting next dispatch from pm-dungeoncrawler

## ROI estimate
- ROI: 30

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-action-economy-fixes
- Generated: 2026-04-06T21:31:02+00:00
