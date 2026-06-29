# Integration centralization plan

- Status: done
- Summary: Built the first centralization plan for `PROJ-010` so operators have a clear path toward one place to start for external integration setup and audit work. The plan is documented at `dashboards/integrations/centralized-integration-management-plan.md` and shifts the project from pure inventory into a concrete phased program: create one integration hub, add one machine-readable registry, standardize storage-plane rules, then complete a runtime truth audit and secret normalization pass.

## What changed

- Added `dashboards/integrations/centralized-integration-management-plan.md`
- Updated `dashboards/PROJECTS.md` so `PROJ-010` now references the centralization plan in its current state and next step

## Planned phase sequence

1. Create `dashboards/integrations/README.md` as the single operator entry point
2. Create `dashboards/integrations/integration-registry.yaml`
3. Audit runtime config planes (active Drupal config, env vars, token files, workflow secrets)
4. Normalize secret placement and remove tracked secrets from sync config

## Immediate priority

The plan explicitly puts the tracked SerpAPI secret in the P0 bucket alongside the hub README and integration registry.
