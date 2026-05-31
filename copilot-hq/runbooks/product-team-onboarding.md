# Product Team Onboarding Standard

This runbook is the standard process for adding a new product team to HQ automation.

## Source of truth

- Team registry: `org-chart/products/product-teams.json`
- Seat definitions: `org-chart/agents/agents.yaml`
- QA suite manifest: `qa-suites/products/<product>/suite.json`

## Required onboarding steps

1) Add seats in `org-chart/agents/agents.yaml`
- Required seat IDs per product:
  - `pm-<product>`
  - `dev-<product>`
  - `qa-<product>`
- Ensure supervisor/role/scope are valid.

2) Add product QA manifest
- Create `qa-suites/products/<product>/suite.json` from `qa-suites/products/_template/suite.json`.
- Replace placeholders with executable commands.

3) Add registry entry
- Add a new object under `teams[]` in `org-chart/products/product-teams.json`.
- Required fields:
  - `id`, `label`, `site`, `aliases`, `active`
  - `qa_agent`, `dev_agent`, `pm_agent`
  - `qa_suite_manifest`
  - `release_preflight_enabled`, `coordinated_release_default`
  - `site_audit` object

4) Optional: enable site audit automation
- If `site_audit.enabled=true`, also provide:
  - `site_audit.filter`
  - `site_audit.base_url_env`
  - `site_audit.drupal_web_root`
  - `site_audit.qa_artifacts_dir`
  - `site_audit.route_regex`
  - `site_audit.qa_permissions_path`
- Create the permissions file at `site_audit.qa_permissions_path`.

5) Validate and dry run
- Validate team standard:
  - `python3 scripts/product-team-standard-validate.py`
- Validate suite manifests:
  - `python3 scripts/qa-suite-validate.py`
- Dry-run release preflight queueing:
  - `scripts/release-cycle-start.sh <product-or-site-alias> <release-id>`
- Dry-run coordinated queueing:
  - `scripts/coordinated-release-cycle-start.sh <release-id>`

## Acceptance criteria

- Team appears in `product-teams.json` with valid seat references.
- QA suite manifest exists and has no placeholder command tokens.
- `release-cycle-start.sh` resolves the team by alias and queues the correct QA seat.
- If site audit is enabled, `site-audit-run.sh <filter-or-id>` runs for the new team without script edits.

## Notes

- This process intentionally makes automation config-driven. Adding new teams should not require hardcoding branches in scripts.
- Keep paused/non-release teams in the registry with `release_preflight_enabled=false`.