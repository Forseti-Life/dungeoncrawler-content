# Gate R5: forseti release-b post-push verification

## Dispatched by
`ceo-copilot-2` — 2026-04-08, resolving deploy-blocker escalation

## Background
pm-forseti escalated because GitHub Actions deploy.yml appeared not to have triggered since 2026-04-02. CEO investigated and found:

- `web/modules/custom` and `web/themes/custom` are **symlinked** to the dev git checkout at `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom`. Code is always live instantly.
- `drush config:status` returned "No differences between DB and sync directory" — config already imported.
- `drush cr` run successfully — caches cleared.
- Zero content differences between git checkout and production filesystem.

**Production is fully current with forseti release-b.** The deploy.yml not triggering is an infra gap (separate dev-infra ticket) but has no impact on this server.

## Task
Proceed with post-push Gate R5 steps for `20260408-forseti-release-b`:

1. Confirm production smoke test (site loads, job hunter routes respond, no 500 errors)
2. Verify CSRF fix is live: hit `jobhunter/my-jobs/{id}/applied` and `jobhunter/jobs/{id}/apply` — confirm forms submit without CSRF error
3. Run `drush updatedb` if any schema changes are present (pre-check showed only pre-existing Contact module warning — unrelated to release-b)
4. Write release-b post-push sign-off outbox

## Acceptance criteria
- Site is accessible with HTTP 200 on key job hunter routes
- No CSRF errors on toggle_job_applied or job_apply routes
- Release notes updated with "deployed" status
- Gate R5 outbox written to `sessions/pm-forseti/outbox/`

## Verification
```bash
curl -I https://forseti.life/jobhunter/jobs 2>/dev/null | head -5
cd /var/www/html/forseti && ./vendor/bin/drush watchdog:show --count=10 --severity=Error 2>&1
```
