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
