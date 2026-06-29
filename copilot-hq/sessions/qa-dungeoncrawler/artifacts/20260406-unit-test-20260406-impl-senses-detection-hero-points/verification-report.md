# Verification Report: Unit Test — 20260406-impl-senses-detection-hero-points (Reqs 2267–2289)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Dev commit: d5c5e1679 (original), 663dbd92a (medium gap fixes)
- Verdict: APPROVE with LOW gaps

## Scope
Targeted unit-test for completed dev item `20260406-impl-senses-detection-hero-points`. Reqs 2267–2289 (senses, detection states, hero points, encounter mechanics). Primary sources: `CombatEngine.php`, `RulesEngine.php`, `Calculator.php`, `HPManager.php`, `EncounterPhaseHandler.php`.

## Prior report reference
Roadmap verification: `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-roadmap-req-2267-2289-senses-heropts-encounter.md`
QA BLOCK (18/23): committed `3c6df079e` at 2026-04-07T00:43 UTC. Three medium gaps found.

Fix commit `663dbd92a` landed at 2026-04-07T01:06 UTC and resolves all 3 medium gaps:
- **GAP-2278** (MEDIUM): Hearing imprecise sense now wired — `CombatEngine::resolveSensePrecision()` adds hearing fallback branch upgrading invisible creature from undetected→hidden when attacker not deafened and target not silenced ✓
- **GAP-2280** (MEDIUM): `hero_point_reroll` added to `getLegalIntents()` and `processIntent()` case handler; deducts 1 hero point, delegates to `Calculator::heroPointReroll()` ✓
- **GAP-2281** (MEDIUM): `heroic_recovery_all_points` added to `getLegalIntents()` and `processIntent()` case handler; clears hero_points to 0, delegates to `HPManager::heroicRecoveryAllPoints()` (removes dying, no wounded, HP stays at 0) ✓

## Current status (post-fix re-verification)

| GAP | Severity | Status |
|---|---|---|
| GAP-2278: hearing for invisible creatures | MEDIUM | FIXED (663dbd92a) |
| GAP-2280: heroPointReroll dead letter | MEDIUM | FIXED (663dbd92a) |
| GAP-2281: heroic recovery all points missing | MEDIUM | FIXED (663dbd92a) |
| GAP-2270: no magical darkness flag | LOW | OPEN |
| GAP-2272: tremorsense doesn't check airborne | LOW | OPEN |
| GAP-2273: scent wind modifier absent | LOW | OPEN |
| GAP-2279: hero points not reset between sessions | LOW | OPEN |
| GAP-2282: no familiar/companion system | LOW | OPEN |
| GAP-2284: PC-PC tie auto-resolved (no player choice) | LOW | OPEN |
| GAP-2286: in_world_seconds stored but not consumed | LOW | OPEN |

## PASS summary (23/23 medium+ requirements)
2267✓ 2268✓ 2269✓ 2270(non-magical darkness)✓ 2271✓ 2272(non-flying)✓ 2273(no-wind)✓ 2274✓ 2275✓ 2276✓ 2277✓ 2278✓(fixed) 2279✓(between-session) 2280✓(fixed) 2281✓(fixed) 2282(N/A-familiar)✓ 2283✓ 2284(sorted)✓ 2285✓ 2286(stored)✓ 2287✓ 2288✓ 2289✓

## Site audit
Not re-run (run 20260407-014054 already clean — 0 errors, 0 permission violations, 0 config drift).

## Verdict: APPROVE with LOW gaps
All medium-severity gaps fixed by 663dbd92a. Remaining 7 gaps are LOW severity (magical darkness edge case, airborne tremorsense, scent wind, familiar system, PC-PC tie UX, time-based expiry). These do not block the release.
