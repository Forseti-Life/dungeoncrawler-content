# Implementation Notes (Dev-owned)
# Feature: dc-cr-ancestry-traits

## Summary
Ancestry creature traits system — stores and exposes a character's canonical PF2e trait strings (e.g. `["Dwarf", "Humanoid"]`) derived from their ancestry at character creation. Provides `hasTraits` API for spell/ability targeting prerequisite work.

## Knowledgebase references
- Prior PM decision (commit `576262c5`): trait matching is case-sensitive, canonical Title Case. Enforced via `TRAIT_CATALOG` constant and `isValidTrait()` check.
- Bug fixed in this PR: `saveCharacter()` was looking up `ANCESTRIES[$machine_id]` (e.g. `ANCESTRIES['half-elf']`) but ANCESTRIES is keyed by canonical name (`'Half-Elf'`). Fixed by adding `resolveAncestryCanonicalName()`.

## Files / Components Touched
- `dungeoncrawler_content/src/Service/CharacterManager.php` — added `TRAIT_CATALOG`, `resolveAncestryCanonicalName()`, `getAncestryTraits()`, `hasTraits()`, `isValidTrait()`, `mergeTraits()` static helpers
- `dungeoncrawler_content/src/Form/CharacterCreationStepForm.php` — `saveCharacter()`: fixed ANCESTRIES lookup; auto-assigns traits at creation
- `dungeoncrawler_content/src/Service/CharacterStateService.php` — added `traits` field to PC state; added `resolveCharacterTraits()` with legacy fallback
- `dungeoncrawler_content/src/Controller/CharacterTraitsController.php` — NEW controller for traits API
- `dungeoncrawler_content/dungeoncrawler_content.routing.yml` — 3 new routes

## API Endpoints

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/dungeoncrawler/traits` | `access dungeoncrawler characters` | Canonical trait catalog |
| GET | `/api/character/{id}/traits` | `_character_access` | Character's current traits |
| GET | `/api/character/{id}/traits/check` | `_character_access` | hasTraits query `?traits[]=X` |

No write endpoints — trait assignment is server-side only at character creation.

## Data Model
- Traits stored as `traits[]` string array in `character_data` JSON column of `dc_campaign_characters`
- Legacy characters (no stored traits): `resolveCharacterTraits()` derives from ancestry machine ID at read time

## Access Control
- Trait catalog: requires `access dungeoncrawler characters` (authenticated player or admin)
- Character traits/check: `_character_access` (own character or `administer dungeoncrawler content`)
- No mutation endpoint (client cannot set traits directly)

## Testing Performed
All AC criteria verified manually via curl:
1. Catalog returns 16 canonical traits ✅
2. Half-elf (char 53) traits: `["Human", "Elf", "Humanoid", "Half-Elf"]` ✅
3. `hasTraits` true for Humanoid, Elf on half-elf ✅
4. `hasTraits` false for Dwarf on half-elf ✅
5. Unknown trait rejected: `"Unknown trait: NotARealTrait"` ✅
6. `humanoid` (lowercase) rejected as unknown (case-sensitive) ✅
7. State endpoint includes `traits` field ✅
8. Non-existent character returns 404 ✅
9. Anon access to catalog and traits: 403 ✅
10. No duplicates in traits array ✅
11. Halfling (char 207) traits: `["Halfling", "Humanoid"]` ✅

## Rollback
Revert commit `e97a248b5`. No schema changes — traits stored in existing `character_data` JSONB column.

## What I learned
- ANCESTRIES machine ID lookup bug was silent (returned NULL) — existing size/speed/languages were also wrong for multi-word ancestries (Half-Elf, Half-Orc). Fixed as a side effect.
