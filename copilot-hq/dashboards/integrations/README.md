# Integrations Hub

- Project: `PROJ-010`
- Owner: `pm-integrations`
- Maintainer: `dev-integrations`
- Security review: `sec-analyst-integrations`
- Executive sponsor: `ceo-copilot-2`
- Status: `in_progress`
- Last updated: `2026-04-13`

## Start here

If you are setting up, auditing, or troubleshooting an external integration, start in this directory.

This is the canonical operator entry point for:

- what integrations exist
- where their config appears to live
- where secrets are supposed to live
- who owns the integration
- how to verify an integration is working

## Core documents

| Purpose | File |
| --- | --- |
| First-pass inventory of known integrations | `dashboards/integrations/server-integration-inventory-2026-04.md` |
| Centralization plan / operating model | `dashboards/integrations/centralized-integration-management-plan.md` |
| Machine-readable integration registry | `dashboards/integrations/integration-registry.yaml` |

## How to use this hub

### If you are onboarding or setting up a service

1. Open `integration-registry.yaml`
2. Find the integration by name or domain
3. Check:
   - `config_plane`
   - `secret_plane`
   - `setup_doc`
   - `verification_method`
4. If `setup_doc` is missing, use the inventory file plus the referenced code/runbook paths

### If you are auditing risk

Start with:

1. `server-integration-inventory-2026-04.md`
2. the `risk_level` fields in `integration-registry.yaml`
3. the centralization plan

### If you are standardizing storage

Use the policy direction in:

- `dashboards/integrations/centralized-integration-management-plan.md`

Current preferred rule:

- secrets belong in env vars, approved server-side secret files, or GitHub Actions secrets
- non-secret integration settings may live in sync config and registry metadata
- tracked secrets, ad hoc token files, and DB-only secret storage are migration targets

## Current priorities

### P0

1. Rotate and remove the tracked `serpapi_api_key`
2. Complete the runtime truth audit

### P1

1. Add per-integration setup runbooks under `runbooks/integrations/`
2. Confirm live owners for integrations currently marked `needs-owner-confirmation`

## Current gaps

- The registry is the first durable version, not the final truth.
- Some integrations are verified as config surfaces only, not confirmed live traffic.
- Runtime storage truth still needs a second-pass audit across active Drupal config, env vars, token-file consumers, and GitHub Actions workflows.

## Next step

Advance `PROJ-010` into the runtime truth audit so every integration has a confirmed live storage plane, not just a repo-visible one.
