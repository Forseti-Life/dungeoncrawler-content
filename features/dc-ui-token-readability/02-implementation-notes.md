# Implementation Notes: dc-ui-token-readability

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- `RenderSystem`
- Entity render metadata supplied by `HexMapController`

## Candidate markers

- Team ring / fill
- HP bar
- Current-turn glow
- Selected-token outline
- Condition badges
- Interaction / quest marker

## Design notes

- Use iconography and color intentionally; avoid text-heavy markers on the board
- Token readability should degrade gracefully at low zoom
- Badge rendering should be decoupled from the detailed entity inspector

## Architectural gotchas

- Do not derive badge state from inspector DOM state. The badge layer needs its own normalized token presentation model sourced from ECS/server state.
- Current player-sheet hydration and selected-entity inspection are separate flows. Selection rings, current-turn glow, and token badges must clarify what is selected without overwriting the persistent player-character panel.
- Server state polling every 3 seconds means HP/condition badges can briefly lag after combat actions unless the runtime reconciles local action results with the next authoritative refresh.
- Hidden or GM-private flags must be filtered before badge rendering; token readability is a common place where unauthorized knowledge leaks by accident.

## Sequencing note

- Build token readability on top of the scene-layer contract and participant/token projection work, not as an isolated sprite overlay hack.
