# Acceptance Criteria: Hexmap Thin Client Runtime
# Feature: dc-ui-hexmap-thin-client

## AC-001: Hexmap is display-first, not rules-authoritative
- Given a player is using `/hexmap`, when they click, move, attack, interact, or navigate, then the client submits intent and renders server-confirmed results rather than deciding final gameplay outcomes locally
- Given authoritative gameplay state differs from the client’s transient expectation, when the server response arrives, then the client reconciles to backend state

## AC-002: Backend owns gameplay mutation categories
- Given combat lifecycle events occur, when they resolve, then initiative, HP/action changes, attack outcomes, and NPC turns are server-owned
- Given movement or interaction is attempted, when it resolves, then legality, world mutation, and room-state updates are server-owned
- Given room navigation or scene transition occurs, when the state changes, then the server returns the authoritative destination state instead of the client materializing rooms ad hoc

## AC-003: Client retains presentation-only responsibilities
- Given the board is rendered, when the player pans, zooms, hovers, selects, opens chat, or opens support panels, then those remain client-side presentation concerns
- Given chat/session views are used, when the player reads or posts messages, then the client acts as a UI shell over backend chat services

## AC-004: Runtime seams are decomposed
- Given `hexmap.js` is refactored, when the cleanup lands, then rendering, shell UI, chat adapter, state adapter, and API client responsibilities are separated clearly enough that gameplay rules are no longer interleaved with presentation code

## Security acceptance criteria

- Security AC exemption: this feature strengthens authority boundaries by moving gameplay validation and mutation to the backend.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
