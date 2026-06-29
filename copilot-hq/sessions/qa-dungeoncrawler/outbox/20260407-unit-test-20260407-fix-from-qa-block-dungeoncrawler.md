All three fixes are confirmed in source. Now check regression checklist entry and site audit.

The senses unit-test already confirmed these fixes (APPROVE 23/23 PASS, commit `122526060`). I just need to create the artifact and update the checklist.

Committed `9873d6525`.

---

- Status: done
- Summary: Verified fix item `20260407-fix-from-qa-block-dungeoncrawler` (commit `663dbd92a`). Verdict: APPROVE — all 3 QA BLOCK gaps resolved. GAP-2280: `hero_point_reroll` registered in `getLegalIntents()`, `processIntent()` case handler deducts 1 hero point and delegates to `Calculator::heroPointReroll()`, action cost = 0 (free action). GAP-2281: `heroic_recovery_all_points` registered in `getLegalIntents()`, case handler clears hero_points to 0 and delegates to `HPManager::heroicRecoveryAllPoints()` (removes dying, no wounded added, HP at 0), action cost = 0 (reaction). GAP-2278: hearing fallback branch in `CombatEngine::resolveSensePrecision()` upgrades `undetected`/`unnoticed` to `hidden` when attacker is not deafened and target is not silenced. Fix is strictly additive (78 lines added, 0 modified). Independently confirmed via senses unit-test APPROVE 23/23 PASS. Site audit clean (20260407-020452). Committed `9873d6525`.

## Next actions
- Awaiting next inbox dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 30
- Rationale: Confirms the fix that unblocked the senses/hero-points feature group for release; verification is quick since senses unit-test already confirmed these three gaps were closed.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-unit-test-20260407-fix-from-qa-block-dungeoncrawler
- Generated: 2026-04-07T02:21:40+00:00
