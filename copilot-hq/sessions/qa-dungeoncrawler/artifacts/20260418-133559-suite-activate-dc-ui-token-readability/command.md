- Status: done
- Completed: 2026-04-18T13:44:18Z

# Suite Activation: dc-ui-token-readability

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-ui-token-readability"`**  
   This links the test to the living requirements doc at `features/dc-ui-token-readability/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-ui-token-readability-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-ui-token-readability",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-ui-token-readability"`**  
   Example:
   ```json
   {
     "id": "dc-ui-token-readability-<route-slug>",
     "feature_id": "dc-ui-token-readability",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-ui-token-readability",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-ui-token-readability

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-13
**Feature:** Token readability and status badges

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | Frontend/runtime verification | Verify token badges, selection emphasis, HP markers, and zoom behavior |
| `role-url-audit` | HTTP role audit | Ensure hidden/private entity data is not leaked in player payloads |

---

## Test Cases

### TC-UI-TOKEN-01 — Team and selection state are visible on-map
- **Suite:** module-test-suite
- **Description:** Player can distinguish allied, hostile, and selected tokens without opening inspector.
- **Expected:** rings/outlines/selection cues render on visible tokens.

### TC-UI-TOKEN-02 — HP state is legible on-map
- **Suite:** module-test-suite
- **Description:** Damage state changes are reflected in token health indicators.
- **Expected:** HP marker updates after combat damage/healing events.

### TC-UI-TOKEN-03 — Condition badges render for conditioned tokens
- **Suite:** module-test-suite
- **Description:** Tokens with conditions expose compact indicators.
- **Expected:** condition badges/icons render without obscuring the token body.

### TC-UI-TOKEN-04 — Badge density adapts to zoom
- **Suite:** module-test-suite
- **Description:** Zooming out reduces clutter while preserving key state.
- **Expected:** badge presentation simplifies at low zoom and becomes richer at closer zoom.

### TC-UI-TOKEN-05 — Hidden/private state is not leaked
- **Suite:** role-url-audit
- **Description:** Player payload and rendered badges do not reveal GM-only/private token state.
- **Expected:** no unauthorized hidden-state indicators appear in player view.

### Acceptance criteria (reference)

# Acceptance Criteria: Token Readability and Status Badges
# Feature: dc-ui-token-readability

## AC-001: Core tactical state is visible on-map
- Given a creature token is visible, when the board renders, then the player can distinguish team/allegiance, current selection, and active-turn emphasis directly on the token
- Given HP state is relevant, when the token renders, then a compact health indicator is visible without opening the inspector

## AC-002: Conditions and markers are surfaced compactly
- Given a token has active conditions, when the board renders, then those conditions are shown through compact icons/badges rather than requiring full inspection
- Given a token or object is quest-relevant or interactable, when the board renders, then a clear marker indicates that state

## AC-003: Presentation scales with zoom/readability
- Given the player zooms out, when token badges render, then they simplify or collapse to avoid unreadable clutter
- Given the player zooms in, when token badges render, then richer badge detail may appear without obscuring the token itself

## AC-004: Visibility rules are preserved
- Given hidden/private state exists for an entity, when player-facing tokens render, then that hidden information is not exposed through badges or rings

## Security acceptance criteria

- Security AC exemption: token badges are a read-only presentation of already-authorized gameplay state.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
