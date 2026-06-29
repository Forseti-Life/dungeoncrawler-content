# Site Instructions: integrations

## Authority
- Primary owner: `pm-integrations`
- Methodology owner: `ceo-copilot-2`

## Mission
Own PROJ-010: inventory, govern, and harden how the org stores, resolves, verifies, and remediates configuration for external integrations used across product and operational systems.

## Applies to
All seats with `website_scope: ["integrations"]`.

## Team lanes
- PM: `pm-integrations`
- BA: `ba-integrations`
- Dev: `dev-integrations`
- QA: `qa-integrations`
- Security: `sec-analyst-integrations`

## Scope
- Integration inventory, ownership assignment, setup/runbook clarity, storage-plane governance, runtime truth audits, and remediation coordination.
- Canonical hub docs in `dashboards/integrations/`.
- Cross-product integration policy and verification methods.

## Out of scope
- Product feature ownership for the business behavior of an integration once requirements are clear.
- Whole-site QA or UX audits.
- Release management for product releases outside integration-governance work.

## Environment model
- No dedicated web surface.
- Validation is operator-audit and artifact-driven: registry checks, config inspection, runbook execution, and targeted remediation evidence.

## Key paths
| Resource | Path |
|---|---|
| Project hub | `dashboards/integrations/README.md` |
| Registry | `dashboards/integrations/integration-registry.yaml` |
| Inventory | `dashboards/integrations/server-integration-inventory-2026-04.md` |
| Centralization plan | `dashboards/integrations/centralized-integration-management-plan.md` |
| Runbooks | `runbooks/integrations/` |

## Ownership rule
- The integrations team owns governance and truth-maintenance for external-service configuration.
- When a fix belongs to product code or product config, route execution to the owning product or infra team; do not absorb unrelated product backlog permanently.

## Verification posture
- Prefer reproducible commands, explicit config-plane mapping, and named owners for every registry entry.
- Secret-bearing integrations must have a documented secret plane and a safe verification path.
