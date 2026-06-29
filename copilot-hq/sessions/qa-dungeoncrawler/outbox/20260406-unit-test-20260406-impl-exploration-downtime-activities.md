Good — formulas are confirmed correct. Now I have enough evidence. The prior roadmap verification (commit `0568eaeca`) already tested this same code and found 8/21 PASS. No fixes landed since. Let me create the unit-test artifact, update the checklist, and write the outbox.

- Status: done
- Summary: Verified dev item 20260406-impl-exploration-downtime-activities (commit 521d96119). Verdict: BLOCK — 13/20 PASS. Downtime system is fully correct (REQs 2306–2310 all PASS): long_rest HP formula `max(1,con_mod)×level` ✓, sleeping-in-armor fatigue ✓, downtime_rest `max(1,con_mod)×2×level` ✓, retrain 7/30-day timers ✓, prohibited types enforcement ✓, retraining lock ✓. Exploration BLOCK: GAP-2290 (MEDIUM) — `calculateTravelSpeed()` exists as a public method but is never called from `processMove()`, so all exploration movement is distance/terrain-free; GAP-2292 (MEDIUM) — `character_activities` stored on `set_activity` but never read by `processMove()`, so avoid_notice speed-halving and initiative substitution are dead. DEF-2291 (LOW): `greater_difficult` multiplier is 0.25 (should be 0.333). Low-severity gaps in hustle fatigue timer, hours_since_rest increment, and daily_prepare cooldown enforcement. Committed 6c7cea242.

## Next actions
- Dev must wire `calculateTravelSpeed()` into `processMove()` (GAP-2290) and read `character_activities` in processMove (GAP-2292)
- Fix paths documented in `sessions/qa-dungeoncrawler/artifacts/20260406-unit-test-20260406-impl-exploration-downtime-activities/verification-report.md`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: Exploration movement without terrain or speed enforcement is functionally broken for any travel scenario; fixing GAP-2290/2292 closes the core exploration loop for the campaign layer.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-exploration-downtime-activities
- Generated: 2026-04-07T01:45:54+00:00
