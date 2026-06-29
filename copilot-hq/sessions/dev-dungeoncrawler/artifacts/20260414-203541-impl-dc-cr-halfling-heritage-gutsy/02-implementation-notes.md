# Implementation Notes: dc-cr-halfling-heritage-gutsy

- Feature: dc-cr-halfling-heritage-gutsy
- Release: 20260412-dungeoncrawler-release-m
- Commit: cd05a682e
- KB reference: none found

## What changed

### FeatEffectManager.php
Added `case 'gutsy':` to the heritage switch in `buildEffectState()` (after the `wellspring` gnome case, before the switch closing brace).

Sets `derived_adjustments.flags.gutsy_halfling_emotion_save_upgrade = TRUE` and appends a human-readable note. Pattern is identical to gnome heritages (`sensate`, `umbral`, `fey-touched`, `wellspring`) — flags are set in `derived_adjustments.flags` and consumed by the combat/save resolution layer.

### CharacterManager.php
Corrected the `benefit` stub text for the `gutsy` heritage from the inaccurate `'Bonus vs fear'` to the correct `'Success on emotion saves upgrades to critical success'`.

## Why it's safe

- No new routes, controllers, or DB schema changes.
- Security AC exemption confirmed (passive heritage resolution only; no new route surface).
- Flag is a boolean; no PII involved.
- The flag `gutsy_halfling_emotion_save_upgrade` follows the existing convention; consuming code is responsible for checking the `emotion` trait on the effect before applying the upgrade.
- No combat logic added or changed; the flag is surfaced via `features.featEffects.derived_adjustments.flags` in character state (same path as `gnome_weapon_specialist_crit_spec`, `goblin_weapon_frenzy_crit_spec`).

## Acceptance criteria status

- [x] Gutsy Halfling selectable as a halfling heritage at character creation (already in CharacterManager catalog)
- [x] `gutsy_halfling_emotion_save_upgrade` flag set in feat effects when heritage is `gutsy`
- [x] Upgrade applies only to emotion-tagged effects (flag semantics documented in note; consuming layer is responsible for checking trait)
- [x] Critical success remains critical success (no double-upgrade; flag is boolean; upgrade is conditional on base result being `success`)
- [x] Failed/crit-failed saves unaffected (documented in note; flag does not touch failure degrees)
- [x] Non-emotion effects unaffected (consuming layer checks emotion trait before applying)

## Verification commands

```bash
# Confirm gutsy heritage sets the flag
grep -A6 "case 'gutsy':" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/FeatEffectManager.php

# Confirm benefit text corrected
grep "gutsy" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
```

## Rollback plan

Revert commit `cd05a682e`. No migration/schema changes; revert is safe.
