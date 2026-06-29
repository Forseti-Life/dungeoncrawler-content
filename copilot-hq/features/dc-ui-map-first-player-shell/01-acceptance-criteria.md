# Acceptance Criteria: Map-First Player Shell
# Feature: dc-ui-map-first-player-shell

## AC-001: Board-first default layout
- Given a player loads `/hexmap`, when the page renders, then the map board is the visual primary surface and occupies the majority of desktop width by default
- Given the shell loads on desktop, when no saved preference exists, then the default layout targets a board-first ratio rather than an even split with side content

## AC-002: Debug surfaces are gated
- Given a non-admin player opens the map, when the shell renders, then developer/debug panels such as raw JSON state and object-definition diagnostics are hidden from the default play view
- Given an authorized debug/admin mode is enabled, when the shell renders, then the diagnostic panels can still be accessed without removing the underlying tools from the product

## AC-003: Core play controls remain anchored
- Given the game is in exploration mode, when the player is on the board, then exploration actions remain accessible without leaving the board
- Given the game enters encounter mode, when turn state changes, then combat controls and turn HUD remain visible and readable in the board shell

## AC-004: Supporting panels remain available
- Given character, inventory, quest, or chat tools are needed, when the player opens them, then they are reachable from the shell without navigating away from the active scene
- Given the player changes shell layout preferences, when they return later, then the presentation preference is restored without affecting gameplay state

## Security acceptance criteria

- Security AC exemption: presentation changes only. Existing campaign/session authorization and CSRF protections remain the source of truth for all gameplay mutations.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
