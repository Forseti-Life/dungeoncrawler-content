# Verification Report: Reqs 2179–2189 — Actions
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK — DEF-2182

## Scope
Action economy (reqs 2179–2189): action types, activities, reactions, free actions, start/end of turn, quickened/slowed/stunned, persistent damage, disrupted actions.

## KB reference
None found relevant in knowledgebase/.

## Note on inbox expected failures
Several inbox-listed "expected failures" were already fixed in current production code:
- REQ 2185: quickened/slowed/stunned ARE handled in `startTurn()` — PASS
- REQ 2186: recovery check IS auto-triggered in `startTurn()` — PASS
- REQ 2188/2189: disrupted action handling IS in `ActionProcessor::executeAction()` — PASS

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2179-P: Single action deducts 1 | PASS | `validateActionEconomy(actor, 1)` deducts cost; `resolveAttack` line 797: `actions_remaining - 1` ✓ |
| TC-2179-P: Activity with action_cost=3 deducts 3 | PASS | `executeActivity`: reads `$action_data['action_cost']`, passes to `validateActionEconomy` ✓ |
| TC-2179-P: Free action deducts 0 | PASS | `validateActionEconomy('free')`: returns `actions_after = actions_remaining` (no deduction) ✓ |
| TC-2179-P: Reaction uses reaction slot | PASS | `validateActionEconomy('reaction')`: checks `reaction_available`; sets it to 0 on spend ✓ |
| TC-2180-P: 2-action activity deducted atomically | PASS | `executeActivity` calls `validateActionEconomy` then deducts full cost in one `updateParticipant` call ✓ |
| TC-2181-P: Reaction valid when `reaction_available=1` | PASS | `validateActionEconomy('reaction')`: `if (!empty($participant['reaction_available'])) → is_valid=TRUE` ✓ |
| TC-2181-N: Second reaction → invalid | PASS | `executeReactionAction` sets `reaction_available=0`; second call fails validation ✓ |
| TC-2182-P: Free action (no trigger) deducts 0 | PASS | `executeFreeAction` → `validateActionEconomy('free')` → no deduction ✓ |
| TC-2182-N: Free action with trigger — same semantics as reaction | **FAIL** | DEF-2182: `executeFreeAction` never checks a trigger condition; no `has_trigger` param handling. Free actions with triggers should consume `reaction_available` the same as reactions, but the current code always passes `'free'` to `validateActionEconomy` regardless of whether a trigger is declared. |
| TC-2183-P: `startTurn()` sets actions=3, reaction=1 | PASS | `startTurn`: `base_actions=3`, `reaction_available=1` set in `updateParticipant` ✓ |
| TC-2183-N: Prior turn leftover NOT carried over | PASS | `startTurn` always resets to 3 (adjusted for conditions) regardless of prior state ✓ |
| TC-2184-P: Unused actions lost at end of turn | PASS | `endTurn` does NOT add unused actions to next turn; `startTurn` always resets ✓ |
| TC-2184-P: `reaction_available` reset to 1 at `startTurn` | PASS | `startTurn` line 252: `reaction_available => 1` ✓ |
| TC-2185-P: Quickened → 4 actions at start of turn | PASS | `startTurn`: `if (hasCondition('quickened')) { $base_actions += 1 }` ✓ |
| TC-2185-P: Slowed 1 → 2 actions | PASS | `startTurn`: `$base_actions = max(0, $base_actions - $slowed_value)` ✓ |
| TC-2185-P: Stunned 2 → 1 action, condition decremented by 2 | PASS | `startTurn`: `$reduce = min($stunned_value, $base_actions)`, decrements condition ✓ |
| TC-2186-P: Recovery check auto-triggers at `startTurn` for dying char | PASS | `startTurn`: `if ($dying_value > 0) { processDying($pid, $eid) }` ✓ |
| TC-2187-P: Frightened 2 → frightened 1 at end of turn | PASS | `processEndOfTurnEffects` calls `conditionManager->tickConditions()`; frightened is `end_of_turn` type ✓ |
| TC-2187-P: Persistent damage fires at end of turn + flat check DC 15 | PASS | `processEndOfTurnEffects`: applies damage, rolls `rollFlatCheck(15)`, clears if success ✓ |
| TC-2187-P: Persistent damage cleared on flat check success | PASS | `if ($cleared) { store->removeCondition() }` ✓ |
| TC-2188-P: Disrupted action — cost deducted, effect NOT applied | PASS | `executeAction`: `if (!empty($action_data['disrupted'])) { deduct cost, return disrupted=TRUE }` ✓ |
| TC-2188-N: Disrupted action does NOT refund cost | PASS | Cost deducted via `actions_remaining - action_cost`; no refund path ✓ |
| TC-2189-P: Disrupted multi-action activity — all actions lost | PASS | `executeAction` disrupted block reads `$action_data['action_cost']` (must be full declared cost); deducted atomically ✓ |

## Defect

### DEF-2182 (MEDIUM) — Free actions with triggers not consuming reaction slot
- **File**: `ActionProcessor::executeFreeAction` (lines ~634–661)
- **Code**: Always calls `validateActionEconomy($actor, 'free')` regardless of trigger presence
- **Problem**: PF2e rule REQ 2182 states free actions with triggers follow the same "once per trigger" semantics as reactions — they should consume `reaction_available`. The current implementation treats ALL free actions as no-cost regardless of whether a trigger is declared.
- **Impact**: Players can use triggered free actions unlimited times per turn. In practice this matters for abilities like Nimble Dodge, Parry, etc.
- **Fix**: Accept an optional `has_trigger` bool in `$action_data`. When `has_trigger=TRUE`, validate and consume `reaction_available` instead of using the 'free' cost path.

## Summary
22 of 23 test cases pass. Several inbox-expected failures are already fixed in production (quickened/slowed/stunned action adjustment, recovery check auto-trigger, disrupted action handling). One medium defect: DEF-2182 — free actions with triggers do not consume `reaction_available`, allowing unlimited use of triggered free abilities per turn.
