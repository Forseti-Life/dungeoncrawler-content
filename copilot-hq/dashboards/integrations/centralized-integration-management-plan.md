# Centralized Integration Management Plan

- Project: `PROJ-010`
- Owner: `architect-copilot`
- Managing lead: `ceo-copilot-2`
- Status: `proposed`
- Last updated: `2026-04-13`

## Problem

Integration configuration is currently spread across multiple places:

- Drupal config sync
- active Drupal config / admin UI
- environment variables
- local token files
- GitHub Actions secrets
- runbooks and session artifacts

That makes setup fragile. A new operator has no single obvious place to start, and the system does not yet enforce one standard for where secret versus non-secret integration config should live.

## Goal

Create a single, durable integration-management entry point so a user setting up or auditing the system can answer:

1. What integrations exist?
2. Where is each one configured?
3. Where should its secrets live?
4. Who owns it?
5. How do I verify it is working?

## Guiding principle

**Centralize the operator experience first, then centralize the storage model.**

This means the first deliverable is not a new UI. It is a single source of truth that points users to the right place every time, while the underlying storage model is progressively normalized.

## Target operating model

## 1. Single operator entry point

Create one canonical integration hub under:

- `dashboards/integrations/`

Planned entry file:

- `dashboards/integrations/README.md`

That README should become the answer to: **"Where do I go to set up or audit an integration?"**

It should link to:

- the integration inventory
- the integration registry
- the storage policy
- setup/runbooks by service
- validation commands
- known risks / blocked items

## 2. Single integration registry

Add a durable machine-readable registry:

- `dashboards/integrations/integration-registry.yaml`

Each integration entry should include:

- `id`
- `name`
- `domain`
- `purpose`
- `owner`
- `system_scope`
- `code_paths`
- `config_plane`
- `secret_plane`
- `setup_doc`
- `verification_method`
- `status`
- `risk_level`

This becomes the canonical list of integrations and their expected storage plane.

## 3. Standard storage-plane policy

The org should adopt one simple rule set:

### Secrets

Secrets should live in one of these approved planes only:

1. server environment variables
2. server-side secret files outside git-tracked config
3. GitHub Actions secrets for workflow-only needs

### Non-secret integration settings

Non-secret values may live in:

1. Drupal config sync
2. registry metadata in `copilot-hq`

### Transitional / legacy

These should be treated as migration targets, not preferred endpoints:

- active Drupal DB config holding secrets
- ad hoc token files with unclear ownership
- tracked config sync secrets

## 4. Setup runbooks by integration

Each production-relevant integration should have one setup/audit runbook under:

- `runbooks/integrations/<integration-id>.md`

Minimum sections:

- purpose
- required credentials
- approved storage plane
- setup steps
- verification command
- failure modes
- rotation / change procedure

## 5. Verification-first setup flow

Every integration should have a standard "prove it works" command or URL.

Examples:

- GitHub billing: `gh api` usage endpoint call
- AWS billing: `aws ce get-cost-and-usage`
- Bedrock: application-level test or drush/runtime verification
- SerpAPI: controlled search call

Users should never be left with "config saved" as the only success signal.

## 6. Later admin UX

After the registry and storage policy are in place, the org can consider a read-only internal admin page that shows:

- integrations by status
- current config plane
- last verification time
- owner
- blockers

That UI should **not** expose secret values.

## Recommended content layout

```text
dashboards/integrations/
  README.md
  server-integration-inventory-2026-04.md
  centralized-integration-management-plan.md
  integration-registry.yaml
  runtime-config-audit-2026-04.md

runbooks/integrations/
  aws-bedrock.md
  aws-billing.md
  github-billing.md
  github-deploy.md
  serpapi.md
  google-social-auth.md
  recaptcha.md
  neris.md
```

## Phase plan

## Phase 1 — central entry point and registry

Goal: make setup navigable.

Deliverables:

1. `dashboards/integrations/README.md`
2. `dashboards/integrations/integration-registry.yaml`
3. `runbooks/integrations/` directory with initial high-priority runbooks
4. explicit storage-plane terminology and glossary

Success condition:

- a new operator can find every known integration from one starting file

## Phase 2 — runtime truth audit

Goal: close the gap between repo-visible config and live server reality.

Deliverables:

1. active Drupal config audit
2. environment variable inventory
3. token-file consumer inventory
4. GitHub Actions secret usage map

Success condition:

- every integration in the registry has both a documented config plane and a confirmed live storage plane

## Phase 3 — secret normalization

Goal: reduce the number of secret storage planes.

Priority actions:

1. remove tracked secrets from config sync
2. move server-side secrets to approved planes
3. mark legacy token files as either:
   - retained with explicit ownership and scope
   - or migrated off

Success condition:

- no git-tracked sync config contains live secrets
- each integration uses an approved storage pattern

## Phase 4 — operator UX and governance

Goal: make long-term upkeep obvious and auditable.

Deliverables:

1. owner matrix per integration
2. verification cadence
3. secret rotation guidance
4. optional internal admin dashboard for read-only visibility

Success condition:

- CEO can delegate setup/audit work without rediscovering where config lives

## Immediate priorities

## P0

1. Rotate and remove the tracked `serpapi_api_key`
2. Create the integration registry
3. Create the operator entry-point README

## P1

1. Audit live Drupal active config for all verified integrations
2. Audit `/home/ubuntu/github.token` consumers
3. Audit GitHub Actions workflows that depend on secrets

## P2

1. Write per-integration setup runbooks
2. Define secret placement standard in durable policy language
3. Decide whether a read-only Drupal integration admin view is worth building

## Recommended next action

Use `PROJ-010` to drive Phase 1 immediately:

1. add the integration hub README
2. add the machine-readable registry
3. dispatch the runtime truth audit as the next CEO-managed work item
