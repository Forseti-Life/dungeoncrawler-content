# Test Plan: dc-ui-scene-layer-contract

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-13
**Feature:** Scene layer contract

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | Frontend/runtime verification | Verify the layer contract initializes correctly and preserves board/HUD behavior under pan/zoom |
| `role-url-audit` | HTTP role audit | Ensure scene payload changes do not leak unauthorized data fields |

---

## Test Cases

### TC-UI-LAYER-01 — Scene stack initializes with expected containers
- **Suite:** module-test-suite
- **Description:** Required layer containers are created in deterministic order.
- **Expected:** background/terrain/grid/props/tokens/overlays/fx/interaction/hud layers exist.

### TC-UI-LAYER-02 — HUD remains screen-space during pan/zoom
- **Suite:** module-test-suite
- **Description:** Board transforms do not move the HUD with the world.
- **Expected:** HUD remains fixed and readable while world layers pan/zoom.

### TC-UI-LAYER-03 — Background and FX render behind tokens
- **Suite:** module-test-suite
- **Description:** Background art and effects occupy the intended layers.
- **Expected:** tokens and overlays stay legible above background/terrain layers.

### TC-UI-LAYER-04 — Overlay/template rendering does not block interaction
- **Suite:** module-test-suite
- **Description:** Range/highlight overlays coexist with selection and interaction affordances.
- **Expected:** player can still select and inspect entities/hexes with overlays active.
