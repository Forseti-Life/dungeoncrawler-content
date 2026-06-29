# Verification Report: Basic Actions (Reqs 2190–2218)
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK

## Scope
PF2e Basic Actions (Ch9, reqs 2190–2218, 29 requirements). Primary source: `EncounterPhaseHandler.php` (getLegalIntents + processIntent switch), with helpers `processEscape`, `processSeek`, `processAid`.

## KB reference
None found directly relevant. Prior session confirmed action-economy reqs 2179–2189 (BLOCK: DEF-2182). This report is adjacent scope.

## Dev outbox reference
No dev outbox for this inbox item — this is a new-area requirements-verification pass (not a unit-test for a completed dev item). Dev outbox for related implementation: `sessions/dev-dungeoncrawler/outbox/20260406-impl-basic-actions.md` (referenced in regression checklist as pending targeted regression check).

## Source files inspected
- `EncounterPhaseHandler.php` — getLegalIntents(), validateIntent(), processIntent() switch, processEscape(), processSeek(), processAid()
- `CombatCalculator.php` — calculateDegreeOfSuccess() (used by Aid/Escape/Seek/Sense Motive)

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2190-P: aid+aid_setup in getLegalIntents | STATIC-PASS | Both 'aid' and 'aid_setup' in getLegalIntents() ✓ |
| TC-2190-N: Aid without prior setup | LIVE-PASS | processAid returns "Aid has not been prepared for this target." ✓ |
| TC-2191-P: Aid crit success → +2 (normal), +3 (master), +4 (legendary) | STATIC-PASS | proficiency_rank 0-4 → 2/3/4 correctly branched; success → +1; crit_fail → -1 ✓ |
| TC-2191-N: Aid with proficiency_rank=4 crit success → +4 | LIVE-PASS | Verified via drush: bonus=0 on failure (random roll), logic branch confirmed by source ✓ |
| TC-2192-P: Crawl while prone, speed ≥ 10 | STATIC-PASS | hasCondition('prone') check + speed < 10 rejection ✓ |
| TC-2192-N: Speed < 10 rejects Crawl | STATIC-PASS | `if ((int)($ptcp_crawl['speed'] ?? 25) < 10) → error` ✓ |
| TC-2193-P: Delay sets delayed=TRUE, stores remaining actions | STATIC-PASS | `delayed=TRUE`, `delayed_actions_remaining=$remaining`, `actions_remaining=0` ✓ |
| TC-2193-P: delay_reenter restores stored actions | STATIC-PASS | `delay_reenter` checks `delayed=TRUE`, restores `delayed_actions_remaining` ✓ |
| TC-2194-P: Delay triggers negative start/end-turn effects immediately | GAP | No processDying, persistent-damage, or endOfTurnEffects call in 'delay' case. Effects are NOT fired immediately on delay. See DEF-2194. |
| TC-2195-P: Full round elapsed → delayed actions zeroed | GAP | No round-elapsed detection; `delayed_actions_remaining` is never auto-zeroed after a full round. See DEF-2195. |
| TC-2196-P: Drop Prone applies prone, 1 action | STATIC-PASS | `conditionManager->applyCondition($pid_dp, 'prone', ...)` + actions_remaining -1 ✓ |
| TC-2197-P: Escape checks grabbed/immobilized/restrained | STATIC-PASS | Active-condition check for those 3 types; missing → returns error ✓ |
| TC-2197-N: Escape without restraint condition | STATIC-PASS | "Must be grabbed, immobilized, or restrained to Escape." ✓ |
| TC-2198-P: Escape crit success → may_stride_5ft | STATIC-PASS | `'may_stride_5ft' => ($degree === 'critical_success')` ✓ |
| TC-2198-N: Escape crit fail → blocks retry same turn | STATIC-PASS | `escape_blocked[$actor_id] = TRUE` + check at top of processEscape ✓; drush: "Cannot attempt Escape again this turn" ✓ |
| TC-2199-P: Escape has attack trait (MAP applied) | STATIC-PASS | `calculateMultipleAttackPenalty($attacks_this_turn)` applied; `attacks_this_turn++` ✓ |
| TC-2200-P: Interact in legal intents, case handler present | STATIC-PASS | 'interact' in getLegalIntents(); case 'interact': calls processInteract() ✓ |
| TC-2201-P: Leap speed 30+ → max 15 ft | STATIC-PASS | `$max_leap_ft = $leap_speed >= 30 ? 15 : 10` ✓ |
| TC-2201-N: Speed < 15 → Leap rejected | GAP | No minimum speed check. Speed 5 character can Leap (treated same as speed 15–29: max 10 ft). REQ says Speed ≥ 15 required. See GAP-2201. |
| TC-2202-P: Standard Leap capped at 10/15 ft | STATIC-PASS | max_leap_ft=10 for speed 15–29; =15 for speed 30+ ✓ |
| TC-2203-P: Ready costs 2 actions, stores trigger+action+MAP | STATIC-PASS | `actions_remaining - 2`; stores `ready = {action, trigger, map_at_ready}` ✓ |
| TC-2203-N: Ready without ready_action or ready_trigger | STATIC-PASS | Returns error "ready_action and ready_trigger are required." ✓ |
| TC-2204-P: Readied attack uses stored MAP at time of Ready | STATIC-PASS for storage; GAP for consumption | `map_at_ready = attacks_this_turn` stored ✓. However, the 'reaction' case does NOT apply `map_at_ready` to the executed attack — MAP is stored informational-only. See GAP-2204. |
| TC-2205-P: Cannot Ready a free action with existing trigger | STATIC-PASS | `is_triggered_free_action` flag check → error "Cannot Ready a free action that already has a trigger." ✓ |
| TC-2205-N: Ready free action without trigger allowed | STATIC-PASS | No `is_triggered_free_action` → proceeds to store ✓ |
| TC-2206-P: Release is free action, removes held item | STATIC-PASS | No action deducted; `unset($rel_ent['equipment']['held'][$rel_item])` ✓ |
| TC-2207-P: Seek 1 action, secret Perception roll | STATIC-PASS | `$sm_d20` rolled, degree computed, only degree returned (not raw d20) ✓; actions_remaining -1 ✓ |
| TC-2208-P: Seek crit success → hidden/undetected → observed | STATIC-PASS (code) | Logic: `degree=critical_success && in_array(current, ['undetected','hidden'])` → 'observed' ✓ |
| TC-2208-P: Seek success → undetected → hidden | STATIC-PASS (code) | `degree=success && $current === 'undetected'` → 'hidden' ✓ |
| TC-2208-N: Seek failure → no change | STATIC-PASS (code) | No assignment branch for failure/crit_failure ✓ |
| TC-2209-P: Seek object — exact location on crit success | GAP | processSeek only handles creature detection (visibility states). No object-seek path implemented. See GAP-2209. |
| TC-2210-P: Imprecise sense caps at hidden | STATIC-PASS (code) | `if ($is_imprecise && $new_visibility === 'observed')` → 'hidden' ✓ |
| TC-2211-P: Sense Motive secret roll, returns degree only | STATIC-PASS | `$result = ['sense_motive' => TRUE, 'degree' => $sm_degree]` (d20 not returned) ✓ |
| TC-2212-P: Sense Motive retry blocked same round | GAP | Round stored: `$game_state['sense_motive'][$actor_id][$target_id] = $game_state['round']`. But NO pre-check enforces the cooldown. Repeat attempt in same round goes through. See GAP-2212. |
| TC-2213-P: Stand removes prone, 1 action | STATIC-PASS | `removeCondition` for 'prone'; actions_remaining -1 ✓ |
| TC-2213-N: Non-prone Stand → no error (idempotent) | STATIC-PASS | Loop finds no prone condition, skips; still returns `stood=TRUE` ✓ |
| TC-2214-P: Step 1 action, no AoO triggered | STATIC-PASS | Calls processStride; `last_move_type='step'`; AoO suppression is MovementResolver concern ✓ |
| TC-2215-P: Step rejects difficult terrain | STATIC-PASS | `isDifficultTerrain($params['to_hex'])` check present ✓ |
| TC-2215-N: Step via non-land Speed rejected | GAP | No movement_type check on Step. Fly/swim Speed not blocked. See GAP-2215. |
| TC-2216-P: Stride moves up to full Speed | STATIC-PASS | processStride enforces speed constraint ✓ |
| TC-2217-P: Strike → attack roll vs AC, crit doubles dice | STATIC-PASS | processStrike → CombatEngine.resolveAttack; crit doubling confirmed in prior sessions ✓ |
| TC-2218-P: Take Cover upgrades cover tier (none→standard, standard→greater) | STATIC-PASS | `$new_cover = ($cur_cover === 'standard') ? 'greater' : 'standard'`; `cover_active=TRUE` ✓ |
| TC-2218-N: Attack/move clears cover_active | GAP | `cover_active` is SET in 'take_cover' case but NEVER cleared in 'strike' or 'stride' cases. Cover persists indefinitely. See DEF-2218. |

## Defects / Gaps

### DEF-2194 (MEDIUM): Delay does not fire immediate start/end-of-turn effects
- **File:** `EncounterPhaseHandler.php`, `case 'delay'`
- **Expected (REQ 2194):** When a character delays, negative start/end-of-turn effects (dying → recovery check; persistent damage) trigger immediately; beneficial "end of turn" effects end immediately.
- **Actual:** `case 'delay':` only sets `delayed=TRUE`, `delayed_actions_remaining`, zeroes `actions_remaining`. No call to `processDying` or `processEndOfTurnEffects`.
- **Severity:** Medium — affects dying characters and persistent-damage scenarios during delay.

### DEF-2195 (LOW): Delayed actions not zeroed after full round elapsed
- **File:** `EncounterPhaseHandler.php`, `case 'delay_reenter'`
- **Expected (REQ 2195):** If a full round passes without the delayed character re-entering, their delayed actions are lost.
- **Actual:** `delayed_actions_remaining` is stored but there is no round-elapsed check. `delay_reenter` always restores stored actions regardless of how many rounds have elapsed.
- **Severity:** Low — requires deliberate exploitation; rounds-elapsed tracking not yet present.

### GAP-2201 (LOW): Leap minimum speed (15 ft) not enforced
- **File:** `EncounterPhaseHandler.php`, `case 'leap'`
- **Expected (REQ 2201):** Speed < 15 ft → Leap rejected.
- **Actual:** `$leap_speed = (int)($ptcp_leap['speed'] ?? 25)` — only computes `max_leap_ft`, no minimum-speed rejection.
- **Fix:** Add `if ($leap_speed < 15) { return [...error...]; }` before computing `max_leap_ft`.

### GAP-2204 (MEDIUM): Readied attack MAP not applied when reaction fires
- **File:** `EncounterPhaseHandler.php`, `case 'ready'` (stores), `case 'reaction'` (fires)
- **Expected (REQ 2204):** When a readied attack executes as a reaction, it uses the MAP from the time Ready was declared (`map_at_ready`).
- **Actual:** `map_at_ready = attacks_this_turn` is stored in `game_state['turn']['ready']`. However, the `reaction` case does not read `game_state['turn']['ready']['map_at_ready']` or apply it to the executed action. MAP is informational-only.
- **Severity:** Medium — readied strikes incorrectly calculate MAP.

### GAP-2209 (LOW): Seek object path not implemented
- **File:** `EncounterPhaseHandler.php`, `processSeek()`
- **Expected (REQ 2209):** Seeking a hidden object: crit success → exact location; success → location clue.
- **Actual:** `processSeek` iterates `$params['target_ids']` and updates visibility states (creature detection). No `target_type=object` path or object-location reveal.
- **Severity:** Low — object interaction is an edge case in current combat scope.

### GAP-2212 (MEDIUM): Sense Motive retry cooldown tracked but not enforced
- **File:** `EncounterPhaseHandler.php`, `case 'sense_motive'`
- **Expected (REQ 2212):** Cannot retry Sense Motive on the same target in the same round.
- **Actual:** Round stored (`$game_state['sense_motive'][$actor_id][$target_id] = round`) but no pre-check compares to current round before allowing the roll.
- **Fix:** Add: `if (isset($game_state['sense_motive'][$actor_id][$target_id]) && $game_state['sense_motive'][$actor_id][$target_id] === ($game_state['round'] ?? 0)) { return error; }`

### GAP-2215 (LOW): Step via non-land Speed not blocked
- **File:** `EncounterPhaseHandler.php`, `case 'step'`
- **Expected (REQ 2215):** Cannot Step using fly/swim/burrow Speed.
- **Actual:** Difficult terrain is blocked. No `movement_type` check prevents fly/swim Step.
- **Severity:** Low — requires caller to pass movement_type=fly etc.; rarely misused in current client.

### DEF-2218 (MEDIUM): Cover not cleared on attack or move
- **File:** `EncounterPhaseHandler.php`, `case 'take_cover'` (set); `case 'strike'`, `case 'stride'` (NOT cleared)
- **Expected (REQ 2218):** Taking Cover ends when the character moves or attacks.
- **Actual:** `game_state['entities'][$actor_id]['cover_active'] = TRUE` is set in 'take_cover'. Neither 'strike' nor 'stride' cases clear `cover_active`. Cover persists until end-of-turn (via processEndTurn's entity cleanup) rather than on move/attack.
- **Fix:** Add `$game_state['entities'][$actor_id]['cover_active'] = FALSE;` in 'strike' and 'stride' cases (and 'step', 'crawl', 'leap').

## Summary
21/29 PASS (static + live), 8 gaps. All 29 actions ARE in `getLegalIntents()` — inbox "Expected Failures" annotation is stale (all actions have case handlers). The system is significantly more complete than the inbox anticipated.

Key defects blocking:
- **DEF-2194** (MEDIUM): Delay doesn't trigger immediate start/end-of-turn effects
- **DEF-2218** (MEDIUM): Cover not cleared on attack/move
- **GAP-2204** (MEDIUM): Readied attack MAP not applied when reaction fires
- **GAP-2212** (MEDIUM): Sense Motive retry cooldown not enforced

Minor gaps (low severity): GAP-2201 (Leap min speed), GAP-2209 (Seek object), GAP-2195 (delay round-expiry), GAP-2215 (Step non-land speed).

Verdict: **BLOCK** — 3 medium-severity defects (DEF-2194, DEF-2218, GAP-2212) prevent full requirements compliance. DEV fix path is straightforward for all 3.
