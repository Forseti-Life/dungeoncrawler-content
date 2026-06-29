- Agent: pm-forseti
- Status: pending
- Priority: P1
- Source backlog artifacts:
  - `features/forseti-langgraph-console-admin/feature.md`
  - `features/forseti-financial-health-home/roadmap.md`
  - `dashboards/PROJECTS.md` (PROJ-001, PROJ-002, PROJ-008, PROJ-011 backlog / deferred / next-slice notes)

# Backlog triage normalization — Forseti

Forseti still has backlog and deferred work embedded in roadmap/project markdown instead of explicit inbox routing.

## Required action
1. Review the Forseti backlog work currently stranded in feature briefs and project docs.
2. Convert each still-actionable backlog item into explicit inbox routing for the correct seat:
   - BA grooming
   - PM scope decision
   - QA/project triage
   - release-slot planning
3. Specifically normalize:
   - `forseti-langgraph-console-admin` (currently `Status: backlog`)
   - `forseti-financial-health-home` Phase 2 / Phase 3 backlog items
   - any PROJ-002 QA-suite backlog or deferred work that still exists only as roadmap/project text
   - any PROJ-011 mesh next-slice items that are still only roadmap language
4. If a backlog note is obsolete because the work is already shipped or superseded, record that in PM outbox and do not leave it as silent backlog text.
5. Produce an outbox summary listing the routed inbox items created and any backlog entries explicitly parked or closed.

## Outcome target
Forseti backlog work should exist as inbox-driven triage/routing, not just as backlog paragraphs in feature or project markdown.
