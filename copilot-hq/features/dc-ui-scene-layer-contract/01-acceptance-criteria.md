# Acceptance Criteria: Scene Layer Contract
# Feature: dc-ui-scene-layer-contract

## AC-001: Explicit layer stack exists
- Given the map runtime initializes, when scene containers are created, then the render stack includes explicit layers for background art, terrain/base hexes, grid/measurement, props, tokens, overlays/templates, fog/lighting/effects, interaction, and HUD

## AC-002: Screen-space vs world-space behavior is defined
- Given the player pans or zooms, when the board transforms, then world-space layers move with the map while HUD/shell layers remain screen-space and readable

## AC-003: Background and FX hooks are supported
- Given a room or map provides background art or atmosphere metadata, when the scene renders, then the architecture provides a supported place for those assets without colliding with tokens or overlays
- Given future move/attack/fog reveal effects are added, when they render, then they have a dedicated effects layer rather than borrowing unrelated UI containers

## AC-004: Contract is deterministic and extendable
- Given new scene elements are introduced, when devs add them, then the correct target layer and z-order rules are clear from the contract

## Security acceptance criteria

- Security AC exemption: render-layer contract only. Existing authoritative gameplay and access checks remain unchanged.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
