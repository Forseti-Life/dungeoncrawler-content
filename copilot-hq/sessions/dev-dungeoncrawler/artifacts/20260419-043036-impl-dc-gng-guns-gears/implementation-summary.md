# dc-gng-guns-gears Implementation Summary

- Commit: `1cdb1f07d`
- Release: `20260412-dungeoncrawler-release-p`
- Module: `dungeoncrawler_content`

## Files changed

| File | Change |
|---|---|
| `src/Service/CharacterManager.php` | Added `gunslinger` + `inventor` to CLASSES const; added CONSTRUCT_COMPANION const |
| `src/Service/EquipmentCatalogService.php` | Added `'gng'` to VALID_BOOKS; added 4 firearms to CATALOG |
| `src/Controller/GunGearsController.php` | NEW — 8 API endpoints for GNG mechanics |
| `dungeoncrawler_content.routing.yml` | 8 new routes appended |
| `dungeoncrawler_content.services.yml` | Registered GunGearsController |

## Gunslinger class

- `class_key = 'gunslinger'`, HP 8, DEX key ability
- Singular Expertise: firearms/crossbows Expert at L1
- Way subclass (permanent): drifter / vanguard / sniper / pistolero / reloading
- 10 class feature levels defined (L1–L19)

## Inventor class

- `class_key = 'inventor'`, HP 8, INT key ability
- Innovation subclass (permanent): construct / weapon / armor
- Overdrive: 1-action Crafting check vs DC 15+level; +2 or +3 damage bonus; crit-fail = explosion
- Unstable actions: crit-fail → splash fire damage tracked on character
- Construct Innovation: scaffolds construct_companion on character at class selection

## Firearms

| ID | Name | Reload | Misfire | Special |
|---|---|---|---|---|
| `flintlock-pistol` | Flintlock Pistol | 1 | 1 | fatal 1d8 |
| `flintlock-musket` | Flintlock Musket | 1 | 1 | fatal 1d12 |
| `pepperbox` | Pepperbox | 0 (per shot) | 1 | 6-shot cylinder; cylinder reload 3 |
| `sword-pistol` | Sword Pistol | — | — | Combination: melee/ranged modes |

## GunGearsController endpoints

All require `_character_access: TRUE` + `_csrf_request_header_mode: TRUE`.

| Method | Path | Action |
|---|---|---|
| POST | `/api/character/{id}/class-subtype` | Set Way or Innovation (server-validated enum) |
| POST | `/api/character/{id}/firearm/{wid}/reload` | Mark loaded; validates action cost from catalog |
| POST | `/api/character/{id}/firearm/{wid}/fire` | Fire + misfire resolution; jams on crit-fail misfire |
| POST | `/api/character/{id}/firearm/{wid}/clear-jam` | Clear jam (3 actions required) |
| PATCH | `/api/character/{id}/firearm/{wid}/mode` | Combination weapon mode switch |
| POST | `/api/character/{id}/construct-companion` | Create/update construct companion |
| POST | `/api/character/{id}/overdrive` | Inventor Overdrive resolution |
| POST | `/api/character/{id}/unstable-action` | Unstable action splash damage resolution |

## CONSTRUCT_COMPANION const (CharacterManager)

- Command via Inventive Interface (free action)
- HP = 4 * level; AC = 15 + level
- Construct immunities (mental, poison, death, disease, etc.)
- Advancement: level_1 / level_4 / level_8 / level_16
- Repair with Crafting (not Recovery checks)
- 2 starting modification slots

## AC coverage

All 30 REQs across Happy Path + Edge Cases + Security are addressed:
- Gunslinger class ✓ (3 REQs)
- Inventor class ✓ (3 REQs)
- Firearms + combination weapons ✓ (4 REQs)
- Construct Companion ✓ (2 REQs)
- Access + rules integrity ✓ (2 REQs)
- Edge cases: reload-0 vs reload-1+, double-jam guard, mode-switch identity, construct+minion coexistence ✓ (4 REQs)
- Failure modes: enum validation, 403 for non-owners, server-computed state ✓ (3 REQs)
- Security AC ✓ (4 criteria)
