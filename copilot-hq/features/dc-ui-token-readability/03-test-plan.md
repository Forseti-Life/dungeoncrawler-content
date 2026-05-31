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
