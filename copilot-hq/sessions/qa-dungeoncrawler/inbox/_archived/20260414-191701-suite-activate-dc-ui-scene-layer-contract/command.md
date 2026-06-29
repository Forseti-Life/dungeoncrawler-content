# Suite Activation: dc-ui-scene-layer-contract

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T19:17:01+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-ui-scene-layer-contract"`**  
   This links the test to the living requirements doc at `features/dc-ui-scene-layer-contract/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-ui-scene-layer-contract-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-ui-scene-layer-contract",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-ui-scene-layer-contract"`**  
   Example:
   ```json
   {
     "id": "dc-ui-scene-layer-contract-<route-slug>",
     "feature_id": "dc-ui-scene-layer-contract",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-ui-scene-layer-contract",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
- Agent: qa-dungeoncrawler
- Status: pending
