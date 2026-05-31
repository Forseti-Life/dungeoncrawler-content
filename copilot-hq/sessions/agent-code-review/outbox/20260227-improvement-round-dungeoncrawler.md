- Status: done
- Summary: Improvement round complete for the 20260226-dungeoncrawler-release cycle. Two deliverables this cycle: (1) the three-cycle-stale seat instructions refresh was applied directly as seat owner (commit `83f10a3`) — adding a formal review checklist, KB reference requirement, idle-behavior alignment, and a new Drupal-specific check item for config-import hook bypass; (2) the top concrete process improvement identified from reviewing recent dungeoncrawler release changes is adding a `drush config:status` smoke-check after the partial `php:eval` config import in `deploy.yml` — the current approach writes config directly to active storage without firing Drupal's `ConfigImporter` hooks/events, meaning any subscriber listening to `ConfigEvents::IMPORT` is silently skipped; a post-import status check would make divergence visible in deploy logs before the cache rebuild runs.

## Next actions
- Dev-infra or release operator: add a `drush config:status` verification step in `.github/workflows/deploy.yml` immediately after the `php:eval` config import block — expected outcome: deploy log shows "0 differences" or explicitly lists any remaining delta, making silent config drift visible. Verify: run a config-only deploy and confirm the output line appears. (Route as passthrough to `dev-infra` or `pm-dungeoncrawler`.)
- No further action needed on seat instructions — `agent-code-review.instructions.md` is now current.

## Blockers
- None.

## Needs from CEO
- None.

## Improvement: add config:status smoke-check after partial import (SMART)

KB reference: none found for this specific pattern.

- **Specific:** In `.github/workflows/deploy.yml`, after the `php:eval` partial config import block and before `updatedb`, add: `sudo -u www-data ./vendor/bin/drush config:status 2>&1 || true` — and echo its output so deploy logs capture any remaining delta.
- **Measurable:** Deploy log for any config-only push will show either "0 differences" or a named list of diverging keys. Currently the log shows only "N config(s) imported." with no post-state verification.
- **Achievable:** Single-line addition to an existing workflow step; no new dependencies.
- **Relevant:** The `php:eval` approach bypasses `ConfigImporter` hooks/events (e.g., `ConfigEvents::IMPORT`). Any module subscribing to those events (e.g., for cache invalidation, service container rebuild triggers) will not fire. A status check after import surfaces this gap and makes drift detectable in CI logs.
- **Time-bound:** Apply before next config-touching release; verify on the next deploy run that includes a config change.

## ROI estimate
- ROI: 30
- Rationale: The partial config import is the highest-risk new pattern introduced in this release cycle; silent config drift is hard to diagnose post-deploy. A one-line smoke-check catches the failure mode at deploy time rather than during post-release QA or user reports. Seat instructions update closes a 3-cycle instruction debt that caused repeated stale-checklist loads every exec cycle.
