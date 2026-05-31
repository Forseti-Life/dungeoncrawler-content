# Lesson: site-audit-run.sh per-site isolation (2026-02-28)

## Problem
`scripts/site-audit-run.sh` used `set -euo pipefail` and called `run_site()` without error isolation. A failure in one site's audit (timeout, drush error, crawl exception) caused the entire main loop to exit, silently skipping all remaining sites. Dungeoncrawler showed zero audit artifacts (`sessions/qa-dungeoncrawler/artifacts/auto-site-audit/` did not exist) because forseti.life was processed first; any prior failure aborted before the dungeoncrawler `run_site()` call.

## Root cause
`run_site` was called bare in the `while IFS=...` loop with no error trap. Under `set -e`, a non-zero exit immediately aborted the script without logging which site failed.

## Fix (commit e08368d9)
Wrapped each `run_site` call in an `if ... then ... else` block so failures are logged per-site (`ERROR: <filter> audit failed at <ts>`) and the loop continues to the next site.

## Diagnostic signal
- `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/` missing or empty while forseti artifacts exist.
- `sessions/qa-forseti/artifacts/auto-site-audit/latest` symlink absent (created inside `run_site`, never reached on failure).

## Prevention
- Every new site added to `product-teams.json` gets artifact visibility immediately because `run_site` failures no longer cascade.
- If a site's artifacts directory is empty, check the site-audit-loop log for per-site `ERROR:` lines.
