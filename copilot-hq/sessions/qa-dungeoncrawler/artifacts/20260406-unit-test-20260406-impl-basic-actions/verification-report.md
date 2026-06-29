# Verification Report: Unit Test — 20260406-impl-basic-actions (Reqs 2190–2218)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Dev commit: b2fc76afd
- Verdict: BLOCK

## Scope
Targeted unit-test for completed dev item `20260406-impl-basic-actions`. Reqs 2190–2218 (PF2e basic actions). Primary source: `EncounterPhaseHandler.php`.

## Prior report reference
Full detailed test evidence in: `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-roadmap-req-2190-2218-basic-actions.md` (QA BLOCK committed `f7606c76d`, 2026-04-06 23:40 UTC). No follow-up fix commits have landed since that report.

## Current defect status (verified 2026-04-07)

All 4 medium defects from the prior BLOCK remain unpatched in source:

### DEF-2194 (MEDIUM) — Delay does not fire immediate start/end-of-turn effects
- **File:** `EncounterPhaseHandler.php`, `case 'delay'` (line ~468)
- **Evidence:** `case 'delay':` block sets `delayed=TRUE`, stores remaining actions, zeroes `actions_remaining`. No call to `processDying()` or `processEndOfTurnEffects()`.
- **Still open:** confirmed by direct source inspection.

### DEF-2218 (MEDIUM) — Cover not cleared on attack or move
- **File:** `EncounterPhaseHandler.php`, `case 'take_cover'` (line 753)
- **Evidence:** `cover_active` set at line 753. `grep cover_active` returns only 2 hits — both inside the `take_cover` case. No clearing in `strike`, `stride`, `step`, `crawl`, or `leap` cases.
- **Still open:** confirmed by direct source inspection.

### GAP-2204 (MEDIUM) — Readied attack MAP not applied when reaction fires
- **File:** `EncounterPhaseHandler.php`, `case 'ready'` (stores) / `case 'reaction'` (fires)
- **Evidence:** `map_at_ready` stored in `game_state['turn']['ready']` (line 539) but no read of that value in the reaction execution path.
- **Still open:** confirmed by direct source inspection.

### GAP-2212 (MEDIUM) — Sense Motive retry cooldown tracked but not enforced
- **File:** `EncounterPhaseHandler.php`, `case 'sense_motive'` (line 717)
- **Evidence:** Round stored: `$game_state['sense_motive'][$actor_id][$target_id] = $game_state['round'] ?? 0` (line 732). No pre-check before the roll; repeat attempt on same target same round proceeds.
- **Still open:** confirmed by direct source inspection.

## Low-severity gaps (still open, no change)
- GAP-2201: Leap minimum speed (15 ft) not enforced
- GAP-2209: Seek object path not implemented
- DEF-2195: Delayed actions not zeroed after full round
- GAP-2215: Step via non-land Speed not blocked

## Site audit (2026-04-07T01:40:54Z)
Run: `ALLOW_PROD_QA=1 scripts/site-audit-run.sh dungeoncrawler`
- Missing assets (404): 0
- Permission expectation violations: 0
- Other failures (4xx/5xx): 0
- Config drift: none
- Evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260407-014054/`

## PASS summary (21/29 unchanged)
2190✓ 2191✓ 2192✓ 2193✓ 2196✓ 2197✓ 2198✓ 2199✓ 2200✓ 2202✓ 2203✓ 2205✓ 2206✓ 2207✓ 2208✓ 2210✓ 2211✓ 2213✓ 2214✓ 2216✓ 2217✓

## Verdict: BLOCK
Four medium-severity defects (DEF-2194, DEF-2218, GAP-2204, GAP-2212) remain open. Dev must apply fixes before re-verification.

## Fix paths for dev
1. **DEF-2194**: In `case 'delay':` after storing delayed state, call `$this->processDying($actor_id, $encounter_id)` if participant is dying, and call `$this->processEndOfTurnEffects($participant, $game_state)` before ending the turn.
2. **DEF-2218**: Add `$game_state['entities'][$actor_id]['cover_active'] = FALSE;` at the top of `case 'strike':`, `case 'stride':`, `case 'step':`, `case 'crawl':`, and `case 'leap':`.
3. **GAP-2204**: In the `reaction` execution path, read `$map_at_ready = $game_state['turn']['ready']['map_at_ready'] ?? 0` and pass it to the attack roll instead of current `attacks_this_turn`.
4. **GAP-2212**: Before the roll in `case 'sense_motive':`, add: `if (isset($game_state['sense_motive'][$actor_id][$target_id]) && $game_state['sense_motive'][$actor_id][$target_id] === ($game_state['round'] ?? 0)) { return ['error' => 'Cannot retry Sense Motive on the same target this round.']; }`.
