- Status: done
- Completed: 2026-04-18T13:57:49Z

# Suite Activation: dc-ui-sidebar-drawers

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-18T13:36:00+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-ui-sidebar-drawers"`**  
   This links the test to the living requirements doc at `features/dc-ui-sidebar-drawers/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-ui-sidebar-drawers-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-ui-sidebar-drawers",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-ui-sidebar-drawers"`**  
   Example:
   ```json
   {
     "id": "dc-ui-sidebar-drawers-<route-slug>",
     "feature_id": "dc-ui-sidebar-drawers",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-ui-sidebar-drawers",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-ui-sidebar-drawers

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-13
**Feature:** Sidebar drawers and docked support panels

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | Drupal functional/UI | Verify drawer switching, preserved panel behavior, and responsive chat/drawer layout |
| `role-url-audit` | HTTP role audit | Confirm panel refactor does not widen access to character/inventory/quest/chat data |

---

## Test Cases

### TC-UI-DRAWER-01 — Only one primary drawer is open at a time
- **Suite:** module-test-suite
- **Description:** Character/inventory/quests/inspector drawers follow single-primary behavior.
- **Expected:** opening one closes the previous primary drawer.

### TC-UI-DRAWER-02 — Character, inventory, and quest content still works inside drawers
- **Suite:** module-test-suite
- **Description:** Existing panel content survives the shell change.
- **Expected:** core interactions and data loads remain intact.

### TC-UI-DRAWER-03 — Chat remains accessible without dominating board width
- **Suite:** module-test-suite
- **Description:** Chat uses docked/collapsible behavior in the new shell.
- **Expected:** chat is reachable and usable while board remains primary.

### TC-UI-DRAWER-04 — Narrow-screen overlay behavior is usable
- **Suite:** module-test-suite
- **Description:** Drawer model degrades cleanly on tablet/mobile sizes.
- **Expected:** slide-over/overlay drawer remains readable and dismissible.

### Acceptance criteria (reference)

# Acceptance Criteria: Sidebar Drawers and Docked Support Panels
# Feature: dc-ui-sidebar-drawers

## AC-001: Single-primary drawer model
- Given the player opens character, inventory, quests, or inspector, when the drawer system is active, then only one primary right-side drawer is expanded at a time
- Given the player switches drawers, when the new drawer opens, then the prior drawer collapses without losing state unexpectedly

## AC-002: Existing panel functionality is preserved
- Given the player opens character, inventory, or quest content, when the drawer renders, then the existing data and interactions still work inside the new shell
- Given the player opens the entity inspector, when an entity is selected, then inspection data remains available without navigating away from the board

## AC-003: Chat is docked without dominating the board
- Given room/story/party/system chat is used, when the player interacts with the chat area, then it behaves as a docked or collapsible support panel instead of forcing a full-width competing region

## AC-004: Responsive behavior is supported
- Given the shell is viewed on a narrow screen, when a drawer opens, then it degrades to an overlay/slide-over pattern that remains usable on tablet/mobile
- Given the player returns later, when the shell loads, then the last selected drawer may be restored if appropriate for the device size

## Security acceptance criteria

- Security AC exemption: drawer refactor changes presentation only; existing route/API authorization remains authoritative.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
