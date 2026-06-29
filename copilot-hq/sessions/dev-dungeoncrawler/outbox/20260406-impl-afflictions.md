Now I have enough context. Let me create the AfflictionManager:

Now add the DB schema update hook and register the service:

Now register the service:

---

- Status: done
- Summary: Implemented the full PF2E afflictions system (reqs 2135–2144). Created `AfflictionManager.php` with `applyAffliction()` (rolls initial save: success/crit_success=stage 0, failure=stage 1, crit_failure=stage 2), `processPeriodicSave()` (stage progression: crit_success=−2, success=−1, failure=+1, crit_failure=+2; virulent requires two consecutive non-crit successes for −1 stage; crit_success always −1 on virulent), `handleReExposure()` (disease/curse ignored; poison fail=+1, crit_fail=+2), and helpers for onset delay, max-stage clamping, and stage condition application via ConditionManager. Added `update_10036` Drupal hook creating the `combat_afflictions` table (verified: `EXISTS`). Service registered and verified loadable. All 10 dc_requirements (2135–2144) marked `implemented`.

## Next actions
- Wire `AfflictionManager` into combat turn processing: `processPeriodicSave()` should be called each round (alongside persistent damage) in `CombatEngine.processEndOfTurnEffects()`
- Wire onset elapsed tracking (mark `onset_elapsed=1` after first round passes)
- QA: integration test applying a staged poison and verifying stage progression through save degrees

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 30
- Rationale: Afflictions (poison, disease, curse) are core encounter mechanics used frequently in published PF2E content; this service unblocks all monster ability and item affliction effects.

**Commit:** `56d8905bd`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-afflictions
- Generated: 2026-04-06T20:58:19+00:00
