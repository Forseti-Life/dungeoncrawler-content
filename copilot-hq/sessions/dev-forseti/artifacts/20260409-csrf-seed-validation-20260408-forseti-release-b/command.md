- command: |
    Add a PHPUnit test (or static lint check) that validates CSRF token seed strings in
    job_hunter controllers match the corresponding route paths declared in job_hunter.routing.yml.

    Background: FR-RB-01 (2026-04-08) caused 7 commits of rework + a separate fix release because
    JobApplicationController and CompanyController generated CSRF tokens with custom seeds
    ('job_apply_{id}') instead of route-path seeds ('jobhunter/my-jobs/{id}/applied',
    'jobhunter/jobs/{id}/apply'). The pattern was detectable but not caught before commit.

    ## Acceptance criteria
    - A test (PHPUnit or static grep-based) exists in the job_hunter module that:
      1. Scans all `csrfToken()->get(...)` calls in `src/Controller/` for the job_hunter module.
      2. For each found seed string, verifies it is a known valid route path pattern
         (matching one of the route paths in job_hunter.routing.yml — strip leading slash, allow
         `{param}` placeholders in both the route and seed).
      3. Fails (returns non-zero / FAIL) if any seed uses a custom non-path string.
    - Alternatively: add a Drupal unit test class `CsrfSeedConsistencyTest.php` that asserts the
      seed used in each controller matches the route path pattern for that route.
    - The test is added to `qa-suites/products/forseti/suite.json` under a `forseti-csrf-seed-consistency` entry.
    - Running the check from the Drupal site root returns PASS with no failures.

    ## Verification
    - QA can run: `grep -rn "csrfToken.*get(" sites/forseti/web/modules/custom/job_hunter/src/Controller/`
      and confirm every seed matches a route path in `job_hunter.routing.yml`.
    - Or: `vendor/bin/phpunit modules/custom/job_hunter/tests/Unit/CsrfSeedConsistencyTest.php` → PASS.

    ## Scope
    - Target file(s): `sites/forseti/web/modules/custom/job_hunter/src/Controller/` (all controllers)
    - New test file: `sites/forseti/web/modules/custom/job_hunter/tests/Unit/CsrfSeedConsistencyTest.php`
    - Suite manifest: `qa-suites/products/forseti/suite.json`

- Agent: dev-forseti
- Status: pending
- Release: 20260409-forseti-release-b
- Source: improvement-round-20260408-forseti-release-b (agent-code-review)
