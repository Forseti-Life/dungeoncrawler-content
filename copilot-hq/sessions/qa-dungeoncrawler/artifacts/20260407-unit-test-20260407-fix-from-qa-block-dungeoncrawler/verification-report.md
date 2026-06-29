# Verification Report: Unit Test — 20260407-fix-from-qa-block-dungeoncrawler
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Dev commit: 663dbd92a (2026-04-07T01:06 UTC)
- Verdict: APPROVE — all 3 QA BLOCK gaps resolved, no regressions

## Scope
Targeted unit-test for fix item `20260407-fix-from-qa-block-dungeoncrawler`.
Fixes GAP-2278, GAP-2280, GAP-2281 from roadmap QA BLOCK `3c6df079e` (REQs 2267–2289).
Sources: `EncounterPhaseHandler.php`, `CombatEngine.php`.

## Prior QA BLOCK reference
- Roadmap QA BLOCK: `3c6df079e` at 2026-04-07T00:43 UTC — 18/23 PASS
- Gaps identified: GAP-2278 (hearing for invisible), GAP-2280 (hero_point_reroll dead letter), GAP-2281 (heroic_recovery_all_points missing)
- This fix commit was separately confirmed during `20260406-unit-test-20260406-impl-senses-detection-hero-points` (commit `122526060`): APPROVE 23/23 PASS

## Fix verification

### GAP-2280: hero_point_reroll — FIXED
- EPH line 200: `hero_point_reroll` registered in `getLegalIntents()`
- EPH lines 837–858: case handler deducts 1 hero point from entity_ref, delegates to `Calculator::heroPointReroll()`, logs event
- EPH line 2344: action cost = 0 in `getActionCost()`
- Correct: free action, costs 1 hero point, returns reroll result

### GAP-2281: heroic_recovery_all_points — FIXED
- EPH line 202: `heroic_recovery_all_points` registered in `getLegalIntents()`
- EPH lines 862–891: case handler clears entity_ref hero_points to 0, delegates to `HPManager::heroicRecoveryAllPoints()` (removes dying, no wounded added, HP stays at 0)
- EPH line 2346: action cost = 0 (reaction) in `getActionCost()`
- Correct: reaction, spends all hero points, removes dying condition

### GAP-2278: hearing imprecise sense for invisible targets — FIXED
- CombatEngine lines 1076–1085: when `$visual_state` is `undetected` or `unnoticed`, hearing fallback upgrades state to `hidden`
- Blocked correctly if `$attacker_entity['deafened']` is true or `$target_entity['silenced']` is true
- Matches PF2e base rules: invisible creatures are hidden (not undetected) to hearing attackers

## Regression check
- No changes to existing `getLegalIntents()` entries, existing sense resolution branches, or `getActionCost()` existing cases
- Fix is strictly additive (78 lines added, 0 modified/deleted per commit stat)
- `determineDegreeOfSuccess()`, MAP logic, action economy: unaffected

## Cross-reference
- Confirmed independently via senses unit-test: `sessions/qa-dungeoncrawler/artifacts/20260406-unit-test-20260406-impl-senses-detection-hero-points/verification-report.md` (APPROVE 23/23)

## Site audit
- Run: 20260407-020452 (most recent — same session)
- Result: CLEAN — 0 errors, 0 permission violations, 0 config drift

## KB reference
- Fix-commit pattern confirmed: commit 663dbd92a is strictly additive — safest fix pattern; no regression surface.

## Verdict
APPROVE: GAP-2278, GAP-2280, GAP-2281 all resolved. No regressions introduced.
