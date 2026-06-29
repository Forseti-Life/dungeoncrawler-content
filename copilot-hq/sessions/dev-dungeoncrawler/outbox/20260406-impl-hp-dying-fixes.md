Now I have a full picture. Let me implement all 19 bugs/features systematically:

Now I'll implement all the fixes. Starting with the four files:

Now fix `processDying()` for REQ 2158 (DC = 10 + dying_value) and REQ 2165/2166 (doomed reduces death threshold):

Now rewrite HPManager.php with all the fixes:

Now fix `applyHealing()` and `applyDyingCondition()` and `stabilizeCharacter()`:

Now fix `applyDyingCondition()` (REQ 2154: dying 2 on crit):

Now fix `stabilizeCharacter()` (BUG 2+3: HP stays 0, wounded +1 additive):

Now add the new methods (heroicRecovery, naturalRecovery, wakeOnInteract, helpers) and fix evaluateDeath. I'll replace the bottom section of HPManager:

Now fix CombatEngine: pass `is_critical` to applyDyingCondition, add fast healing/regen in startTurn, initiative shift at 0 HP:

That replacement was incorrect. Let me view the current state and fix properly:

Now add fast healing/regeneration to `startTurn()` and the `shiftInitiativeAfterAttacker()` helper at the bottom of CombatEngine:

Now add `shiftInitiativeAfterAttacker()` before the closing brace of CombatEngine:

Now add doomed decrement to DowntimePhaseHandler.processLongRest (REQ 2167):

Now lint all 4 files and cache-clear:

All checks pass. Now mark the requirements and commit:

---

- Status: done
- Summary: Implemented all 19 HP/dying/healing system requirements (2153–2178) across 4 files. Bug fixes: recovery flat check DC now uses `10 + dying_value` (REQ 2158); death threshold is `4 - doomed` (REQ 2165/2166); stabilizeCharacter leaves HP at 0 (REQ 2160) and increments wounded additively (REQ 2161/2162); massive damage check fires on `damage >= 2×maxHP` not final HP (REQ 2173); unconscious condition gains blinded + `−4` status penalties (REQ 2168); doomed decrements by 1 per long rest (REQ 2167). New features: dying 2 on critical hit (REQ 2154); death_effect flag bypasses dying track (REQ 2172); initiative shift to just-after-attacker on defeat (REQ 2153); `heroicRecovery()`, `naturalRecovery()`, `wakeOnInteract()` (REQs 2169–2171); fast healing and regeneration processed in `startTurn()` (REQs 2177–2178); `applyHealing()` removes wounded on full heal and wakes unconscious (REQs 2164, 2170). All 19 reqs marked implemented. Commit: `85dedb946`.

## Next actions
- Await next dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 55
- Rationale: Closes all HP/dying/healing correctness bugs which are foundational to every combat encounter — incorrect dying DC, wrong stabilization HP, and wrong doomed interaction would make all combat outcomes wrong for players with those conditions.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-hp-dying-fixes
- Generated: 2026-04-06T22:14:42+00:00
