# Implementation Notes — forseti-jobhunter-browser-automation

- Feature: BrowserAutomationService Phase 1 + Phase 2
- Dev owner: dev-forseti
- Release: 20260228-forseti-release
- Date: 2026-02-28

## KB references
- `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — routing.yml permission mismatch pattern (relevant: same root cause found for this QA cycle)
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — job_seeker_id vs UID in attempt logging

## Implementation status

Feature type: `needs-testing`. All code was already shipped (phases 1+2). No new implementation required; this notes document covers the **permission remediation** needed for Gate 2 QA to pass.

## Root cause of QA failures (20260228-084923)

The `credentials-ui` rule failure (`/jobhunter/settings/credentials` returning 403 for authenticated) and all `jobhunter-surface` failures (31 paths returning 403 for authenticated) were caused by a **Drupal config/DB drift**:

- `user.role.authenticated.yml` in the sync directory contained `access job hunter` permission.
- The active database did NOT have this permission (config had not been imported).

This is the same class of problem as documented in KB `20260227-routing-permission-mismatch-companyresearch.md`, but at the role-level rather than the routing.yml level.

## Fix applied

Granted `access job hunter` permission to the `authenticated` role via drush:

```bash
cd /home/keithaumiller/forseti.life/sites/forseti
vendor/bin/drush role:perm:add authenticated 'access job hunter'
vendor/bin/drush role:perm:add authenticated 'view own unpublished content'
```

Also cleared Drupal cache to resolve a stale service container (was causing 500s on `/talk-with-forseti` for authenticated users):

```bash
vendor/bin/drush cr
```

## Separate fix: talk-with-forseti anon-access violations

Two routing files were fixed to restrict `/talk-with-forseti` and `/talk-with-forseti_content` to authenticated users only (QA rule: anon=deny). Changed `_permission: 'access content'` → `_user_is_logged_in: 'TRUE'`.

Commit: `d015207f7` in forseti.life repo.

## Verification commands

```bash
# Confirm authenticated role has access job hunter
vendor/bin/drush php:eval "use Drupal\user\Entity\Role; \$r = Role::load('authenticated'); echo in_array('access job hunter', \$r->getPermissions()) ? 'PASS' : 'FAIL';"

# Confirm anon gets 403 on talk-with-forseti
curl -s -o /dev/null -w "%{http_code}" http://localhost/talk-with-forseti  # expect 403

# Confirm credentials route works (requires authenticated session — QA to verify)
# /jobhunter/settings/credentials should return 200 for authenticated
```

## Phase 3 — WorkdayWizardService (commit `7dea91e8f`)

Shipped as part of `forseti-jobhunter-application-submission`. New files:

- `src/Service/WorkdayWizardService.php` — advances Workday portal wizard steps 2–7 via Playwright subprocess. Public API: `advanceStep()` (single step) and `advanceWizardAutoSingleSession()` (full sequence). Uses `WorkdayPlaywrightRunner` for subprocess management.
- `src/Service/WorkdayPlaywrightRunner.php` — spawns `playwright/workday-wizard-advance.js` with `proc_open`, reads output file, enforces hard timeout cap.
- `src/Service/WorkdayProfileDataMapper.php` — assembles profile data for wizard form filling.
- `playwright/workday-wizard-advance.js` — Node.js script driving the Workday browser session.

Integration point: `JobApplicationController::applicationSubmissionSubmitApplication()` calls `advanceWizardAutoSingleSession()` after resume upload (Step 5 POST path).

### Timeout handling fix (applied 2026-04-06)
`WorkdayPlaywrightRunner::runWizardPayload()` was not draining `$pipes[1]` (stdout) in its wait loop. A verbose Node subprocess writing large output to stdout could block on a full pipe buffer, causing the hard-cap timeout to fire incorrectly. Fixed by setting stdout to non-blocking and draining it each poll iteration alongside stderr.

### Test coverage
- Unit: `tests/src/Unit/Service/WorkdayWizardServiceTest.php` — covers `advanceStep()` and `advanceWizardAutoSingleSession()` happy-path, timeout, missing context, and invalid step key paths.
- Functional: `tests/src/Functional/Controller/ApplicationSubmissionRouteTest.php` — smoke-tests 5 GET routes for 200 (authenticated) / 403 (anonymous).

## Open item (escalated to PM/CEO)

`user-register | anon | deny` — QA rule expects anonymous users to be denied `/user/register` (200 returned instead). This is a product decision (open vs invite-only registration). Not changed by dev.

## Rollback plan

```bash
vendor/bin/drush role:perm:remove authenticated 'access job hunter'
# Revert routing: git revert d015207f7
```

## Next: QA handoff

QA should retest:
- `/jobhunter/settings/credentials` as authenticated → expect 200
- `/jobhunter` (and any jobhunter-surface path) as authenticated → expect 200
- `/talk-with-forseti` as anon → expect 403
- `/talk-with-forseti_content` as anon → expect 403
- All other BrowserAutomationService criteria from `01-acceptance-criteria.md`
