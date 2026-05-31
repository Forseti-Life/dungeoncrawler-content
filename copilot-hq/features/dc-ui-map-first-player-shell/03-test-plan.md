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
