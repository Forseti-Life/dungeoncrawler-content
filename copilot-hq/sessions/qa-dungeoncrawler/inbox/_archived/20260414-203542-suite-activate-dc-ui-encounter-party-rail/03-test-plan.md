# Test Plan: dc-ui-encounter-party-rail

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-13
**Feature:** Encounter and party rail

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | Drupal functional/UI | Verify encounter rail card rendering, turn updates, and selection behavior |
| `role-url-audit` | HTTP role audit | Confirm no unauthorized player-visible rail data leaks through page payloads |

---

## Test Cases

### TC-UI-RAIL-01 — Encounter rail shows participant cards
- **Suite:** module-test-suite
- **Description:** Encounter view renders cards with initiative, name, team, and HP state.
- **Expected:** thin list replaced or enhanced into participant card rail.

### TC-UI-RAIL-02 — Current turn is clearly emphasized
- **Suite:** module-test-suite
- **Description:** Active actor changes are visually obvious in the rail.
- **Expected:** current card highlighted and prior actor de-emphasized after turn advance.

### TC-UI-RAIL-03 — Rail updates after HP/defeat changes
- **Suite:** module-test-suite
- **Description:** Damage or defeat updates are reflected in the rail without manual refresh.
- **Expected:** HP/defeat indicators update after combat events.

### TC-UI-RAIL-04 — Card selection syncs with board/entity inspector
- **Suite:** module-test-suite
- **Description:** Clicking a card selects the corresponding entity and board state follows.
- **Expected:** selected actor is highlighted on-map and inspector reflects the same entity.

### TC-UI-RAIL-05 — Mobile/narrow view stays usable
- **Suite:** module-test-suite
- **Description:** Rail remains readable in compact layout.
- **Expected:** horizontal/compact mode renders without obscuring the board.
