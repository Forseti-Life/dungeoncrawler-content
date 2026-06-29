# Test Plan — forseti-jobhunter-application-controller-db-extraction

- Feature ID: forseti-jobhunter-application-controller-db-extraction
- Written by: qa-forseti
- Release: 20260409-forseti-release-b
- Based on: 01-acceptance-criteria.md (6 ACs)

## Pre-run notes

- `$this->database` count already 0 in `JobApplicationController.php` at plan-write time (dev has begun extraction).
- `ApplicationSubmissionService.php` exists with 5 public methods and PHPDoc; `ApplicationAttemptRepository.php` does NOT exist.
- Controller constructor does NOT inject `ApplicationSubmissionService`; line 2648 uses `\Drupal::service('job_hunter.application_submission_service')`. TC-2 documents this for AC-3 evaluation.
- TC-4 and TC-5 are manually verified (no automation for PHPDoc presence or authenticated multi-step flow).

---

## TC-1 — AC-1: No `$this->database` calls in controller

- Type: static
- Command:
  ```bash
  grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
  ```
- Expected: `0`
- Pass condition: output is exactly `0`

---

## TC-2 — AC-3: No new static `\Drupal::` calls added by extraction

- Type: static
- Command:
  ```bash
  grep -n '\\Drupal::' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
  ```
- Expected: Output must not include any NEW `\Drupal::service()` or `\Drupal::database()` calls introduced by this feature's extraction work.
- Baseline: 19 `\Drupal::` calls were present pre-extraction (CSRF token + service calls).
- Pass condition: Any `\Drupal::service('job_hunter.application_submission_service')` calls found must be confirmed as pre-existing (not newly added). If the service is called via `\Drupal::service()` AND was not present before the extraction, this TC is FAIL (AC-3 requires constructor injection).
- Reviewer note: Line 2648 references `\Drupal::service('job_hunter.application_submission_service')` — confirm via git blame or diff whether this is new.

---

## TC-3 — AC-4: PHP lint passes on all modified files

- Type: static
- Commands:
  ```bash
  php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
  php -l sites/forseti/web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php
  ```
- Expected: `No syntax errors detected` on each file
- Pass condition: Both return clean lint output

---

## TC-4 — AC-2: Extracted methods in service/repository have PHPDoc (manual)

- Type: code-review (manual)
- Target: `sites/forseti/web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php`
- Steps:
  1. Verify each public method in `ApplicationSubmissionService.php` has a `/** ... */` PHPDoc block directly above it.
  2. Verify the queries extracted from the controller are present (all 54 formerly-inline DB queries accounted for — either directly in service methods or via a repository layer).
- Expected: All public methods have PHPDoc; query coverage accounts for all 54 original `$this->database` calls.
- Pass condition: No public method without PHPDoc; reviewer is satisfied that extracted logic is complete.
- Automation note: PHPDoc presence is not automatable in this setup; manual review required.

---

## TC-5 — AC-5: Application submission routes render without errors (manual smoke)

- Type: functional (manual smoke)
- Precondition: Authenticated user with `access job hunter` Drupal permission
- Steps:
  1. Log in as a user with `access job hunter` permission.
  2. Visit `/jobhunter/application-submission/{job_id}` for a known valid `job_id`.
  3. Step through the application submission flow — steps 1–5.
  4. After each step, verify no HTTP 500 and no PHP notices/errors in page output.
  5. Run `drush watchdog:show --count=20` and verify no new critical/error entries from `job_hunter` module.
- Expected: All steps return HTTP 200; no PHP notices or errors in watchdog.
- Anon-access check (automatable):
  ```bash
  curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter/application-submission/1
  ```
  Expected: `403` (unauthenticated access blocked)
- Pass condition: Anon returns 403; manual walk-through of steps 1–5 confirms no 500s or error log entries.

---

## TC-6 — AC-6: Site audit clean

- Type: regression
- Commands:
  ```bash
  ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti
  python3 -c "import json; d=json.load(open('sessions/qa-forseti/artifacts/auto-site-audit/latest/findings-summary.json')); print('failures:', d['counts']['failures'])"
  ```
- Expected: `failures: 0`, violations: 0
- Pass condition: Both counts are 0 after extraction is complete

---

## Suite entries

Suite entries will be added at suite activation (separate inbox item). Do not add to suite.json during grooming.

Planned suites (3):
- `forseti-jobhunter-application-controller-db-extraction-static` — TC-1, TC-2, TC-3 (static grep + lint)
- `forseti-jobhunter-application-controller-db-extraction-functional` — TC-5 anon check
- `forseti-jobhunter-application-controller-db-extraction-regression` — TC-6 site audit
