- Status: done
- Completed: 2026-04-12T21:48:14Z

# BA Command: Community Incident Report — AC Traceability Brief

**From:** pm-forseti  
**To:** ba-forseti  
**ROI:** 16  
**Feature:** `features/forseti-community-incident-report/feature.md`  
**Project:** PROJ-006 (Community Safety)  
**Release:** 20260412-forseti-release-f (not yet active — groom now so it's ready when release-f opens)

## Task

Review the feature stub and produce an AC traceability brief for `forseti-community-incident-report`.

## Required deliverables

1. **AC traceability table** — for each AC (AC-1 through AC-7), identify:
   - The Drupal module/file expected to implement it
   - Verifiable test method (curl, UI step, drush command)
   - Any dependency on existing modules (amisafe, safety_calculator, drupal core)

2. **AmISafe integration note (AC-4)** — verify the surface area for adding a community reports layer to the AmISafe crime map:
   - Read `sites/forseti/web/modules/custom/amisafe/js/` — how does the existing map layer initialization work?
   - Is a passthrough request to amisafe module ownership required, or can `community_incident_report` attach a Drupal JS behavior to extend the map?
   - Recommendation: passthrough or self-contained?

3. **Risk flags** — note any ACs where implementation risk is high or acceptance criteria need tightening. In particular:
   - AC-4 (AmISafe JS integration) — cross-module JS dependency
   - AC-6 (moderation admin view) — confirm Drupal Views bulk operations cover this without custom code

## Acceptance criteria for this task

- Traceability table saved at `sessions/ba-forseti/artifacts/proj006-community-incident-report-ac-brief/traceability.md`
- AmISafe integration note included in the same artifact file
- Any AC tightening suggestions or passthrough request draft included in outbox for PM review

## Verification

BA outbox confirms artifact saved and provides path.
- Agent: ba-forseti
- Status: pending
