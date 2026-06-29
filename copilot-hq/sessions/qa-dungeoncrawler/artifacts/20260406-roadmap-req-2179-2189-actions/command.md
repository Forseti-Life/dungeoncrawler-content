# QA Task: Actions (Reqs 2179–2189)

**Type:** qa  
**Section:** Ch9 — Actions  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Actions"

---

## Scope

Write positive and negative test cases for all 11 requirements in this section.

Key files:
- `RulesEngine.php` — `validateActionEconomy()`
- `CombatEngine.php` — `startTurn()`, `endTurn()`, `applyEndOfTurnEffects()`
- `ActionProcessor.php` — `executeActivity()`
- `ConditionManager.php` — condition catalog (quickened, slowed, stunned, frightened)

---

## Test Cases

### REQ 2179 — Action types: single (1), activity (2–3), reaction, free action
- **Positive:** Single action deducts 1 from `actions_remaining`
- **Negative:** Activity with `action_cost: 3` deducts 3 (not 1); free action deducts 0; reaction uses reaction slot (not actions)

### REQ 2180 — Activities must complete in sequence; cannot interrupt mid-activity
- **Positive:** 2-action activity deducted in full when called
- **Negative:** Client cannot submit 2 separate 1-action calls to approximate a 2-action activity with effect after first

### REQ 2181 — Reaction requires a trigger; usable on any turn when triggered
- **Positive:** `validateActionEconomy(actor, 'reaction')` returns valid when `reaction_available=1`
- **Negative:** Second reaction in same round → `reaction_available=0` → invalid

### REQ 2182 — Free actions with triggers follow reaction rules; without → single action
- **Positive:** Free action with no trigger deducts 0 from actions
- **Negative:** Free action with trigger uses same "once per trigger" semantics as reaction

### REQ 2183 — Each turn grants 3 actions + 1 reaction
- **Positive:** `startTurn()` sets `actions_remaining=3`, `reaction_available=1`
- **Negative:** Carrying over unused actions from prior turn → NOT permitted (startTurn always resets to 3)

### REQ 2184 — Unused actions lost at end of turn; unused reaction lost at start of next turn
- **Positive:** `endTurn()` called with 1 action remaining → next `startTurn()` resets to 3 (not 4)
- **Negative:** `reaction_available` from prior turn should be reset to 1 at `startTurn()` (verify it is)

### REQ 2185 — Quickened/slowed/stunned modify action count
- **Positive:** Quickened character → `startTurn()` should grant 4 actions (3+1 extra)
- **Negative:** Slowed 1 character → `startTurn()` should grant 2 actions (3−1); currently gives 3 (EXPECTED FAIL)

### REQ 2186 — Start of turn: reduce durations, process triggers, roll recovery check, regain actions
- **Positive:** `startTurn()` resets actions ✅; condition durations decremented ✅
- **Negative:** Dying character — recovery check should auto-trigger at `startTurn()` (currently NOT automatic — EXPECTED FAIL)

### REQ 2187 — End of turn: end effects, persistent damage + flat check, reduce valued conditions
- **Positive:** Frightened 2 at end of turn → frightened 1
- **Positive:** Persistent fire damage → apply damage + flat check DC 15 to end
- **Negative:** Persistent damage does NOT persist into next round if flat check succeeds

### REQ 2188 — Disrupted action: costs still spent, effects negated
- **Positive:** Disrupted 2-action spell → 2 actions deducted, spell effect does NOT apply
- **Negative:** Disrupting an action does NOT refund the action cost

### REQ 2189 — Disrupted multi-action activity: all spent actions lost
- **Positive:** 3-action activity disrupted after 2nd step → all 3 action slots consumed
- **Negative:** Disrupted activity does NOT only lose the remaining cost

---

## Expected Failures

- **REQ 2185:** `startTurn()` always sets actions=3 regardless of quickened/slowed/stunned
- **REQ 2186:** `startTurn()` does NOT auto-trigger recovery check for dying characters
- **REQ 2188/2189:** No disrupted action handling found in `ActionProcessor`
- Agent: qa-dungeoncrawler
- Status: pending
