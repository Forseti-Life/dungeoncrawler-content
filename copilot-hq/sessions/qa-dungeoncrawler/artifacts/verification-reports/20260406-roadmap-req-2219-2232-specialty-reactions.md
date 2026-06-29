# Verification Report: Specialty Basic Actions + Reactions in Encounters (Reqs 2219–2232)
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK

## Scope
PF2e Specialty Basic Actions and Reactions in Encounters (Ch9, reqs 2219–2232, 14 requirements). Primary source: `EncounterPhaseHandler.php`.

## KB reference
None found relevant. All 14 actions were expected NOT implemented by the inbox; all 14 are in fact implemented with case handlers (identical pattern to 2190–2218 where inbox annotations were stale).

## Dev outbox reference
No specific dev outbox for this inbox item. Related: `sessions/dev-dungeoncrawler/outbox/20260406-impl-specialty-actions-reactions.md` (referenced in regression checklist as pending targeted regression check).

## Source files inspected
- `EncounterPhaseHandler.php` — getLegalIntents(), processIntent() switch (cases: arrest_fall, avert_gaze, burrow, fly, grab_edge, mount, dismount, point_out, raise_shield, attack_of_opportunity, shield_block)
- `CombatEngine.php` — resolveAttack() skip_map handling (line 592–601), DB attacks_this_turn update (line 795), target AC computation (reads `$target['ac']` flat DB column only)
- `Calculator.php` — `calculateAC()` signature (defined with `$shield_raised` param; never called in attack resolution path)

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2219-P: Arrest a Fall in getLegalIntents | STATIC-PASS | 'arrest_fall' in getLegalIntents() ✓ |
| TC-2219-P: Requires fly Speed; Acrobatics DC 15; reaction consumed | STATIC-PASS | `if (empty($entity_af['fly_speed'])) → error`; Acrobatics roll vs DC 15; `reaction_available=FALSE` ✓ |
| TC-2219-N: No fly Speed → rejected | STATIC-PASS | fly_speed check enforced ✓ |
| TC-2219-P: Four-degree outcomes (crit success=no damage, fail=partial, crit fail=full/heavy) | STATIC-PASS | degree determines `$damage_af` via floor/ceil math; falls computed by `$feet_fallen` ✓ |
| TC-2220-P: Avert Gaze in getLegalIntents; sets avert_gaze_active | STATIC-PASS | 'avert_gaze' in getLegalIntents(); `entity_data_ag['avert_gaze_active'] = TRUE` stored; 1 action ✓ |
| TC-2220-N: Expires at start of next turn | STATIC-PASS | End-of-turn cleanup in processEndTurn: `$entity_fly['avert_gaze_active'] = FALSE` ✓ |
| TC-2220-N: +2 circumstance bonus applied to gaze-ability saves | GAP | `avert_gaze_active` flag is stored and cleaned up ✓, but NO service reads the flag to add +2 circumstance bonus to gaze-triggered save rolls. See GAP-2220. |
| TC-2221-P: Burrow in getLegalIntents; requires burrow_speed > 0 | STATIC-PASS | 'burrow' in getLegalIntents(); `if ($burrow_speed <= 0) → error` ✓ |
| TC-2221-P: Tags entity as underground; conditional tunnel | STATIC-PASS | `entity_data_b['underground'] = TRUE`; `creates_tunnel` check ✓ |
| TC-2221-N: No burrow_speed → rejected | STATIC-PASS | burrow_speed check enforced ✓ |
| TC-2222-P: Fly in getLegalIntents; requires fly_speed > 0 | STATIC-PASS | 'fly' in getLegalIntents(); `if ($fly_speed <= 0) → error` ✓ |
| TC-2222-P: Falls at end of turn if no Fly used | STATIC-PASS | End-of-turn: `if (airborne && !fly_used_this_turn) → applyFallDamage`; `fly_used_this_turn=FALSE` cleared ✓ |
| TC-2222-N: Upward movement costs double (difficult terrain) | STATIC-PASS | `upward_movement = TRUE` flag passed to processStride via `movement_cost_multiplier` ✓ |
| TC-2223-P: Fly 0 = hover; 1 action; stays airborne | STATIC-PASS | `if ($fly_distance === 0) → hovered=TRUE, airborne=TRUE, fly_used_this_turn=TRUE`; actions_remaining -1 ✓ |
| TC-2224-P: Grab an Edge in getLegalIntents; Reflex DC 15; clinging=TRUE on success | STATIC-PASS | 'grab_edge' in getLegalIntents(); Reflex roll vs DC 15; `entity_ge['clinging'] = TRUE`; reaction consumed ✓ |
| TC-2224-P: Reaction spent check | STATIC-PASS | `reaction_available === FALSE` guard at top ✓ |
| TC-2225-P: Mount in getLegalIntents; adjacency check | STATIC-PASS | 'mount' in getLegalIntents(); `if ($dist_m > 1) → error` ✓ |
| TC-2225-N: Size check (≥1 size larger) | GAP | No size comparison between actor and mount target. REQ says target must be ≥1 size larger. See GAP-2225. |
| TC-2225-N: Willing check | GAP | No `willing` flag check on mount target entity. See GAP-2225. |
| TC-2226-P: Point Out in getLegalIntents; undetected→hidden for allies | STATIC-PASS | 'point_out' in getLegalIntents(); iterates allies; `if (state === 'undetected' or 'unnoticed') → 'hidden'` ✓ |
| TC-2226-N: Cannot make hidden → observed via Point Out | STATIC-PASS | Only upgrades 'undetected'/'unnoticed' → 'hidden'; no upgrade from 'hidden' ✓ |
| TC-2227-P: Raise a Shield in getLegalIntents; finds held shield; sets shield_raised | STATIC-PASS | 'raise_shield' in getLegalIntents(); findHeldShield(); `shield_raised=TRUE`; `shield_raised_ac_bonus` stored ✓ |
| TC-2227-N: No shield in hand → rejected | STATIC-PASS | `if (!$shield_rs) → error "No shield in hand."` ✓ |
| TC-2227-N: Broken shield → rejected | STATIC-PASS | `if (!empty($shield_rs['broken'])) → error "Shield is broken."` ✓ |
| TC-2227-N: Shield AC bonus expires at start of next turn | STATIC-PASS | End-of-turn cleanup: `$entity_fly['shield_raised'] = FALSE` ✓ |
| TC-2227-N: Shield AC bonus applies to incoming attacks | GAP | `shield_raised_ac_bonus` stored in entity_ref ✓. BUT `CombatEngine::resolveAttack` reads `$target_ac = (int)($target['ac'] ?? 10)` — a flat DB column. It never reads entity_ref for `shield_raised_ac_bonus`. `Calculator::calculateAC()` has `$shield_raised` param but is never called in the attack resolution path. Raised shield provides zero mechanical AC benefit. See GAP-2227. |
| TC-2228-P: AoO in getLegalIntents; class feature check | STATIC-PASS | 'attack_of_opportunity' in getLegalIntents(); `if (!in_array('attack_of_opportunity', $class_features)) → error` ✓ |
| TC-2228-N: Non-fighter (no class feature) → rejected | STATIC-PASS | class_features check enforced ✓ |
| TC-2229-P: AoO crit + manipulate trigger → disrupt | STATIC-PASS | `if ($aoo_result['degree'] === 'critical_success' && $trigger_type === 'manipulate') → disrupted=TRUE` ✓ |
| TC-2229-N: AoO hit (non-crit) on manipulate → NOT disrupted | STATIC-PASS | Only crit_success triggers disruption ✓ |
| TC-2230-P: AoO does not apply MAP (skip_map) | STATIC-PASS | `weapon['skip_map']=TRUE` → CombatEngine uses 0 MAP penalty; DB attacks_this_turn not incremented ✓ |
| TC-2230-N: AoO should NOT change game_state attacks_this_turn | DEF-FAIL | Line 1180: `$game_state['turn']['attacks_this_turn'] = max(0, (existing ?? 1) - 1)` DECREMENTS the count instead of leaving it unchanged. processStrike does NOT increment game_state attacks_this_turn (it's DB-only via CombatEngine), so the -1 creates an off-by-one. See DEF-2230. |
| TC-2231-P: Shield Block in getLegalIntents; damage split via Hardness | STATIC-PASS | 'shield_block' in getLegalIntents(); `$reduced = incoming - hardness`; `$shield_takes = floor($reduced / 2)`; `$entity_takes = $reduced - $shield_takes` ✓ |
| TC-2231-P: Shield at 0 HP → broken flag | STATIC-PASS | `if ($shield_sb['hp'] <= 0) → broken=TRUE, shield_raised=FALSE` ✓ |
| TC-2231-N: Broken shield cannot be used for Shield Block | STATIC-PASS in raise_shield; PARTIAL in shield_block | raise_shield rejects broken shield ✓. shield_block only checks `shield_raised`; does not re-check `broken` flag at Shield Block time. Minor gap. |
| TC-2232-P: Shield must be raised to Shield Block | STATIC-PASS | `if (empty($entity_sb['shield_raised'])) → error "Shield must be raised to use Shield Block."` ✓ |
| TC-2232-N: Shield not raised → Shield Block rejected | STATIC-PASS | guard enforced ✓ |

## Defects / Gaps

### GAP-2220 (MEDIUM): Avert Gaze flag set but +2 circumstance bonus not consumed
- **File:** `EncounterPhaseHandler.php` sets `entity_data_ag['avert_gaze_active'] = TRUE` in entity_ref; no other service reads it.
- **Expected (REQ 2220):** While `avert_gaze_active` is TRUE, character gains +2 circumstance bonus to saves against gaze abilities until start of next turn.
- **Actual:** Flag lifecycle is correct (set on action, cleared at end-of-turn). Zero services read `avert_gaze_active` to apply the +2 bonus when a gaze-triggered save is resolved.
- **Fix:** In whichever service resolves gaze-ability saves, read `$entity_ref['avert_gaze_active']` and add +2 circumstance to the save total.
- **Severity:** Medium — blocks functional implementation of gaze-ability saves.

### GAP-2225 (LOW): Mount missing size and willing checks
- **File:** `EncounterPhaseHandler.php`, case 'mount'
- **Expected (REQ 2225):** Target must be willing AND ≥1 size category larger.
- **Actual:** Only adjacency (dist ≤ 1) is checked. No size comparison, no willing flag check.
- **Severity:** Low — only affects edge-case rejection; mechanics otherwise work.
- **Fix:** Load target entity_data; check size field comparison; check `is_willing` or `attitude` field.

### GAP-2227 (MEDIUM): Raised shield AC bonus not applied in attack resolution
- **File:** `EncounterPhaseHandler.php` stores `entity_data_rs['shield_raised_ac_bonus']` in entity_ref; `CombatEngine::resolveAttack` never reads it.
- **Expected (REQ 2227):** Shield raised grants +2 circumstance bonus to AC until start of next turn.
- **Actual:** `CombatEngine::resolveAttack` reads `$target_ac = (int)($target['ac'] ?? 10)` from the flat `combat_participants.ac` DB column. Loads `$target_entity_data = json_decode($target['entity_ref'])` but only for detection-state — never for shield AC. `Calculator::calculateAC()` has `$shield_raised` param but is never called in attack resolution.
- **Fix:** In `CombatEngine::resolveAttack`, after loading `$target_entity_data`, add:
  ```php
  if (!empty($target_entity_data['shield_raised'])) {
      $target_ac += (int) ($target_entity_data['shield_raised_ac_bonus'] ?? 2);
  }
  ```
- **Severity:** Medium — `raise_shield` is a core defensive action; without this fix it provides zero mechanical benefit to AC.

### DEF-2230 (MEDIUM): AoO decrements game_state attacks_this_turn instead of leaving it unchanged
- **File:** `EncounterPhaseHandler.php`, line 1180
- **Expected (REQ 2230):** AoO does not count toward MAP. `game_state['turn']['attacks_this_turn']` should be unchanged after AoO.
- **Actual:** `$game_state['turn']['attacks_this_turn'] = max(0, ($game_state['turn']['attacks_this_turn'] ?? 1) - 1);`
  - `processStrike` only updates the DB participant record (via CombatEngine). It does NOT touch `game_state['turn']['attacks_this_turn']`.
  - The -1 at line 1180 thus decrements an already-unchanged value → net result: attacks_this_turn = N-1 instead of N.
  - Example: fighter with 1 prior strike (attacks_this_turn=1) takes AoO → game_state becomes 0 → next strike has no MAP (wrong, should be MAP -5).
- **Fix:** Remove line 1180 entirely. Comment at line 1179 says "Do NOT decrement" but the code does exactly that.

### MINOR-2231-shield-block: Shield Block doesn't re-check broken flag at use time
- **File:** `EncounterPhaseHandler.php`, case 'shield_block'
- **Note:** `raise_shield` rejects broken shields. If a shield breaks mid-combat via a prior Shield Block on the same turn, a second Shield Block attempt would pass the `shield_raised` check but the shield is now broken. Low severity; edge case.

## Summary
10/14 PASS (2 additional medium gaps found in this re-verification pass; prior report counted 12/14).

**Inbox "Expected: NOT implemented" was incorrect** — all 14 specialty actions are implemented with full case handlers, same pattern as 2190–2218.

Newly-found gaps vs prior report:
- **GAP-2220** (MEDIUM): Avert Gaze flag correct but +2 circumstance bonus never consumed by save resolution
- **GAP-2227** (MEDIUM): `raise_shield` stores AC bonus in entity_ref but `CombatEngine::resolveAttack` reads flat `combat_participants.ac` column — raised shield provides zero AC benefit in combat

Existing defect:
- **DEF-2230** (MEDIUM): AoO case line 1180 decrements `attacks_this_turn` (comment says "Do NOT"; code does the opposite)

Low/minor:
- **GAP-2225** (LOW): Mount missing willing + size checks
- **MINOR-2231**: Shield Block doesn't re-check broken flag (edge case)

Verdict: **BLOCK** — GAP-2227 renders the `raise_shield` action mechanically inert (core defensive mechanic provides zero AC benefit). DEF-2230 corrupts fighter MAP after AoO.
