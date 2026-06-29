# Problem Statement (PM-owned)

## Context
- The Dungeoncrawler `/hexmap` UI modernization landed map-first shell and drawer work, but the older dedicated map-tab affordance no longer exists as an explicit tracked feature or user-facing shell destination.
- The Board wants the map tab recreated as a deliberate backlog item so the board remains an obvious, first-class destination inside the player shell instead of a state users only recover indirectly by closing support panels.

## Goals (Outcomes)
- Restore a clear `Map` destination within the `/hexmap` shell.
- Preserve the board-first presentation from the existing shell modernization.
- Standardize shell navigation around one canonical map/support state model.

## Non-Goals (Explicitly out of scope)
- Reworking combat, movement, or backend gameplay authority.
- Redesigning quest/chat/inventory data contracts beyond the shell-state seams required for navigation.
- Reopening shipped shell work that is unrelated to the map-tab affordance.

## Users / Personas
- Players navigating `/hexmap`
- GMs/admin users who also need rapid return-to-board behavior while using support panels

## Constraints
- Security: preserve existing access rules for campaign, inventory, quest, chat, and debug/admin data.
- Performance: switching to `Map` should remain a shell-state transition, not a heavyweight reload path.
- Accessibility: shell navigation must remain keyboard-usable and visually explicit.
- Backward compatibility (if applicable): do not preserve multiple competing navigation paradigms; migrate old persisted state to one canonical model.

## Success Metrics
- `Map` is visibly present in the shell navigation and is the default active destination.
- Returning from support surfaces to the board becomes explicit and predictable.
- PM can hand clean BA/Dev/QA slices from this feature without reopening ambiguity about where map-tab behavior belongs.

## Dependencies
- `dc-ui-map-first-player-shell`
- `dc-ui-sidebar-drawers`

## Risks
- Shell state remains split between Twig and `hexmap.js`, causing regressions or duplicate state logic.
- Persisted sidebar keys restore invalid/hidden states after the map tab is reintroduced.
- Panel-specific refresh behavior (especially inventory/chat) regresses if shell-state ownership moves without preserving current seams.

## Knowledgebase check
- Related lessons/playbooks (or 'none found'): none found
