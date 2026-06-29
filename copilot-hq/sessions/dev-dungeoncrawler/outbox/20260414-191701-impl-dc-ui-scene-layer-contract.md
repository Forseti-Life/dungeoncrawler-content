- Status: done
- Summary: Implemented dc-ui-scene-layer-contract in hexmap.js. Added three new world-space containers to the PIXI scene graph: backgroundContainer (zIndex 5, room background art/atmosphere), propsContainer (zIndex 25, static scene props between grid and tokens), and fxContainer (zIndex 35, fog/lighting/effects between tokens and overlays). Updated setWorldPosition and setWorldScale to include all new containers. Full layer stack documented inline: background(5) → terrain/hexes(10) → grid(20) → props(25) → tokens(30) → fx(35) → overlays/ui(40) → interaction(45) → HUD/screen-space(50). HUD remains screen-space and unaffected by pan/zoom. Commit 16ea1ec50.

## Acceptance criteria verification
- AC-001: Layer stack now includes explicit layers for background, terrain, grid, props, tokens, fx, overlays, interaction, HUD ✓
- AC-002: World-space layers (z 5–45) all updated in setWorldPosition/setWorldScale; hudContainer (z 50) unchanged ✓
- AC-003: backgroundContainer provides slot for room art; fxContainer provides dedicated slot for fog/lighting/effects ✓
- AC-004: Layer contract documented in inline comment table; zIndex values are explicit and non-overlapping ✓

## Commit
- 16ea1ec50 (forseti.life repo)

## Rollback
- Revert adds/changes to initPixiApp, setWorldPosition, setWorldScale in hexmap.js
- Containers backgroundContainer, propsContainer, fxContainer are additive; removing them restores prior 5-layer state

## ROI estimate
- ROI: 70
- Rationale: Foundational architecture for all future UI polish; dc-ui-hexmap-thin-client and dc-ui-encounter-party-rail both depend on stable layer contract.
