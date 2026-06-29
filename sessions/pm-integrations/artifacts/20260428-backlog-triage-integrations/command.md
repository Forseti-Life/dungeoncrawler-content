- Agent: pm-integrations
- Status: pending
- Priority: P1
- Source backlog artifacts:
  - `dashboards/PROJECTS.md` (PROJ-010 current state / next step / queue status)
  - `dashboards/integrations/README.md`
  - `dashboards/integrations/integration-registry.yaml`
  - `dashboards/integrations/centralized-integration-management-plan.md`

# Backlog triage normalization — Integrations governance

The integrations project has active remediation and runtime-truth audit work described in project docs, but the lane still needs explicit inbox-driven triage/routing.

## Required action
1. Review the PROJ-010 backlog / next-step work that is currently captured in integration docs and project status text.
2. Convert each actionable backlog item into explicit routed inbox work for the integrations team or owning product team.
3. Ensure the first-wave remediation items become routed work, especially:
   - runtime truth audit across live config/env/token/workflow secret planes
   - SerpAPI tracked-secret remediation
   - product-team-specific fix routing for audited integrations
4. If any items are blocked on ownership or missing evidence, create explicit needs-info / blocked routing rather than leaving them in narrative backlog text.
5. Produce an outbox summary listing created inbox items and the routing owner for each remediation lane.

## Outcome target
Integrations governance backlog should be executable through inbox work items, not left in project or planning markdown.
