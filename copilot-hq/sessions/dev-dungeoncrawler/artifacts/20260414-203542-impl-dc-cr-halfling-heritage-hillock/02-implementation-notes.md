# Implementation Notes: dc-cr-halfling-heritage-hillock

- Feature: dc-cr-halfling-heritage-hillock
- Release: 20260412-dungeoncrawler-release-m
- Commit: a710ad62b
- KB reference: none found

## What changed

### FeatEffectManager.php
Added `case 'hillock':` to the heritage switch (after `gutsy`). Sets `derived_adjustments.flags.hillock_halfling_bonus_healing = TRUE` and appends a note. Flag is for client-side display. Server-side mechanics are applied in the phase handlers below.

### DowntimePhaseHandler.php — `processLongRest()`
After computing `$hp_per_rest = max(1, $con_mod) * $level`, added:
```php
if (($entity['heritage'] ?? '') === 'hillock') {
    $hp_per_rest += $level;
}
```
This adds `+level` HP to the overnight rest calculation. The `$healing_blocked` check that follows applies to the combined total (starvation/thirst still blocks all healing including the Hillock bonus, as intended — no special healing bypass).

### ExplorationPhaseHandler.php — `processTreatWounds()`
Inside the existing entity-state update loop, added the snack rider:
```php
if ($healed > 0 && ($entity['heritage'] ?? '') === 'hillock') {
    $target_level = max(1, (int) ($entity['level'] ?? ($entity['stats']['level'] ?? 1)));
    $healed += $target_level;
}
```
`$healed > 0` ensures the bonus only triggers on success or critical success (not on failure or critical failure, where `$healed` remains 0). One function call = one Treat Wounds action = one snack rider application; no extra state guard needed.

### CharacterManager.php
Updated benefit stub from `'Faster healing'` to the accurate mechanic description.

## Why it's safe

- No new routes, controllers, or DB schema changes.
- Security AC exemption confirmed (passive heritage/healing adjustment; no new route surface).
- The `$healing_blocked` guard in `processLongRest()` (starvation/thirst phase) is checked after `$hp_per_rest` is computed, so it correctly blocks both normal and Hillock bonus healing when blocked.
- The snack rider check (`$healed > 0`) is inside the same entity loop that writes `last_treated_wounds_at`, so it reads the correct target entity and cannot double-apply within a single call.
- No PII; no secrets.

## Acceptance criteria status

- [x] Hillock Halfling selectable as a halfling heritage at character creation (already in catalog)
- [x] On overnight rest, Hillock Halfling regains extra HP = level (DowntimePhaseHandler)
- [x] Treat Wounds snack rider adds HP = level when another character treats the Hillock Halfling (ExplorationPhaseHandler)
- [x] Snack rider only applies to Treat Wounds (`$healed > 0` guard; only TW function sets `$healed`)
- [x] Overnight bonus stacks on top of normal recovery (`$hp_per_rest += $level`)
- [x] Characters without Hillock heritage receive no bonus (heritage check guards both paths)
- [x] Snack rider cannot be applied multiple times to same TW result (one function call = one application)

## Verification commands

```bash
# Confirm hillock flag in FeatEffectManager
grep -A8 "case 'hillock':" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/FeatEffectManager.php

# Confirm overnight rest bonus
grep -A4 "dc-cr-halfling-heritage-hillock" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/DowntimePhaseHandler.php

# Confirm snack rider
grep -A5 "dc-cr-halfling-heritage-hillock" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/ExplorationPhaseHandler.php
```

## Rollback plan

Revert commit `a710ad62b`. No migration/schema changes; revert is safe.
