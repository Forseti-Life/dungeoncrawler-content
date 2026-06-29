# Test Plan: dc-ui-hexmap-thin-client

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-13
**Feature:** Hexmap thin client runtime

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | Integration/runtime | Verify the client submits intent and reconciles to server state for combat, movement, interaction, and navigation |
| `role-url-audit` | HTTP role audit | Confirm all gameplay mutations remain server-protected and no client-only mutation paths survive |

---

## Test Cases

### TC-THIN-01 — Combat remains server-authoritative
- **Suite:** module-test-suite
- **Description:** Start combat, attack, and end turn all reconcile from backend response/state sync.
- **Expected:** client renders server-confirmed initiative/order/results; no local-only authority path.

### TC-THIN-02 — Movement/interaction use backend validation
- **Suite:** module-test-suite
- **Description:** Movement and interaction requests are submitted as intent and applied only from server-confirmed state.
- **Expected:** invalid or blocked actions do not mutate client world state without backend confirmation.

### TC-THIN-03 — Room navigation is backend-authored
- **Suite:** module-test-suite
- **Description:** Crossing to a new room uses a backend-authored scene transition contract.
- **Expected:** client consumes returned room/scene state rather than building the destination ad hoc.

### TC-THIN-04 — Character sheet state remains stable while inspecting entities
- **Suite:** module-test-suite
- **Description:** Inspecting NPCs/objects does not corrupt the active player sheet.
- **Expected:** player-character panel and inspected-entity panel use separate, stable backend-fed models.

### TC-THIN-05 — Chat remains usable after runtime cleanup
- **Suite:** module-test-suite
- **Description:** Room chat and session-view chat continue working after `hexmap.js` decomposition.
- **Expected:** room, narrative, party, GM-private, and system-log flows still function.
