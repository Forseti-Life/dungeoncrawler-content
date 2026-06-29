# Test Plan: forseti-qa-suite-fill-controller-extraction

- Feature: forseti-qa-suite-fill-controller-extraction
- Module: qa_suites
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify static structural checks (Phase 1 refactor invariants) and regression routes (no 500s, ACL enforced) for the `JobApplicationController` extraction refactor. Both suites must have deterministic commands that exit 0 = PASS.

## Test cases

### TC-1: Manifest schema valid
- Steps: `python3 scripts/qa-suite-validate.py`
- Expected: exits 0 with "OK: validated N suite manifest(s)"

### TC-2: JobApplicationController has 0 direct DB calls
- Steps: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
- Expected: 0

### TC-3: ApplicationSubmissionController has 0 direct DB calls
- Steps: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`
- Expected: 0

### TC-4: ApplicationActionController has 0 direct DB calls
- Steps: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`
- Expected: 0

### TC-5: JobApplicationRepository.php exists
- Steps: `test -f sites/forseti/web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php && echo PASS`
- Expected: PASS

### TC-6: Repository registered in services.yml
- Steps: `grep -c 'job_hunter.job_application_repository' sites/forseti/web/modules/custom/job_hunter/job_hunter.services.yml`
- Expected: ≥ 1

### TC-7: ApplicationSubmissionController injects repository via container
- Steps: `grep -c "job_hunter.job_application_repository" sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`
- Expected: ≥ 1

### TC-8: static suite command exits 0 (all 7 AC-STATIC checks)
- Prereq: `DRUPAL_ROOT` set or running from `/home/ubuntu/forseti.life`
- Steps: Run the `forseti-jobhunter-controller-extraction-phase1-static` suite command
- Expected: exits 0

### TC-9: Anonymous → 403/302 on application-submission route (no 500)
- Prereq: `FORSETI_BASE_URL` set; Drupal running
- Steps: `curl -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/jobhunter/application-submission"`
- Expected: 403 or 302 (not 500)

### TC-10: Authenticated → 200 on application-submission route (no 500)
- Prereq: `FORSETI_BASE_URL` and `FORSETI_COOKIE_AUTHENTICATED` set with `access job hunter` user
- Steps: `curl -b "..." -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/jobhunter/application-submission"`
- Expected: 200 (not 500 or 403)

### TC-11: regression suite command exits 0 (all 7 Phase 1 routes)
- Prereq: `FORSETI_BASE_URL` and `FORSETI_COOKIE_AUTHENTICATED` set
- Steps: Run the `forseti-jobhunter-controller-extraction-phase1-regression` suite command
- Expected: exits 0; no route returns 500

## Regression notes
- `python3 scripts/qa-suite-validate.py` must exit 0 after suite.json changes
- CompanyController, GoogleJobsSearchController, ResumeController are NOT in Phase 1 scope; their DB calls are expected and must not appear as failures in this suite
- ApplicationSubmissionRouteTest.php functional tests must remain unaffected
