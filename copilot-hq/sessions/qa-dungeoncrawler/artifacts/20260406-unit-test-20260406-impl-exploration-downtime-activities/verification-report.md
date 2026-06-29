# Verification Report: Unit Test — 20260406-impl-exploration-downtime-activities (Reqs 2290–2310)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Dev commit: 521d96119
- Verdict: BLOCK

## Scope
Targeted unit-test for completed dev item `20260406-impl-exploration-downtime-activities`. Reqs 2290–2310 covering Exploration Mode and Downtime Mode activities. Primary sources: `ExplorationPhaseHandler.php`, `DowntimePhaseHandler.php`.

## Prior report reference
Roadmap verification of the same code was performed at 2026-04-07T01:10:54 UTC (commit `0568eaeca`): BLOCK 8/21 PASS. Dev commit `521d96119` predates that QA pass (2026-04-06T22:03). The roadmap QA effectively verified this dev item. No fix commits have landed since. This report confirms those findings remain current and adds the unit-test artifact.

## KB reference
None found directly relevant in `knowledgebase/`.

## Source files inspected
- `ExplorationPhaseHandler.php` — getLegalIntents(), processMove(), set_activity case, daily_prepare case, calculateTravelSpeed()
- `DowntimePhaseHandler.php` — getLegalIntents(), processLongRest(), processDowntimeRest(), processRetrain(), processAdvanceDay()

## Test Results

| TC | REQ | Verdict | Notes |
|---|---|---|---|
| TC-2290: calculateTravelSpeed() called by processMove | 2290 | FAIL | `calculateTravelSpeed()` is a public method at line 790. `processMove()` (line 506) makes no call to it — moves all entities with no speed or terrain enforcement. GAP-2290. |
| TC-2291: greater_difficult terrain multiplier = 0.333 | 2291 | FAIL | `'greater_difficult' => 0.25` at line 794. Should be 0.333 (⅓). DEF-2291. |
| TC-2292: avoid_notice halves speed | 2292 | FAIL | `set_activity` stores activity in `character_activities[$actor_id]` (line 329) but processMove() never reads `character_activities`. Speed halving and initiative substitution not applied. GAP-2292. |
| TC-2293: detect_magic in legal activities | 2293 | PASS | `detect_magic` in the validated activity list at line 317 ✓ |
| TC-2294: follow_expert in legal activities | 2294 | PASS | `follow_expert` in the validated activity list ✓ |
| TC-2295: repeat_spell in legal activities | 2295 | PASS | `repeat_spell` in the validated activity list ✓ |
| TC-2296: hustle doubles speed | 2296 | PASS (partial) | `calculateTravelSpeed`: `if ($hustle) { $speed_multiplier = 2; }` ✓. But GAP-2296: Con mod fatigue timer (10 minutes hustle → fatigued) absent — `fatigue_warning` is a string only, no timer state tracked. |
| TC-2297: scout in legal activities | 2297 | PASS | `scout` in the validated activity list ✓ |
| TC-2298: processSearch() Perception vs search_dc | 2298 | PASS | `processSearch()` present; rolls Perception vs `search_dc`, returns hidden entity visibility reveals ✓ |
| TC-2299: investigate in legal activities | 2299 | PASS | `investigate` in the validated activity list ✓ |
| TC-2300: set_activity rejects unknown activities | 2300 | PASS | Unknown activity type returns: "Invalid exploration activity '$activity'. Legal activities are: ..." ✓ |
| TC-2301: long_rest HP = max(1, con_mod) × level | 2301 | PASS | `processLongRest` line 260: `$hp_per_rest = max(1, $con_mod) * $level` ✓ |
| TC-2302: armor fatigue on long_rest (medium/heavy) | 2302 | PASS | `hasArmorEquipped(['medium','heavy'])` check applies fatigued condition when sleeping in armor ✓ |
| TC-2303: hours_since_rest incremented; >16h → fatigued | 2303 | FAIL (LOW) | `hours_since_rest` reset to 0 on rest (line 300) but no code path increments it; no >16h fatigue trigger. GAP-2303. |
| TC-2304: daily_prepare restores focus points | 2304 | PASS (partial) | `processDailyPrepare()` restores `focus_points` and `daily_abilities`. No rest prerequisite check (GAP-2304 LOW). |
| TC-2305: daily_prepare 24h cooldown enforced | 2305 | FAIL (LOW) | `last_daily_prepare` stored (line 845) but no pre-check enforces 24h cooldown. GAP-2305. |
| TC-2306: downtime_rest restores max(1, con_mod)×2×level HP | 2306 | PASS | `processDowntimeRest` line 384: `max(1, $con_mod) * (2 * $level)` ✓ |
| TC-2307: retrain starts 7-day timer | 2307 | PASS | `processRetrain` sets `days_required=7` (standard), stores timer in `game_state['downtime']['retraining']` ✓ |
| TC-2308: prohibited types blocked | 2308 | PASS | `ancestry/heritage/background/class/ability_score` all in `$prohibited` array; blocked with error ✓ |
| TC-2309: druid_order/wizard_school/sorcerer_bloodline → 30 days | 2309 | PASS | `$major_choices` array; `$days_required = in_array($retrain_type, $major_choices) ? 30 : 7` ✓ |
| TC-2310: retraining lock blocks new retrain if active | 2310 | PASS | `processRetrain` checks `isset($game_state['downtime']['retraining'][$actor_id])` → returns error ✓ |

## Open Defects / Gaps

### GAP-2290 (MEDIUM) — calculateTravelSpeed() disconnected from processMove()
- `calculateTravelSpeed()` exists at line 790 with correct terrain multiplier logic
- `processMove()` at line 506 makes no call to it — distance is never checked against speed, terrain is ignored
- **Fix:** Call `calculateTravelSpeed($entity['stats']['speed'], $terrain_type, $current_activity)` in processMove and validate distance against `effective_speed`.

### DEF-2291 (LOW) — greater_difficult terrain multiplier wrong
- `'greater_difficult' => 0.25` (line 794) — should be `0.333` (⅓ movement)
- **Fix:** `'greater_difficult' => 1/3`

### GAP-2292 (MEDIUM) — avoid_notice/defend activity effects not applied
- `character_activities[$actor_id]` stores the current activity on `set_activity`
- `processMove()` never reads `character_activities` — no speed halving for avoid_notice/defend, no initiative substitution for avoid_notice
- **Fix:** Read `$game_state['exploration']['character_activities'][$actor_id]` in processMove and apply speed modifiers; apply initiative substitution in encounter start.

### GAP-2296 (LOW) — Hustle Con mod fatigue timer absent
- `calculateTravelSpeed` returns `fatigue_warning` string but no timer state
- PF2e rules: 10 minutes of hustle → fatigued condition
- **Fix:** Track `hustle_minutes_elapsed` in game_state; apply fatigued on threshold.

### GAP-2303 (LOW) — hours_since_rest never incremented
- Reset to 0 on rest but no action handler increments it; >16h fatigue check is dead code

### GAP-2304 (LOW) — daily_prepare missing rest prerequisite
- `processDailyPrepare` does not check if character rested recently

### GAP-2305 (LOW) — daily_prepare 24h cooldown not enforced
- `last_daily_prepare` stored but no pre-check before re-entry

## Summary
13/20 PASS. Downtime system (REQs 2306–2310) is fully correct. Key exploration gaps: calculateTravelSpeed() is a dead letter (never called from processMove), character_activities never read for speed/initiative effects. Low-severity gaps in daily_prepare and hustle fatigue.

## Site audit
Not re-run (run 20260407-014054 already clean — 0 errors, 0 permission violations, 0 config drift).

## Verdict: BLOCK
Two medium-severity gaps (GAP-2290, GAP-2292) prevent compliance with core exploration movement rules.
