# Suite Activation: dc-ui-map-first-player-shell

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:42+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-ui-map-first-player-shell"`**  
   This links the test to the living requirements doc at `features/dc-ui-map-first-player-shell/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-ui-map-first-player-shell-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-ui-map-first-player-shell",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-ui-map-first-player-shell"`**  
   Example:
   ```json
   {
     "id": "dc-ui-map-first-player-shell-<route-slug>",
     "feature_id": "dc-ui-map-first-player-shell",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-ui-map-first-player-shell",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-ui-map-first-player-shell

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-13
**Feature:** Map-first player shell for `/hexmap`

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | Drupal functional/UI | Verify player-visible shell, debug gating, and board-first layout defaults |
| `role-url-audit` | HTTP role audit | Ensure map route still respects campaign ownership and does not leak debug surfaces to unauthorized roles |

---

## Test Cases

### TC-UI-SHELL-01 — Board-first layout is the default
- **Suite:** module-test-suite
- **Description:** Fresh player load shows the board as the primary visual region.
- **Expected:** board width dominates shell; supporting panels are secondary.

### TC-UI-SHELL-02 — Non-admin players do not see debug panels by default
- **Suite:** module-test-suite
- **Description:** Raw dungeon JSON/object-definition diagnostics are hidden in the default player shell.
- **Expected:** debug/info diagnostics absent or collapsed behind debug-only affordance for non-admin players.

### TC-UI-SHELL-03 — Authorized debug/admin mode can still access diagnostics
- **Suite:** module-test-suite
- **Description:** Admin/debug session can reveal diagnostics without breaking the player shell.
- **Expected:** diagnostics available only when explicitly enabled by authorized user.

### TC-UI-SHELL-04 — Exploration and encounter controls remain usable
- **Suite:** module-test-suite
- **Description:** Shell refactor does not break action bars, turn HUD, or combat controls.
- **Expected:** exploration actions, encounter actions, and turn HUD remain visible in their respective modes.

### TC-UI-SHELL-05 — Layout preference persists
- **Suite:** module-test-suite
- **Description:** User layout preference survives reload.
- **Expected:** saved shell preference is restored on next visit without altering server state.

### Acceptance criteria (reference)

# Acceptance Criteria: Map-First Player Shell
# Feature: dc-ui-map-first-player-shell

## AC-001: Board-first default layout
- Given a player loads `/hexmap`, when the page renders, then the map board is the visual primary surface and occupies the majority of desktop width by default
- Given the shell loads on desktop, when no saved preference exists, then the default layout targets a board-first ratio rather than an even split with side content

## AC-002: Debug surfaces are gated
- Given a non-admin player opens the map, when the shell renders, then developer/debug panels such as raw JSON state and object-definition diagnostics are hidden from the default play view
- Given an authorized debug/admin mode is enabled, when the shell renders, then the diagnostic panels can still be accessed without removing the underlying tools from the product

## AC-003: Core play controls remain anchored
- Given the game is in exploration mode, when the player is on the board, then exploration actions remain accessible without leaving the board
- Given the game enters encounter mode, when turn state changes, then combat controls and turn HUD remain visible and readable in the board shell

## AC-004: Supporting panels remain available
- Given character, inventory, quest, or chat tools are needed, when the player opens them, then they are reachable from the shell without navigating away from the active scene
- Given the player changes shell layout preferences, when they return later, then the presentation preference is restored without affecting gameplay state

## Security acceptance criteria

- Security AC exemption: presentation changes only. Existing campaign/session authorization and CSRF protections remain the source of truth for all gameplay mutations.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
- Agent: qa-dungeoncrawler
- Status: pending
