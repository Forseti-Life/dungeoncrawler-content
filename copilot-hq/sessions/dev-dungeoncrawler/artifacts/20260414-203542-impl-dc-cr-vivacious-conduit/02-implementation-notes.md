# Implementation Notes: dc-cr-vivacious-conduit

- Feature: dc-cr-vivacious-conduit
- Release: 20260412-dungeoncrawler-release-m
- Commit: c3e4db145
- KB reference: none found

## What changed

### CharacterManager.php
Added `vivacious-conduit` entry to the Gnome feat catalog at level 9 (after `first-world-adept`):
```php
['id' => 'vivacious-conduit', 'name' => 'Vivacious Conduit', 'level' => 9,
 'traits' => ['Gnome'], 'prerequisites' => '',
 'benefit' => 'If you rest for 10 minutes, you regain Hit Points equal to your
   Constitution modifier × half your level. This is cumulative with any healing
   you receive from Treat Wounds.'],
```

### FeatEffectManager.php
Added `case 'vivacious-conduit':` to the feat switch (between `gnome-weapon-specialist` and `gnome-weapon-expertise`). Sets `derived_adjustments.flags.vivacious_conduit_short_rest_heal = TRUE` and adds a display note. The flag is for client-side display; actual healing is server-side in the phase handler.

### ExplorationPhaseHandler.php — `processRest()`
Replaced the no-op short-rest return with active Vivacious Conduit logic:
1. Find actor entity in dungeon_data (by reference).
2. Check if `'vivacious-conduit'` is in `array_column($entity['feats'], 'id')`.
3. Compute: `$con_mod = max(0, entity['stats']['con_modifier'] ?? 0)`, `$half_level = floor($level / 2)`, `$bonus = $con_mod × $half_level`.
4. If `$bonus > 0`: apply to `entity['state']['hit_points']['current']`, capped at `max`. Persist. Return `entity_state` mutation.
5. Returns `vivacious_conduit_healed` key in result for client display.

## Why it's safe

- No new routes, controllers, or DB schema changes.
- Security AC exemption confirmed (passive healing adjustment; no new route surface).
- `max(0, $con_mod)` ensures negative Con modifiers produce zero bonus (not negative healing) per AC.
- `min($max_hp, $current_hp + $bonus)` prevents HP exceeding maximum.
- No client-supplied healing values: bonus is computed entirely from entity's server-side stats.
- Stacks correctly with Treat Wounds: applied in a completely separate code path (`processRest` vs `processTreatWounds`); values are additive on the client/state side.
- No state flag needed to prevent double-apply: processRest is one function call = one rest action.
- Characters without the feat skip the block entirely (feat_ids check fails).

## Acceptance criteria status

- [x] Vivacious Conduit selectable as a Gnome feat at level 9 (CharacterManager catalog)
- [x] After 10-minute rest, character regains HP = Con modifier × half level (ExplorationPhaseHandler::processRest)
- [x] Stacks with Treat Wounds (separate code paths, additive)
- [x] Half level uses standard floor(level/2) rounding (consistent across levels)
- [x] Negative Con modifier → zero healing, not negative (max(0, $con_mod))
- [x] Characters without the feat receive no bonus (feat_ids in_array check)
- [x] Bonus not applied outside 10-minute rest window (logic only in processRest short-rest path)

## Verification commands

```bash
# Confirm feat in CharacterManager
grep -A2 "vivacious-conduit" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php

# Confirm flag in FeatEffectManager
grep -A5 "case 'vivacious-conduit'" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/FeatEffectManager.php

# Confirm rest healing logic
grep -A15 "dc-cr-vivacious-conduit" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/ExplorationPhaseHandler.php
```

## Rollback plan

Revert commit `c3e4db145`. No migration/schema changes; revert is safe.
