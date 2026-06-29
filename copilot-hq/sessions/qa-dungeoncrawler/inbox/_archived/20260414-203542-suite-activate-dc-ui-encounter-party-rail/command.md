# Suite Activation: dc-ui-encounter-party-rail

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-ui-encounter-party-rail"`**  
   This links the test to the living requirements doc at `features/dc-ui-encounter-party-rail/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-ui-encounter-party-rail-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-ui-encounter-party-rail",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-ui-encounter-party-rail"`**  
   Example:
   ```json
   {
     "id": "dc-ui-encounter-party-rail-<route-slug>",
     "feature_id": "dc-ui-encounter-party-rail",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-ui-encounter-party-rail",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: Encounter and Party Rail
# Feature: dc-ui-encounter-party-rail

## AC-001: Initiative/party cards are information rich
- Given an encounter is active, when the rail renders, then each visible participant card includes name, initiative, team, HP state, and current-turn emphasis
- Given a participant has conditions or action/reaction state relevant to the turn, when the rail renders, then that status is surfaced in compact form

## AC-002: Rail stays synchronized with combat state
- Given turn order changes, when the active turn advances, then the rail updates immediately to highlight the new actor
- Given participant HP/defeat state changes, when combat state refreshes, then the rail reflects those changes without requiring the player to inspect the map token manually

## AC-003: Rail supports selection and responsiveness
- Given a player clicks a participant card, when the actor is selectable, then the corresponding entity is selected/highlighted on the board
- Given the game is viewed on narrow screens, when the rail renders, then it degrades to a compact horizontal or collapsed pattern rather than overwhelming the board

## AC-004: Player and NPC visibility rules are respected
- Given encounter participants include information that should remain hidden from players, when the player-facing rail renders, then only allowed information is shown

## Security acceptance criteria

- Security AC exemption: read-model presentation of already-authorized combat state only. No client-only state may override server combat authority.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
- Agent: qa-dungeoncrawler
- Status: pending
