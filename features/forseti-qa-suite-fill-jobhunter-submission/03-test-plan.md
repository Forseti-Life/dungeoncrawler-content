# Test Plan: forseti-qa-suite-fill-jobhunter-submission

- Feature: forseti-qa-suite-fill-jobhunter-submission
- Module: qa_suites
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify suite.json has correct commands for both suites, PHPUnit path correction is applied, qa-suite-validate.py passes, and each suite command exits 0 when run against a live Drupal instance.

## Test cases

### TC-1: Manifest schema valid
- Steps: `python3 scripts/qa-suite-validate.py`
- Expected: exits 0 with "OK: validated N suite manifest(s)"

### TC-2: Unit suite path is correct
- Steps:
  ```bash
  test -f /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php && echo PASS || echo FAIL
  ```
- Expected: PASS

### TC-3: WorkdayWizardServiceTest.php — all 15 methods pass
- Prereq: Drupal at `/var/www/html/forseti` or `/home/ubuntu/forseti.life/sites/forseti`
- Steps: `cd /var/www/html/forseti && vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php --testdox`
- Expected: All 15 tests pass; exit 0

### TC-4: route-acl suite — anon returns 403 on canonical GET
- Prereq: `FORSETI_BASE_URL` set; Drupal running
- Steps: `curl -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/jobhunter/application-submission"`
- Expected: 403 or 302

### TC-5: route-acl suite — anon returns 403 on POST step route
- Prereq: `FORSETI_BASE_URL` set
- Steps: `curl -X POST -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/jobhunter/application-submission/99999/identify-auth-path"`
- Expected: 403

### TC-6: route-acl suite — authenticated returns 200 on GET step routes
- Prereq: `FORSETI_COOKIE_AUTHENTICATED` set with `access job hunter` user
- Steps: Run route-acl suite command; check artifacts for all submission GET paths show auth=ALLOW
- Expected: exit 0; artifact shows 200 for all GET routes for authenticated user

### TC-7: route-acl suite command exits 0 (full run)
- Prereq: `FORSETI_BASE_URL` and `FORSETI_COOKIE_AUTHENTICATED` set
- Steps: Run the `forseti-jobhunter-application-submission-route-acl` suite command
- Expected: exits 0

### TC-8: unit suite command exits 0 (full run)
- Prereq: Drupal installed at `/var/www/html/forseti`
- Steps: Run the `forseti-jobhunter-application-submission-unit` suite command with corrected path
- Expected: exits 0; all 15 PHPUnit tests pass

## Regression notes
- Existing `forseti-jobhunter-application-submission-route-acl` and `forseti-jobhunter-application-submission-unit` entries must remain in suite.json (not replaced)
- `python3 scripts/qa-suite-validate.py` must still exit 0 after path correction
- ApplicationSubmissionRouteTest.php functional tests (separate) must be unaffected
