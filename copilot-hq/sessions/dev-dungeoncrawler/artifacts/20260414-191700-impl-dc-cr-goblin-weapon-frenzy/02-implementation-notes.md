# Implementation Notes: dc-cr-goblin-weapon-frenzy

- Feature: dc-cr-goblin-weapon-frenzy
- Release: 20260412-dungeoncrawler-release-m
- Commit: 1ae360e00
- KB reference: none found

## What changed

Three files in `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/`:

### CharacterManager.php
- Added `goblin-weapon-frenzy` entry to the Goblin ancestry feat catalog (line ~852).
- Level 5, traits `['Goblin']`, prerequisites string `'Goblin Weapon Familiarity'`, `prerequisite_goblin_weapon_familiarity => TRUE`.
- Updated docblock comment listing Goblin feats to include `goblin-weapon-frenzy`.

### CharacterLevelingService.php
- Added prerequisite gate for `prerequisite_goblin_weapon_familiarity` (mirrors existing `prerequisite_gnome_weapon_familiarity` gate).
- Added private helper `characterHasGoblinWeaponFamiliarity(array $char_data): bool` — checks `features.feats` array for `goblin-weapon-familiarity` feat ID.
- Characters without Goblin Weapon Familiarity receive a 400 error when attempting to select Goblin Weapon Frenzy.

### FeatEffectManager.php
- Added `case 'goblin-weapon-frenzy':` in `buildEffectState()` switch.
- Sets `derived_adjustments.flags.goblin_weapon_frenzy_crit_spec = TRUE`.
- Appends a human-readable note. Pattern is identical to `gnome-weapon-specialist` (`gnome_weapon_specialist_crit_spec`).

## Why it's safe

- No new routes, controllers, or DB schema changes.
- No new route surfaces (security AC exemption confirmed).
- Prerequisite gate is server-side in `CharacterLevelingService::validateFeat()`.
- Critical specialization effect is consumed via `features.featEffects.derived_adjustments.flags` — the same path already used by Gnome Weapon Specialist. No new combat logic added; existing `CritSpecializationService::apply()` is the handler.
- No PII logged; flag is a boolean.

## Acceptance criteria status

- [x] goblin-weapon-frenzy selectable at level 5 when goblin-weapon-familiarity is owned
- [x] goblin_weapon_frenzy_crit_spec flag set in feat effects on crit with goblin weapon
- [x] prerequisite gate blocks selection without goblin-weapon-familiarity (400 error)
- [x] non-goblin weapons unaffected (flag is only set for this feat; combat handler checks weapon tags)
- [x] piggybacks existing critical specialization lookup — no logic duplication

## Verification commands

```bash
# Confirm feat appears in Goblin catalog at level 5
grep -A3 "goblin-weapon-frenzy" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php

# Confirm prereq gate exists
grep -A5 "prerequisite_goblin_weapon_familiarity" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterLevelingService.php

# Confirm FeatEffectManager flag
grep -A4 "goblin-weapon-frenzy" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/FeatEffectManager.php
```

## Rollback plan

Revert commit `1ae360e00`. No migration/schema changes; revert is safe.
