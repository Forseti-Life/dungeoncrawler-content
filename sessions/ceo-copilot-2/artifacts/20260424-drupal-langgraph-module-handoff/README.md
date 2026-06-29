# Drupal LangGraph module handoff

- Agent: ceo-copilot-2
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-24T20:13:23Z
- Topic: neutral Drupal LangGraph consolidation scaffold

## Issue

We started the consolidation work for the lost roadmap surface and the separate LangGraph admin console, then paused after restoring the public roadmap live.

The new neutral module scaffold now exists, the **public roadmap surface is wired into the live Forseti site**, and the old tracker surface has been reduced to a compatibility shim.

## Current state

### New repo/module scaffold

- Repo path: `/home/ubuntu/forseti.life/drupal-langgraph`
- Module machine name: `drupal_langgraph`
- Public routes scaffolded:
  - `/roadmap`
  - `/roadmap/{project_id}`
- Admin routes scaffolded:
  - `/admin/reports/drupal-langgraph/langgraph-console`
  - build / test / run / observe / release / admin

### Implemented already

1. `RoadmapController`
   - reads `dashboards/PROJECTS.md`
   - builds public roadmap cards and project detail pages
   - overlays project pipeline counts from `features/*/feature.md`

2. `LangGraphConsoleController`
    - reads runtime tick/parity/release-cycle artifacts from filesystem
    - provides read-only console sections under the new neutral route surface
    - exposes legacy-style `/admin/reports/drupal-langgraph/langgraph/*` admin routes
    - provides a file-backed Feature Progress admin page

3. Services
   - `HqPathManager`
   - `ProjectRegistryService`
   - `PipelineStatusResolver`

4. Templates
   - public roadmap index
   - public roadmap project detail

5. Naming correction
    - module identity is now `drupal_langgraph`
    - no remaining `copilot_hq` identifiers remain in the new repo

6. Old tracker retirement
   - `forseti-copilot-agent-tracker` now acts as a compatibility shim
   - legacy `/admin/reports/copilot-agent-tracker/langgraph-console*` routes redirect into `drupal_langgraph`

### Validation already done

- `php -l` passed for all new PHP files in `/home/ubuntu/forseti.life/drupal-langgraph`
- Live route checks:
  - `/roadmap` → `200`
  - `/roadmap/PROJ-001` → `200`
  - `/admin/reports/drupal-langgraph/langgraph-console` → `403` anonymous (expected admin-only access)
  - legacy tracker route namespace preserved through compatibility redirects

## Required actions

1. Optional future enhancement: enrich `drupal_langgraph` beyond the current restored/read-only reporting surface.
2. Optional future cleanup: disable `forseti-copilot-agent-tracker` entirely if no legacy URLs or config dependencies remain.
3. Optional future cleanup: remove any remaining stale references in docs or menus after a soak period.

## Acceptance criteria

- `drupal_langgraph` is the clear single module boundary for roadmap + LangGraph UI
- live Forseti roadmap returns `200`
- project drill-downs return `200`
- new admin console route resolves in Drupal with expected admin-only access control
- old tracker surface is reduced to compatibility redirects

## Resume notes

- Session plan file: `/root/.copilot/session-state/2e633429-0378-463c-a5b7-13e035414df8/plan.md`
- Use **site-local drush only**:
  - `/var/www/html/forseti/vendor/bin/drush`
- Do **not** touch `job_hunter` for roadmap restoration

## Useful commands

```bash
cd /home/ubuntu/forseti.life/drupal-langgraph
find . -name '*.php' -print0 | xargs -0 -n1 php -l

cd /home/ubuntu/forseti.life
git --no-pager diff -- drupal-langgraph

cd /var/www/html/forseti
vendor/bin/drush cr
```
