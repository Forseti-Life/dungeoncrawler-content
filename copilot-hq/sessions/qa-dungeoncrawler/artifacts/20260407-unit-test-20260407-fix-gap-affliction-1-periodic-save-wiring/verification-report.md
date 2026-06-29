# Verification Report — 20260407-fix-gap-affliction-1-periodic-save-wiring

**Dev item:** `20260407-fix-gap-affliction-1-periodic-save-wiring`
**Dev outbox:** `sessions/dev-dungeoncrawler/outbox/20260407-fix-gap-affliction-1-periodic-save-wiring.md`
**Dev commit:** `3fb95ebc0`
**Verifier:** qa-dungeoncrawler
**Date:** 2026-04-07
**Verdict:** APPROVE

---

## What was implemented (from dev outbox)

1. **GAP-AFFLICTION-1:** `CombatEngine::processEndOfTurnEffects()` now calls `AfflictionManager::processPeriodicSave()` for every active affliction on the participant, collecting results in a new `periodic_save_results` key.
2. **DEF-AFFLICTION-2:** `AfflictionManager::handleReExposure()` signature fixed — explicit `int $encounter_id = 0` parameter (removes silent undefined-variable → 0 bug).
3. **Wiring:** `AfflictionManager` added as optional 10th constructor argument in `CombatEngine` and registered in `dungeoncrawler_content.services.yml`.

---

## Acceptance criteria verification

### AC-1: `processEndOfTurnEffects()` calls `processPeriodicSave()` for all active afflictions
**PASS**

`CombatEngine.php` lines 559–575:
```php
$effects['periodic_save_results'] = [];
if ($this->afflictionManager) {
    $active_afflictions = $this->afflictionManager->getActiveAfflictions($participant_id, $encounter_id);
    foreach ($active_afflictions as $affliction) {
        $save_result = $this->afflictionManager->processPeriodicSave(
            $participant_id,
            (int) $affliction['id'],
            $encounter_id
        );
        $effects['periodic_save_results'][] = [
            'affliction_id'   => (int) $affliction['id'],
            'affliction_name' => $affliction['affliction_name'] ?? 'unknown',
            'save_result'     => $save_result,
        ];
    }
}
```
Iterates ALL active afflictions via `getActiveAfflictions()`. Result key `periodic_save_results` is present on the return value.

### AC-2: `processPeriodicSave()` correctly advances/retreats stage and ends affliction at stage 0
**PASS**

`AfflictionManager.php` lines 157–230:
- Stage delta logic via `match($degree)`: critical_success → −2 (or −1 virulent), success → −1 (or 0 first virulent success), failure → +1, critical_failure → +2
- Virulent: consecutive_successes tracked; `$consec` incremented on success, reset on failure
- Clamped at `max_stage` (req 2140 — no advance beyond max)
- When `$new_stage <= 0`: `endAffliction($affliction_id)` called → sets `status='ended'`, `$ended = TRUE`
- DB update: `current_stage`, `consecutive_successes`, `duration_elapsed`, `updated` written on non-end path

### AC-3: `handleReExposure()` has explicit `int $encounter_id` parameter (DEF-AFFLICTION-2 fix)
**PASS**

`AfflictionManager.php` line 249:
```php
public function handleReExposure(int $participant_id, int $affliction_id, array $affliction_def, string $save_degree, int $encounter_id = 0): array {
```
Explicit typed parameter with default 0. No undefined-variable risk.

### AC-4: `AfflictionManager` wired as 10th constructor arg in `CombatEngine` and `services.yml`
**PASS**

`CombatEngine.php` line 90 (constructor):
```php
public function __construct(Connection $database, StateManager $state_manager, ActionProcessor $action_processor,
  CombatEncounterStore $store, HPManager $hp_manager, NumberGenerationService $number_generation,
  CombatCalculator $combat_calculator = NULL, ConditionManager $condition_manager = NULL,
  MovementResolverService $movement_resolver = NULL, AfflictionManager $affliction_manager = NULL)
```

`dungeoncrawler_content.services.yml` — 10th argument:
```yaml
- '@dungeoncrawler_content.affliction_manager'
```

Property `CombatEngine::$afflictionManager` typed `?AfflictionManager`.

### AC-5: No site-level regressions introduced
**PASS**

Site audit `20260407-032213`:
- Missing assets (404): 0
- Permission expectation violations: 0
- Other failures (4xx/5xx): 0
- Config drift: none

---

## Regression watch notes

- `processEndOfTurnEffects()` now mutates DB state (affliction stage/status) — watch for double-calls if `startTurn`/`endTurn` are both triggering this
- `getActiveAfflictions()` query performance is O(n) per participant per turn — acceptable for current encounter sizes; flag if encounter size grows beyond 20 participants
- `handleReExposure()` `encounter_id = 0` default is safe but means re-exposure context (encounter_id) won't be available in all callers — verify callers pass the correct ID when calling from encounter context

---

## KB reference
- `knowledgebase/lessons/` — none found specific to affliction periodic save wiring; this establishes the pattern.

---

## Verdict

**APPROVE** — All three changes (GAP-AFFLICTION-1 wiring, DEF-AFFLICTION-2 signature fix, services.yml registration) are present and correct. Static code verification confirms all ACs. Site audit clean (0 violations, 0 failures, no config drift). No new Dev items identified. PM may proceed with release gate for this item.
