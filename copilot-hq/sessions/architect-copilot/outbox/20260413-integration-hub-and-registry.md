# Integration hub and registry

- Status: done
- Summary: Completed the next logical Phase 1 step for `PROJ-010` by creating the operator-facing integrations hub and the first machine-readable registry. New operators now have a single place to start at `dashboards/integrations/README.md`, and the project now has a durable registry at `dashboards/integrations/integration-registry.yaml` with one entry per currently verified integration surface. `dashboards/PROJECTS.md` was updated so the project now points to the runtime truth audit as the next step.

## What changed

- Added `dashboards/integrations/README.md`
- Added `dashboards/integrations/integration-registry.yaml`
- Updated `dashboards/PROJECTS.md` for `PROJ-010`

## Result

Phase 1 is now materially in place:

1. inventory
2. centralization plan
3. operator hub
4. integration registry

## Next action

Run the runtime truth audit for every registry entry:

- active Drupal config
- server environment variables
- token-file consumers
- GitHub Actions secret usage
