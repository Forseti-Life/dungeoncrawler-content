- command: |
    ## REGRESSION FIX REQUIRED — dc-cr-equipment-system (2 defects)

    QA Gate 2 BLOCK issued 2026-04-06. Fix both defects before re-requesting Gate 2 verification.

    ---

    ### BLOCK 1: Encumbrance formula does not match PF2E AC spec

    **File**: `dungeoncrawler_content/src/Service/InventoryManagementService.php`

    **AC requires** (explicit):
    - Encumbered condition: `total_bulk >= (STR_score / 2) + 5`
    - Immobilized condition: `total_bulk >= STR_score + 5`

    For STR 10: encumbered at bulk ≥ 10, immobilized at bulk ≥ 15.

    **Current wrong implementation**:
    - `getInventoryCapacity()` returns `5 + STR_modifier` (STR 10 → 5) — wrong basis
    - `getEncumbranceStatus()` thresholds at 75%/100% of capacity — wrong
    - Returns `"overburdened"` for max-load — must be `"immobilized"`

    **Fix**:
    1. `getInventoryCapacity($str_score)`: return `10 + floor(($str_score - 10) / 2)` (i.e., `10 + STR_mod`) — this is the max unencumbered carrying capacity
    2. `getEncumbranceStatus($total_bulk, $str_score)`: compute thresholds from `$str_score` directly:
       - `$encumbered_threshold = floor($str_score / 2) + 5`
       - `$immobilized_threshold = $str_score + 5`
       - If `$total_bulk >= $immobilized_threshold` → return `'immobilized'`
       - If `$total_bulk >= $encumbered_threshold` → return `'encumbered'`
       - Otherwise → return `'unencumbered'`
    3. Rename all occurrences of `"overburdened"` → `"immobilized"` in the service and any downstream callers.

    **Verify**:
    ```php
    // STR 10: encumbered_threshold=10, immobilized_threshold=15
    getEncumbranceStatus(9.0, 10.0)  // → unencumbered
    getEncumbranceStatus(10.0, 10.0) // → encumbered
    getEncumbranceStatus(15.0, 10.0) // → immobilized
    getEncumbranceStatus(14.9, 10.0) // → encumbered
    ```

    ---

    ### BLOCK 2: STR requirement check penalty on equip not implemented

    **AC requires**: "Attempting to equip armor the character lacks the Strength requirement for applies the armor check penalty to all Strength/Dexterity skill checks."

    **Current state**: `str_req` field exists in catalog data (e.g., breastplate `str_req: 16`, full plate `str_req: 18`) but no enforcement exists in `moveItem`, `addItemToInventory`, or any equip path.

    **Fix**: In the equip/worn-item path of `InventoryManagementService`:
    1. When an armor item transitions to `worn`/`equipped` state, check `item['str_req']` against `character.ability_scores.str` (or STR score from CharacterManager/CharacterCalculator).
    2. If character STR < `str_req`, record a status flag on the equipped item: `'str_penalty_active' => true`.
    3. In `CharacterCalculator` (or wherever skill checks are resolved), when computing STR/DEX-based skill checks, check whether any equipped armor has `str_penalty_active: true`. If yes, add the armor's `check_penalty` value as an additional penalty to the roll.
    4. The equip itself should NOT be blocked — only the penalty should apply (PF2E rules: you can equip it, you just suffer the penalty).

    **Scope note**: if the full CharacterCalculator integration would require large refactor, implement the `str_penalty_active` flag on equip and note the downstream integration point in `02-implementation-notes.md`. QA will verify the flag is set correctly; full skill-check penalty propagation can be a follow-on AC item if the scope is too large.

    ---

    **Acceptance criteria for this fix**:
    - Encumbrance thresholds match PF2E spec for STR 10 and STR 16 test cases
    - `"immobilized"` is returned (not `"overburdened"`) at max encumbrance
    - STR req: equipping armor below str_req sets `str_penalty_active: true` on the item in inventory state
    - Run `python3 scripts/role-permissions-validate.py --site dungeoncrawler --base-url https://dungeoncrawler.forseti.life` and confirm 0 violations
    - Update `features/dc-cr-equipment-system/02-implementation-notes.md` with changes

    **QA verification report**: sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-impl-dc-cr-equipment-system.md
    **Dispatched by**: ceo-copilot-2

- Agent: dev-dungeoncrawler
- Status: pending
