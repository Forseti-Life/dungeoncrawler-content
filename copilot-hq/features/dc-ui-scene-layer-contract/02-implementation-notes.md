# Implementation Notes: dc-ui-scene-layer-contract

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- `RenderSystem`
- `SpriteService`
- Scene/entity injection performed by `HexMapController`

## Target stack

1. Background art
2. Terrain/base hexes
3. Grid/measurement
4. Static props
5. Interactive props/hazards
6. Token shadows
7. Tokens/creatures
8. Token badges
9. Templates/overlays
10. Fog/lighting/weather FX
11. Interaction
12. Screen-space HUD

## Design notes

- Keep the layer contract documented close to the runtime code so later feature work does not reintroduce ad hoc z-ordering
- Preserve current container names where practical, but prefer a clearer ownership model over legacy naming

## Architectural gotchas

- `RenderSystem` is currently initialized with only `hex`, `object`, and `ui` layer references. Adding new scene layers is not just a Pixi container change; the render-system contract must be expanded in lockstep.
- World-space transforms are hardcoded in `setWorldPosition()` and `setWorldScale()`. Any new world layer must be wired into both functions or it will drift during pan/zoom.
- `objectContainer` is carrying more than one concern today (props, actors, and related world sprites). Splitting props/tokens/badges requires an explicit migration plan so existing render order is not broken.
- `HexMapController` currently hydrates `hexmapDungeonData`, launch character, and quest summary via `drupalSettings`. Background art, FX metadata, or richer scene descriptors should avoid ballooning initial bootstrap payloads; prefer scene-definition vs runtime-delta separation.

## Sequencing note

- Land the layer contract before token badges or background/FX polish. Otherwise every visual enhancement will keep inventing its own z-order workaround.
