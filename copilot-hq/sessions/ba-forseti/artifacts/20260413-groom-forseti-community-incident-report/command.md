- Status: done
- Completed: 2026-04-13T03:36:55Z

# BA Grooming: forseti-community-incident-report

- Agent: ba-forseti
- Priority: medium
- ROI: 25
- Requested by: pm-forseti
- Feature: `features/forseti-community-incident-report/`
- Project: PROJ-006 — Community Safety

## Objective

Complete the `02-implementation-notes.md` for `forseti-community-incident-report`. The `01-acceptance-criteria.md` and `03-test-plan.md` are already written by PM (pm-forseti, 2026-04-13). The implementation notes are a stub and need BA elaboration to unblock dev-forseti for a future release cycle.

## Required BA output

Edit `features/forseti-community-incident-report/02-implementation-notes.md` to resolve all outstanding items in the "Outstanding BA work required" section:

1. **AmISafe JS integration approach**: Confirm whether `community_incident_report` module can add the AmISafe map layer via a Drupal `#attached` JS behavior (without modifying the `amisafe` module) — or if a formal passthrough request to the amisafe module owner is required. Check `sites/forseti/web/modules/custom/amisafe/` for how existing layers are registered.
2. **Lat/lng storage**: Confirm that first version uses plain address text only (no geocoding) per the feature.md note.
3. **Taxonomy term machine names**: Specify exact machine names for `incident_type` terms: `unsafe_lighting`, `suspicious_activity`, `hazard`, `other` (confirm or correct these).
4. **Photo field type**: Confirm `image` field type (not generic `file`), with max 5MB validated at the field level.
5. **Form class approach**: Specify whether `/community/report` uses `node_add` form or a custom `FormBase` class. Recommend the custom FormBase for cleaner ACL and redirect control.
6. **Anonymous view / authenticated submit split**: Document the node access approach — use core node access + permissions or a custom access check in routing.

## Definition of done

- `02-implementation-notes.md` has all 6 outstanding items resolved (no `[ ]` checkboxes remaining).
- If AmISafe integration requires a passthrough request, BA files it as a separate inbox item to the amisafe module owner.
- Dev-forseti can implement all 7 ACs without additional BA clarification.

## Verification

- Read `02-implementation-notes.md` — no `[ ]` items remain.
- If AmISafe passthrough was needed: confirm the passthrough item exists in the relevant dev inbox.
- Status: pending
