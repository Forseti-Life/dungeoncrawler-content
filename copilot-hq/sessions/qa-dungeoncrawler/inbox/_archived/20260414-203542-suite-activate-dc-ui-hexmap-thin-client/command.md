# Suite Activation: dc-ui-hexmap-thin-client

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-ui-hexmap-thin-client"`**  
   This links the test to the living requirements doc at `features/dc-ui-hexmap-thin-client/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-ui-hexmap-thin-client-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-ui-hexmap-thin-client",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-ui-hexmap-thin-client"`**  
   Example:
   ```json
   {
     "id": "dc-ui-hexmap-thin-client-<route-slug>",
     "feature_id": "dc-ui-hexmap-thin-client",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-ui-hexmap-thin-client",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: Hexmap Thin Client Runtime
# Feature: dc-ui-hexmap-thin-client

## AC-001: Hexmap is display-first, not rules-authoritative
- Given a player is using `/hexmap`, when they click, move, attack, interact, or navigate, then the client submits intent and renders server-confirmed results rather than deciding final gameplay outcomes locally
- Given authoritative gameplay state differs from the client’s transient expectation, when the server response arrives, then the client reconciles to backend state

## AC-002: Backend owns gameplay mutation categories
- Given combat lifecycle events occur, when they resolve, then initiative, HP/action changes, attack outcomes, and NPC turns are server-owned
- Given movement or interaction is attempted, when it resolves, then legality, world mutation, and room-state updates are server-owned
- Given room navigation or scene transition occurs, when the state changes, then the server returns the authoritative destination state instead of the client materializing rooms ad hoc

## AC-003: Client retains presentation-only responsibilities
- Given the board is rendered, when the player pans, zooms, hovers, selects, opens chat, or opens support panels, then those remain client-side presentation concerns
- Given chat/session views are used, when the player reads or posts messages, then the client acts as a UI shell over backend chat services

## AC-004: Runtime seams are decomposed
- Given `hexmap.js` is refactored, when the cleanup lands, then rendering, shell UI, chat adapter, state adapter, and API client responsibilities are separated clearly enough that gameplay rules are no longer interleaved with presentation code

## Security acceptance criteria

- Security AC exemption: this feature strengthens authority boundaries by moving gameplay validation and mutation to the backend.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
- Agent: qa-dungeoncrawler
- Status: pending
