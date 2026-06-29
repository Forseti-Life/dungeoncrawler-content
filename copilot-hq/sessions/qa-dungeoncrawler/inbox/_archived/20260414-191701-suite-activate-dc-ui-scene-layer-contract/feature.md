# Feature Brief: Scene Layer Contract

- Work item id: dc-ui-scene-layer-contract
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: unscoped
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-tactical-grid, dc-cr-exploration-mode, dc-cr-encounter-rules
- Source: 2026-04-13 Dungeoncrawler UI architecture audit
- Category: ui-system
- Created: 2026-04-13

## Goal

Formalize the Pixi scene graph into a documented, deterministic layer contract for background art, terrain, props, tokens, overlays, fog/lighting, effects, interaction, and HUD. This creates a stable rendering architecture for future UI polish instead of continuing to accumulate ad hoc containers and z-index rules.

## Source reference

The current map runtime already has separate containers for `hex`, `grid`, `object`, `ui`, `interaction`, and `hud`, but it does not yet define a full visual contract for background mood art, token badges, scene effects, or future animation layers. The next stage of Dungeoncrawler UX needs that contract before more polish work lands.

## Implementation hint

Use Pixi container boundaries as the architecture boundary and keep axial/cube hex logic as the gameplay source of truth. Expand the current layer model into an explicit stack with clear ownership, screen-space vs world-space rules, and safe extension points for lighting/weather/effect overlays.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: scene layer refactor must preserve existing visibility and room/campaign access boundaries
- CSRF expectations: render-layer work must not bypass existing secured mutation routes for state changes
- Input validation: client render layers are derived from validated gameplay state and may not create hidden-authority mutations
- PII/logging constraints: visual layers do not add new stored personal data; debug logging for render state should remain disabled by default

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
