# Acceptance Criteria: Token Readability and Status Badges
# Feature: dc-ui-token-readability

## AC-001: Core tactical state is visible on-map
- Given a creature token is visible, when the board renders, then the player can distinguish team/allegiance, current selection, and active-turn emphasis directly on the token
- Given HP state is relevant, when the token renders, then a compact health indicator is visible without opening the inspector

## AC-002: Conditions and markers are surfaced compactly
- Given a token has active conditions, when the board renders, then those conditions are shown through compact icons/badges rather than requiring full inspection
- Given a token or object is quest-relevant or interactable, when the board renders, then a clear marker indicates that state

## AC-003: Presentation scales with zoom/readability
- Given the player zooms out, when token badges render, then they simplify or collapse to avoid unreadable clutter
- Given the player zooms in, when token badges render, then richer badge detail may appear without obscuring the token itself

## AC-004: Visibility rules are preserved
- Given hidden/private state exists for an entity, when player-facing tokens render, then that hidden information is not exposed through badges or rings

## Security acceptance criteria

- Security AC exemption: token badges are a read-only presentation of already-authorized gameplay state.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
