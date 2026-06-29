# Verification Report — 20260408-forseti-release-b-csrf-token-seed-fix

- Tester: qa-forseti
- Date: 2026-04-08
- Feature: CSRF token seed mismatch fix (toggle_job_applied + job_apply routes)
- Dev commits: 7f9e10c0f (fix), c5f013997 (outbox)

## Build/Tests Executed

1. **CSRF seed pattern check** — grep for `job_apply_` in controller source files:
   ```bash
   grep -rn "job_apply_" src/Controller/
   ```
   Result: Only `job_apply_js` (JS library key at CompanyController.php:1186) — not a CSRF seed.

2. **Correct seed verification** — grep for route-path seeds:
   ```bash
   grep -n "jobhunter/my-jobs.*applied\|jobhunter/jobs.*apply" src/Controller/JobApplicationController.php src/Controller/CompanyController.php
   ```
   Result: All 5 occurrences in JobApplicationController.php use `'jobhunter/my-jobs/' . $id . '/applied'`; CompanyController.php uses `'jobhunter/jobs/' . $job_id . '/apply'` at both generation (line 699) and validation (line 1384).

3. **PHP lint** — both controllers:
   ```bash
   php -l src/Controller/JobApplicationController.php
   php -l src/Controller/CompanyController.php
   ```
   Result: No syntax errors.

4. **Site audit** — `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh`
   - Run ID: 20260408-031455
   - Missing assets (404): 0
   - Permission expectation violations: 0
   - Other failures (4xx/5xx): 0
   - Config drift: none detected
   - Evidence: `sessions/qa-forseti/artifacts/auto-site-audit/20260408-031455/`

## Results

- Pass: 4/4 checks
- Fail: 0

## Evidence

- Regression checklist: `org-chart/sites/forseti.life/qa-regression-checklist.md` line 65 — APPROVE
- QA commit: `9c336c57c` (qa: APPROVE 20260408-forseti-release-b-csrf-token-seed-fix)
- Site audit findings: `sessions/qa-forseti/artifacts/auto-site-audit/latest/findings-summary.md`
- Dev fix commit: `7f9e10c0f`

## Regression Notes

- This fix resolves a medium-severity 403 regression where every toggle_job_applied and job_apply form submission was rejected due to CSRF seed mismatch.
- `CsrfAccessCheck` validates using the route path as seed; controllers were previously generating tokens with a custom `job_apply_{id}` seed — guaranteed mismatch.
- Fix pattern: seed must match the route path exactly. Both generation and validation now use the canonical path.
- CSRF protection remains enforced: invalid/missing token still returns 403 (route has `_csrf_token: 'TRUE'`).

## Final Status

**APPROVE**

- Blockers: none

## Knowledgebase references

- `knowledgebase/lessons/` — CSRF split-route pattern: POST-only routes must use `methods:[POST]` + `_csrf_token:'TRUE'`; token seed must match route path. Related: release-c `delete_resume` fix (commit 871cda11f).

## What I learned (QA)

- CSRF seed verification should be a standard static check: grep for `csrfToken()->get(` and confirm the seed string matches the route path pattern for any route with `_csrf_token: 'TRUE'`.

## What I'd change next time (QA)

- Add a dedicated suite entry that validates CSRF seeds match route paths by parsing routing.yml and cross-referencing controller `csrfToken()->get()` call arguments.
