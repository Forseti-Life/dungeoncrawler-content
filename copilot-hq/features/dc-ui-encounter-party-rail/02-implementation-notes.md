# Implementation Notes: dc-ui-encounter-party-rail

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- `UIManager.updateInitiativeTracker()`
- Turn/round/combat state wiring already driven by ECS systems

## Design notes

- Reuse the existing turn-order source of truth; enrich the rendered card model instead of inventing a second combat state system
- Distinguish `player-facing rail` from any future GM-only controls
- Keep click-to-select behavior aligned with existing entity inspector behavior
- Prefer a compact card model that can collapse cleanly on mobile/tablet

## Architectural gotchas

- `UIManager.updateInitiativeTracker()` currently renders from a thin model (`initiative`, `name`, `isCurrent`, `isDefeated`). A richer rail cannot be delivered as CSS-only polish; it needs an expanded participant projection carrying HP, team, condition, and action state.
- The runtime has a deliberate split between `showEntityInfo()` and `showLaunchCharacter()`. Rail selection must not accidentally replace the launch character sheet every time a token or NPC card is clicked.
- Server-authoritative encounter state is refreshed on a 3-second polling loop. Rail UX must tolerate delayed authoritative updates or add a clear optimistic/reconcile strategy for HP/actions/current turn.

## Sequencing note

- Treat the first deliverable as a participant view-model hardening pass plus rail rendering. Without that normalization, later token/readability work will duplicate state projection logic.
