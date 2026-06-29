# Dev Task: Fix Action Economy — Condition Modifiers & Disrupted Actions (Reqs 2180, 2185, 2186, 2188, 2189)

**Type:** dev-impl  
**Section:** Ch9 — Actions  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Actions"  
**Requirements:** 2185, 2186, 2188, 2189 (2180 is architectural — document as won't-fix for now)

---

## FIX 1 — REQ 2185: Apply condition effects on action count in startTurn

**File:** `CombatEngine.php` — `startTurn()`  
**Current:** Always sets `actions_remaining = 3`  
**Fix:** Check active conditions and adjust:

```php
$base_actions = 3;

// Apply quickened (+1 action).
if ($this->conditionManager->hasCondition($participant_id, 'quickened')) {
    $base_actions += 1;
}

// Apply slowed (−value actions, min 0).
$slowed_value = $this->conditionManager->getConditionValue($participant_id, 'slowed') ?? 0;
$base_actions = max(0, $base_actions - $slowed_value);

// Apply stunned (stunned acts as slowed+stunned: lose stunned value actions first).
$stunned_value = $this->conditionManager->getConditionValue($participant_id, 'stunned') ?? 0;
if ($stunned_value > 0) {
    $reduce = min($stunned_value, $base_actions);
    $base_actions = max(0, $base_actions - $reduce);
    // Reduce stunned by actions lost.
    $this->conditionManager->decrementCondition($participant_id, 'stunned', $reduce);
}

$this->store->updateParticipant($participant_id, [
    'actions_remaining' => $base_actions,
    ...
]);
```

PF2e ref: "The quickened condition... you gain 1 additional action at the start of your turn. Slowed X: at the start of each of your turns, reduce your actions by X."

---

## FIX 2 — REQ 2186: Auto-trigger recovery check at start of turn if dying

**File:** `CombatEngine.php` — `startTurn()`  
**Fix:** After resetting actions, check if character is dying and trigger recovery check:

```php
$dying_value = $this->conditionManager->getConditionValue($participant_id, 'dying') ?? 0;
if ($dying_value > 0) {
    $recovery = $this->conditionManager->processDying($participant_id, $encounter_id);
    // Include recovery result in startTurn() response.
    $result['recovery_check'] = $recovery;
}
```

Include the recovery result in the response JSON so the frontend can narrate it.  
Note: This also depends on FIX from the HP/Dying section (REQ 2158 — DC must be 10 + dying_value).

---

## FIX 3 — REQ 2188/2189: Disrupted action/activity handling

**File:** `ActionProcessor.php` and/or `RulesEngine.php`  
No current disruption system exists. Add disruption flag to action resolution:

### Approach:
1. Add `disrupted: bool` field to action request payload
2. In `executeActivity()` and `executeAttack()` etc.: if `disrupted=true`, deduct action cost but return early without applying effects
3. Log the disruption event

```php
if (!empty($action_data['disrupted'])) {
    // Deduct action cost but apply no effect.
    $this->store->updateParticipant($participant_id, ['actions_remaining' => $actions_after]);
    $this->logAction($encounter_id, $participant_id, 'disrupted', NULL, $action_data, ['reason' => 'disrupted']);
    return ['status' => 'ok', 'disrupted' => TRUE, 'actions_remaining' => $actions_after];
}
```

For multi-action activities (REQ 2189), the full cost should be deducted immediately when the activity is declared (not step-by-step).

---

## Note on REQ 2180 (Activity Sequencing)

PF2e rule: "You cannot interrupt an activity by taking other actions." Since the system is API-driven and activities are submitted as a single call with `action_cost`, sequential interruption is architecturally prevented. Mark as `implemented` after verifying there is no way to interleave actions with a multi-action activity call.

---

## DB Update

After implementation and QA verification:
```sql
UPDATE dc_requirements SET status='implemented' WHERE id IN (2180, 2185, 2186, 2188, 2189);
```
- Agent: dev-dungeoncrawler
- Status: pending
