# Artifact: dc-ui-token-readability Implementation

## Commit
`5ce9655e2`

## Files changed

### `js/ecs/systems/RenderSystem.js`
- `updateHealthBar` — refactored from fixed 40px three-object Container to single PIXI.Graphics cleared+redrawn each frame; width = `max(20, hexSize*1.3)`; vertical offset = `hexSize*0.8`
- `updateTeamRing` — new; colored circle ring drawn behind sprite (`zIndex-1`); team→color map: `player=0x3b82f6`, `ally=0x22c55e`, `enemy=0xef4444`, `neutral=0x9ca3af`; radius = `hexSize*0.55`; only rendered when CombatComponent present
- `updateSelectionRing` — new; reads `render._isSelected` / `render._isCurrentTurn`; blue ring for selected, gold double-ring for active turn; hides when neither flag set
- `updateConditionBadges` — new; reads `render._conditions[]`; compact labeled dots above token; capped at 3 badges below hexSize 25, 6 otherwise; hidden when `hexSize < 18` or conditions empty; `render._conditions = []` default, ready for future server hydration
- `updateInteractMarker` — new; `!` badge top-right; hidden when `hexSize < 16`; reads `render._isInteractable`; interactable types: `item`, `npc`, `obstacle`
- `syncEntityToSprite` — added `combat` component read; wired all new methods; extended invisible early-return to hide teamRing/selectionRing/conditionBadges/interactMarker
- `removeSprite` — extended to destroy teamRing, selectionRing, conditionBadges (children:true), interactMarker

### `js/hexmap.js`
- `syncTokenBadgeState()` — new method; iterates all entities; sets `_isSelected`, `_isCurrentTurn`, `_conditions` (defaults `[]`), `_isInteractable` on each entity's RenderComponent
- `selectEntity` — removed broken sprite tint hack (`render.sprite.tint = 0x60a5fa`); tint was overwritten each frame by RenderSystem, was a no-op; added `syncTokenBadgeState()` call at end
- `deselectEntity` — tint reset `0xffffff` retained (harmless); added `syncTokenBadgeState()` call before log
- `onTurnChange` — added `syncTokenBadgeState()` call at end (after `selectEntity` which already calls it — ensures NPCs whose turn it is also get `_isCurrentTurn` flag)

## AC coverage
- AC-001: team ring + selection ring + current-turn ring — done
- AC-002: condition badges + interact marker — done (badges default-empty, wired for hydration)
- AC-003: zoom-aware health bar, badge/ring sizing from hexSize — done; badges/marker hide at low zoom
- AC-004: hidden state — invisible early-return extends to all new overlays; badge state from ECS not DOM
