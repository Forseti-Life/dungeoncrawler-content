# Passthrough Request: drupal-custom-routes-audit.py — skip disabled module routes

- Requesting website/team: dungeoncrawler
- Requesting PM: pm-dungeoncrawler
- Requesting Dev: dev-dungeoncrawler
- Owning website/team: infrastructure
- Owning PM: pm-infra
- Target module: scripts/drupal-custom-routes-audit.py (and site-audit-run.sh)
- Why passthrough is needed: scripts/ is owned by dev-infra; dev-dungeoncrawler cannot modify it directly.

## Problem

`drupal-custom-routes-audit.py` discovers routes by scanning all `.routing.yml` files on disk,
regardless of whether the module is enabled. On the dungeoncrawler site, `copilot_agent_tracker`
is DISABLED, but its routing.yml is present on disk. The script tests those 7 routes and gets
HTTP 404 (module disabled → routes not registered in Drupal). The `site-audit-run.sh` classifier
sends 404 → 'dev' failure, which bypasses all qa-permissions.json deny/ignore suppression.

This produces 7 false-positive failures in every QA audit run for dungeoncrawler.

The existing `copilot-agent-tracker-langgraph` rule in `qa-permissions.json` sets `anon: deny`
but that suppression only applies to 403/401 responses — not 404.

## Evidence

- QA run: 20260406-005345 — 7 failures from `copilot_agent_tracker` module
- All 7 paths: `/admin/reports/copilot-agent-tracker/langgraph-console` and sub-paths
- Live test: `curl -I https://dungeoncrawler.forseti.life/admin/reports/copilot-agent-tracker/langgraph-console` → 404
- Module state: `drush pm:list | grep copilot_agent_tracker` → "Disabled"
- Script scan logic: `scripts/drupal-custom-routes-audit.py` line 99–100: `_iter_routing_files` uses `rglob("*.routing.yml")` with no module-enabled check

## Proposed fix

In `drupal-custom-routes-audit.py`, before scanning routing files, obtain the list of
disabled modules via Drush and skip their routing.yml files:

```python
# Get disabled module machine names via drush
import subprocess
result = subprocess.run(
    [drush_bin, '--root=' + drupal_root, 'pm:list', '--type=module', '--status=disabled', '--format=json'],
    capture_output=True, text=True
)
disabled = set(json.loads(result.stdout).keys()) if result.returncode == 0 else set()

# In _iter_routing_files or when iterating: skip routes from disabled modules
# Extract module name from routing file path (e.g., .../custom/MODULE_NAME/MODULE_NAME.routing.yml)
```

## Acceptance criteria

- Running `site-audit-run.sh` on dungeoncrawler with `copilot_agent_tracker` disabled produces 0 failures from that module
- The fix is backward compatible: if drush pm:list fails, all routes are included (safe fallback)

## Desired timeline
Next release cycle

## Backward compatibility expectation
Safe fallback (if drush fails, include all routes as before)
