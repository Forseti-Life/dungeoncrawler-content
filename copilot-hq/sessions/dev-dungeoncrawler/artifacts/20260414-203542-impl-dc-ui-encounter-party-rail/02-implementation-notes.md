# Implementation Notes: dc-ui-encounter-party-rail

## Commit
`941ce1c26`

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/css/hexmap.css`

## What was done

### JS — `UIManager` in `hexmap.js`
- Added `setupPartyRailHandlers()` — called from constructor after `cacheElements()`; sets delegated click + keyboard (Enter/Space) listeners on the `#initiative-list` element. Clicking a `.rail-card` calls `hexmap.selectEntity(entity)` via `stateManager.hexmap` reference.
- Replaced `updateInitiativeTracker()` (8 lines of minimal HTML) with a full rich-card renderer (~60 lines):
  - **Header row**: initiative badge, name, team badge
  - **HP bar**: colour-state track (healthy/bloodied/critical/defeated); exact HP values (X/Y) shown only for `team === 'player'` — AC-004 visibility rule
  - **Action pips**: rendered only for active combatant (`isCurrent`); reads `ActionsComponent.actionsRemaining / maxActions / hasReaction`; reaction pip shown as "R"
  - Cards carry `data-entity-id` and `role=button` for click-to-select and accessibility

### CSS — `hexmap.css`
- Replaced old `.initiative-item` / `.init-value` / `.init-name` block (~50 lines) with:
  - `.rail-card` block (hover, active, defeated states)
  - `.rail-card__header`, `.rail-card__init`, `.rail-card__name`, `.rail-card__team-badge`
  - Team-coloured badge variants: `--player` (blue), `--ally` (green), `--enemy` (red), `--neutral` (gray)
  - `.rail-card__hp-wrap / __hp-track / __hp-bar / __hp-label` for HP visualization
  - `.rail-card__actions` and `.rail-card__pip` family for action pip row
  - `@media (max-width: 600px)`: list becomes horizontal flex row; cards go compact (80–120px); team badge and HP label hidden

## AC coverage
- AC-001 ✅ Cards show name, initiative, team, HP state, current-turn emphasis, action state
- AC-002 ✅ updateInitiativeTracker() is already called by EncounterPhaseHandler on every state change; no call-site changes needed
- AC-003 ✅ Click/keyboard handlers select entity on board; responsive narrow strip at ≤600px
- AC-004 ✅ Enemy/NPC HP shown as colour-state only; exact values only for player team

## Security
- Server remains authoritative; client selection is read-model only (no combat state mutation)
- No new PII surfaced
