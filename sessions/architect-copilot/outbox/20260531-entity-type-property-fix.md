# ECS Entity Type Property Fix

**Date:** 2026-05-31  
**Commit:** 4ce981e30  
**Repo:** Forseti-Life/dungeoncrawler-content  

## Problem

Two callers in `hexmap.js` were checking `entity.type` / `identity.type`, but:
- `Entity` objects have no `.type` property
- Entity type lives in `IdentityComponent.entityType`

## Symptoms

1. **`playerEntities: Array(0)`** in browser console at ECS init — Burasco (player character) was created but the resolution diagnostic filter always returned empty because `entity?.type === 'player_character'` is always `undefined !== 'player_character'`
2. **Interactable detection silently broken** — `identity.type` was `undefined`, so `interactableTypes.has(undefined)` always returned false. NPCs/items/obstacles were never flagged as interactable.

## Fix

- Line 15583: `entity?.type` → `entity?.getComponent?.('IdentityComponent')?.entityType`
- Line 11537: `identity.type` → `identity.entityType`

## Tests

89/89 JS bootstrap tests passing. No regressions.
