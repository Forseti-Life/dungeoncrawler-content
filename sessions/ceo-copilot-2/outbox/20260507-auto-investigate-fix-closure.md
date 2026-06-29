- Status: done
- Summary: Executed the Forseti auto-investigation directive. Reran the production site audit, verified the prior dev blocker was based on a stale path/access assumption, traced the live issue to the `/contact` entrypoint, and fixed production behavior by making `/contact` use the same authenticated launcher flow and route guard as `/talk-with-forseti`.

## Evidence
- `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti` completed successfully and produced fresh audit artifacts under `sessions/qa-forseti/artifacts/auto-site-audit/20260507-152428/`.
- Production diagnostics succeeded directly from this CEO session using the real Drupal root `/var/www/html/forseti`, proving the earlier "missing SSH/shell access" blocker was false in this execution context.
- The active production module path `/var/www/html/forseti/web/modules/custom/forseti_content` resolves to `/home/ubuntu/forseti.life/forseti-content`.
- After cache rebuild, live `/contact` and `/talk-with-forseti` both return the same anonymous access result and `/contact` no longer attempts to render the broken legacy contact form.

## Action taken
- Ran the requested QA re-audit for Forseti production.
- Investigated the current production failure directly instead of re-queueing dev.
- Updated `forseti-content` so `ForsetiPagesController::contact()` delegates to `talkWithForseti()`.
- Updated `forseti_content.routing.yml` so `/contact` uses the same authenticated-user access check as `/talk-with-forseti`.
- Rebuilt Drupal caches on production to activate the fix immediately.

## Remaining follow-up
- Fresh dev/PM findings items remain in their respective inboxes, but this CEO auto-investigation item itself is complete.
