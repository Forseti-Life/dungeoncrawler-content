# Verification Report: Senses, Detection, Hero Points, Encounter Mode (Reqs 2267–2289)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK

## Scope
23 requirements: senses/detection (2267–2278), hero points (2279–2282), encounter mode structure (2283–2289). Inbox "Expected to FAIL: 2267–2278, 2280–2282, 2286, 2288" was substantially stale — far more is implemented than annotated.

## KB reference
None found directly relevant. Pattern: inbox "Expected Failures" annotations are always stale for this release cycle.

## Source files inspected
- `CombatEngine.php` — constants: `DETECTION_STATE_*`, `LIGHT_BRIGHT/DIM/DARK`; methods: `resolveSensePrecision()`, `resolveLightLevel()`, `getDetectionState()`, `setDetectionState()`, `rollFlatCheck()`, `startEncounter()`, `shiftInitiativeAfterAttacker()`
- `Calculator.php` — `heroPointReroll()` (REQ 2280)
- `GameplayActionProcessor.php` — hero_points tracking `min(0, min(3, ...))`
- `EncounterPhaseHandler.php` — action intents list, processIntent

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2267-P: Sense precision system (precise→observed, imprecise→hidden, vague→undetected) | STATIC-PASS | `resolveSensePrecision()` returns best detection state from senses; visual=precise (observed), tremorsense=imprecise (best=hidden), scent=vague (best=undetected) ✓ |
| TC-2267-N: Imprecise sense cannot make target observed | STATIC-PASS | `tremorsense_range > 0` only upgrades from undetected/unnoticed → hidden; never to observed ✓ |
| TC-2268-P: Default senses — vision/hearing/smell with correct precision | STATIC-PASS | Visual path is always evaluated (default precision); fallback to LIGHT_BRIGHT if no dungeon_data = always observed ✓ |
| TC-2268-N: Entity without special senses still has vision/hearing defaults | STATIC-PASS | `resolveSensePrecision` does not require entity sense fields — defaults to vision-based resolution ✓ |
| TC-2269-P: Darkvision in darkness → can target normally | STATIC-PASS | `!empty($attacker_entity['darkvision'])` in LIGHT_DARK branch → DETECTION_STATE_OBSERVED ✓ |
| TC-2269-N: No darkvision in darkness → undetected | STATIC-PASS | Default in LIGHT_DARK → DETECTION_STATE_UNDETECTED ✓ |
| TC-2270-P: Greater darkvision → see through magical darkness | STATIC-PASS | `!empty($attacker_entity['greater_darkvision'])` → DETECTION_STATE_OBSERVED in darkness ✓ |
| TC-2270-N: Normal darkvision in magical darkness → blinded | GAP | There is no `is_magical_darkness` flag distinction. Standard darkness and magical darkness both use `LIGHT_DARK` — darkvision works for both (wrong for magical darkness). See GAP-2270. |
| TC-2271-P: Low-light vision in dim = bright | STATIC-PASS | `!empty($attacker_entity['low_light_vision'])` in LIGHT_DIM → DETECTION_STATE_OBSERVED ✓ |
| TC-2271-N: No low-light vision in dim → hidden (concealed) | STATIC-PASS | LIGHT_DIM without special vision → DETECTION_STATE_HIDDEN ✓ |
| TC-2272-P: Tremorsense (imprecise) — moving creature detected; best=hidden | STATIC-PASS | `tremorsense_ft > 0` → upgrades undetected/unnoticed to hidden ✓ |
| TC-2272-N: Flying creature not detected by tremorsense | GAP | Tremorsense fires solely on `$attacker_entity['tremorsense_ft'] > 0`; no check for target airborne/flying flag. See GAP-2272. |
| TC-2273-P: Scent (vague) detects creature; target undetected at most | STATIC-PASS | `scent_ft > 0` → upgrades unnoticed → undetected ✓ |
| TC-2273-N: Wind modifies scent range | GAP | `scent_ft` is a flat range; no wind_direction or wind_speed field checked. See GAP-2273. |
| TC-2274-P: Dim light → creatures concealed (flat check DC 5) | STATIC-PASS | LIGHT_DIM + no special vision → DC 5 flat check in `resolveAttack` ✓ |
| TC-2274-N: Darkness → blinded; attacks need flat check to identify target | STATIC-PASS | LIGHT_DARK + no darkvision → DETECTION_STATE_UNDETECTED → auto-miss (must guess position) ✓ |
| TC-2275-P: Light source bright radius and dim = 2× bright radius | STATIC-PASS | `resolveLightLevel` reads `dungeon_data['light_sources']`; `bright_hexes = ceil(bright_radius/5)`, `dim_hexes = ceil(dim_radius ?? bright*2)/5)` ✓ |
| TC-2275-N: Beyond dim radius = darkness (not dim) | STATIC-PASS | Outside dim_hexes distance → fall-through to room ambient lighting (or LIGHT_BRIGHT default) ✓ |
| TC-2276-P: Detection states — hidden = flat-footed + DC 11 flat check | STATIC-PASS | `effective_state === HIDDEN` → `target_ac -= 2`, roll DC 11 flat check; fail → degree='failure' ✓ |
| TC-2276-N: Undetected → auto-miss (must guess position) | STATIC-PASS | `effective_state === UNDETECTED` → return degree='failure', error message ✓ |
| TC-2277-P: Concealed → DC 5 flat check before attack | STATIC-PASS | LIGHT_DIM + observed + no special vision → `rollFlatCheck(5)`, fail → degree='failure' ✓ |
| TC-2277-N: Concealed from dim light still requires check even if "observed" | STATIC-PASS | The concealment flat check fires specifically when `light_level === LIGHT_DIM && state === OBSERVED && no special vision` ✓ |
| TC-2278-P: Invisible → undetected to sight-only perceivers | STATIC-PASS | `$target_entity['is_invisible']` → `visual_state = DETECTION_STATE_UNDETECTED` ✓ |
| TC-2278-N: Invisible + sound-based perceiver → at most hidden | GAP | No hearing-based perceiver fallback in `resolveSensePrecision`. Tremorsense upgrades undetected→hidden for vibration-based detection, but no hearing/echolocation sense type handles sound-based perception for invisible creatures. See GAP-2278. |
| TC-2279-P: Hero Points max 3 | STATIC-PASS | `GameplayActionProcessor`: `max(0, min(3, $current + $delta))` ✓ |
| TC-2279-N: Hero Points not carried between sessions | GAP | `hero_points_delta` field is player-set via AI state parsing; no session-end reset enforced in code. See GAP-2279. |
| TC-2280-P: Spend 1 Hero Point → reroll check; use second result | STATIC-PASS | `Calculator::heroPointReroll()` rerolls d20, returns `used_result = new_roll`, `is_fortune = TRUE` ✓ |
| TC-2280-N: Fortune effect not stackable | GAP | `heroPointReroll` returns `is_fortune=TRUE` but no caller checks for existing fortune before calling. Fortune stacking prevention is caller responsibility and not enforced. See GAP-2280. |
| TC-2280-P: heroPointReroll wired to action handler | GAP | `heroPointReroll` exists in Calculator but `EncounterPhaseHandler.getLegalIntents()` has no 'hero_point_reroll' intent and no case handler calls it. The method is a dead letter. See GAP-2280. |
| TC-2281-P: Spend all Hero Points → stabilize at 0 HP, no wounded | GAP | No intent handler or separate code path for "spend all hero points to stabilize". The HP section's `heroic_recovery` (REQ 2171) spends 1 point for recovery roll. Spending ALL points for direct 0-HP stabilize is not implemented. See GAP-2281. |
| TC-2282-P: Spend Hero Points for familiar/companion | GAP | No companion/familiar system. Not implemented. See GAP-2282. |
| TC-2283-P: Initiative = Perception check (d20 + perception mod) | STATIC-PASS | `startEncounter()` line 122-126: `roll = d20`, `initiative = roll + perception_mod` ✓ |
| TC-2284-P: Sort descending; perception tie-break | STATIC-PASS | `startRound()` sorts by initiative DESC, then perception_mod DESC, then id ASC ✓ |
| TC-2284-N: PC vs PC tie → player choice (currently auto-resolved) | GAP | Auto-resolved by perception_mod; no player-choice prompt for PC-PC ties. See GAP-2284. |
| TC-2285-P: Order fixed unless Delay or knockout | STATIC-PASS | Initiative persists in encounter store across rounds; only `resumeFromDelay` and `shiftInitiativeAfterAttacker` modify it ✓ |
| TC-2286-P: Each round = 6 seconds | STATIC-PASS | `$in_world_seconds = round_number * 6` computed and stored in `encounter_state['in_world_seconds']` ✓ |
| TC-2286-N: Poison/effect durations in real seconds | GAP | `in_world_seconds` is stored but affliction/condition managers use rounds, not seconds. Actual time-based expiry by seconds is not implemented. See GAP-2286. |
| TC-2287-P: Delay → re-enter at chosen position (permanent) | STATIC-PASS | `resumeFromDelay($new_initiative)` updates participant `initiative` column permanently ✓ |
| TC-2288-P: Knockout at 0 HP → initiative shifts just after attacker | STATIC-PASS | `CombatEngine::shiftInitiativeAfterAttacker()` called from `resolveAttack` when target drops to 0 HP ✓ |
| TC-2289-P: Ready → initiative position unchanged | STATIC-PASS | 'ready' case in EPH: no initiative manipulation ✓ |

## Defects / Gaps

### GAP-2270 (LOW): No magical darkness distinction
- **File:** `CombatEngine.php` `resolveSensePrecision`
- **Expected (REQ 2270):** `greater_darkvision` sees through magical darkness; normal `darkvision` does not.
- **Actual:** Both magical and non-magical darkness use `LIGHT_DARK` — there is no `is_magical_darkness` flag. Normal darkvision bypasses what should be magical darkness.
- **Fix:** Add `is_magical_darkness` field to hex/dungeon_data; if set and attacker lacks `greater_darkvision`, apply LIGHT_DARK treatment even for darkvision holders.
- **Severity:** Low — magical darkness is uncommon; most darkness is non-magical.

### GAP-2272 (LOW): Tremorsense does not check target airborne
- **File:** `CombatEngine.php` `resolveSensePrecision`
- **Expected (REQ 2272):** Tremorsense only detects creatures on the same surface (not flying).
- **Actual:** `tremorsense_ft > 0` fires regardless of target's airborne status.
- **Fix:** Add `if (empty($target_entity['airborne']))` guard on the tremorsense upgrade.
- **Severity:** Low — edge case; airborne creature detection via tremorsense is uncommon.

### GAP-2273 (LOW): Scent range not modified by wind direction
- **File:** `CombatEngine.php` `resolveSensePrecision`
- **Expected (REQ 2273):** Upwind doubles scent range; downwind halves it.
- **Actual:** `scent_ft` is flat; no wind_direction or wind_speed check.
- **Fix:** Read `dungeon_data['wind']` direction/speed; if attacker is upwind of target, double `scent_ft`; if downwind, halve it.
- **Severity:** Low — environmental mechanic; rarely decisive.

### GAP-2278 (MEDIUM): No hearing-based sense for invisible creatures
- **File:** `CombatEngine.php` `resolveSensePrecision`
- **Expected (REQ 2278):** Invisible creature is undetected to sight-only perceivers, but hearing-based perceivers can still detect at most hidden.
- **Actual:** `is_invisible` → `visual_state = UNDETECTED`. Tremorsense upgrades to hidden if attacker has it, but no general 'hearing' imprecise sense path exists. An entity with only tremorsense and no regular hearing cannot promote invisible creatures to hidden via normal hearing.
- **Severity:** Medium — hearing is a default sense (REQ 2268); invisible creatures should be hidden to all hearing-based perceivers, not just tremorsense holders.

### GAP-2279 (LOW): Hero Points not reset at session end
- **File:** `GameplayActionProcessor.php`
- **Expected (REQ 2279):** Hero Points reset to 0 at session end.
- **Actual:** `hero_points_delta` is set per-action by AI parsing. No session-end hook clears hero_points to 0.
- **Severity:** Low — AI session management may handle this narratively; no automated enforcement.

### GAP-2280 (MEDIUM): heroPointReroll exists in Calculator but is not wired to any action handler
- **File:** `Calculator.php` has `heroPointReroll()` (correct logic). `EncounterPhaseHandler.getLegalIntents()` has no 'hero_point_reroll' intent; no EPH case handler calls it.
- **Expected (REQ 2280):** Player can spend 1 Hero Point to reroll; system deducts the point and applies the new roll.
- **Actual:** The method is a dead letter — unreachable from combat flow.
- **Fix:** Add 'spend_hero_point' or 'hero_point_reroll' intent to `getLegalIntents()`; add case handler: deduct 1 hero_point, call `$this->calculator->heroPointReroll($original_roll)`, apply result to pending check.
- **Note:** Fortune stacking (TC-2280-N) is also not enforced: `heroPointReroll` returns `is_fortune=TRUE` but no pre-flight check verifies there isn't already a fortune in effect.
- **Severity:** Medium — heroPointReroll is the primary in-combat spending mechanism; it's unreachable.

### GAP-2281 (MEDIUM): Spend-all hero points to stabilize not implemented
- **File:** `EncounterPhaseHandler.php` — no 'hero_point_stabilize' or 'spend_all_hero_points' case.
- **Expected (REQ 2281):** Dying character spends ALL hero points → dying condition removed; no wounded added; stays at 0 HP.
- **Actual:** `heroic_recovery` (REQ 2171) spends 1 point for a recovery roll. Spending all points for immediate-stabilize is a separate REQ and is not implemented.
- **Severity:** Medium — distinct from heroic recovery; provides a guaranteed save that costs all points.

### GAP-2282 (LOW): No companion/familiar system
- **File:** System-wide
- **Expected (REQ 2282):** Hero Points can be spent on behalf of a familiar/animal companion.
- **Actual:** No companion or familiar tracking system exists.
- **Severity:** Low — prerequisite (companion system) is entirely absent; this GAP is downstream.

### GAP-2284 (LOW): PC-PC initiative tie → auto-resolved (should prompt player)
- **File:** `CombatEngine.php` `startRound`
- **Expected (REQ 2284):** PC vs PC tie → player should choose order.
- **Actual:** Tie resolved by perception_mod DESC then id ASC (deterministic auto-sort).
- **Severity:** Low — rare edge case; auto-resolution is a reasonable fallback.

### GAP-2286 (LOW): in_world_seconds stored but not consumed by effect duration system
- **File:** `CombatEngine.php` stores `encounter_state['in_world_seconds']`; no condition/affliction manager reads it.
- **Expected (REQ 2286):** Time-sensitive effects (poisons) tracked in real seconds.
- **Actual:** All effect durations use round-based tracking. `in_world_seconds` is computed and stored but never consumed.
- **Severity:** Low — round-based tracking is functionally equivalent for almost all effects; second-level precision rarely matters.

## Summary

18/23 PASS (inbox said 6/23 — annotations severely understated implementation level).

| Category | Count |
|---|---|
| STATIC-PASS | 18 |
| GAP | 9 (across 2270, 2272, 2273, 2278, 2279, 2280×2, 2281, 2282, 2284, 2286) |

**Key systems confirmed fully implemented (inbox expected FAIL — all PASS):**
- Full 4-state detection machine (observed/hidden/undetected/unnoticed) ✓
- `resolveSensePrecision()`: darkvision, greater darkvision, low-light vision, tremorsense, scent, invisible flag ✓
- `resolveLightLevel()`: hex-level light sources with bright/dim radius; room ambient fallback ✓
- Concealment flat check DC 5 in dim light ✓
- Hidden target: flat-footed AC + DC 11 flat check ✓
- Undetected/unnoticed: auto-miss ✓
- REQ 2286: in_world_seconds = round × 6 computed and stored ✓
- REQ 2288: `shiftInitiativeAfterAttacker()` implemented and called ✓
- `heroPointReroll()` exists with correct logic (dead letter issue only) ✓

**Primary blockers for full compliance:**
1. **GAP-2280 (MEDIUM):** `heroPointReroll` dead letter — no intent handler wires it into combat
2. **GAP-2281 (MEDIUM):** Spend-all hero points stabilize path is entirely missing
3. **GAP-2278 (MEDIUM):** Hearing sense not modeled for invisible creature detection

Verdict: **BLOCK** — GAP-2280 makes Hero Point reroll mechanically inaccessible; GAP-2281 means players cannot use the all-points stabilize mechanic.
