# Acceptance Criteria: Encounter and Party Rail
# Feature: dc-ui-encounter-party-rail

## AC-001: Initiative/party cards are information rich
- Given an encounter is active, when the rail renders, then each visible participant card includes name, initiative, team, HP state, and current-turn emphasis
- Given a participant has conditions or action/reaction state relevant to the turn, when the rail renders, then that status is surfaced in compact form

## AC-002: Rail stays synchronized with combat state
- Given turn order changes, when the active turn advances, then the rail updates immediately to highlight the new actor
- Given participant HP/defeat state changes, when combat state refreshes, then the rail reflects those changes without requiring the player to inspect the map token manually

## AC-003: Rail supports selection and responsiveness
- Given a player clicks a participant card, when the actor is selectable, then the corresponding entity is selected/highlighted on the board
- Given the game is viewed on narrow screens, when the rail renders, then it degrades to a compact horizontal or collapsed pattern rather than overwhelming the board

## AC-004: Player and NPC visibility rules are respected
- Given encounter participants include information that should remain hidden from players, when the player-facing rail renders, then only allowed information is shown

## Security acceptance criteria

- Security AC exemption: read-model presentation of already-authorized combat state only. No client-only state may override server combat authority.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
