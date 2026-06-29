Now I have all the context needed. Let me apply the three changes:

Now add the `periodic_save_results` call inside `processEndOfTurnEffects()`, right before the `return $effects` line:

Now fix `handleReExposure()` in AfflictionManager — add `$encounter_id` param and remove the `?? 0` fallback:

Now wire `AfflictionManager` into `CombatEngine` in services.yml:

All 3 checks pass. Commit:

---

- Status: done
- Summary: Implemented GAP-AFFLICTION-1 and DEF-AFFLICTION-2. `CombatEngine` now accepts `AfflictionManager` as an optional 10th constructor argument (wired in services.yml). `processEndOfTurnEffects()` iterates all active afflictions for the participant and calls `AfflictionManager::processPeriodicSave()` per affliction, collecting results in a new `periodic_save_results` key. `AfflictionManager::handleReExposure()` now has an explicit `int $encounter_id` parameter (default 0) eliminating the undefined-variable silent-zero bug. All three drush verification checks pass; committed `3fb95ebc0`.

## Next actions
- QA-dungeoncrawler: retest GAP-AFFLICTION-1 against commit `3fb95ebc0` — verify `periodic_save_results` present and non-empty for a character with an active affliction at end of turn
- Awaiting next inbox item from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: Affliction periodic saves were a silent no-op — poison and disease had zero combat effect. This wires the system end-to-end and is required before the release can claim the affliction feature complete.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260407-fix-gap-affliction-1-periodic-save-wiring
- Generated: 2026-04-07T01:22:35+00:00
