# Suite Activation: forseti-jobhunter-return-to-open-redirect

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T23:54:31+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-return-to-open-redirect"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-return-to-open-redirect/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-return-to-open-redirect-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-return-to-open-redirect",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-return-to-open-redirect"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-return-to-open-redirect-<route-slug>",
     "feature_id": "forseti-jobhunter-return-to-open-redirect",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-return-to-open-redirect",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-jobhunter-return-to-open-redirect

- Feature: Fix return_to parameter open redirect bypass
- QA owner: qa-forseti
- Release target: 20260409-forseti-release-j
- Date written: 2026-04-09

## Overview

Validate that protocol-relative URLs (`//evil.com`) are rejected by `return_to` validation in `CompanyController.php` and `ApplicationActionController.php`. After fix, only paths starting with `/` and not `//` are accepted; all others fall back to `/jobhunter/my-jobs`.

---

## Test Cases

### TC-01 — Static: no unguarded strpos-only validation remains
- **Suite:** static grep
- **AC item:** No bare `strpos($return_to, '/') !== 0` check without `//` rejection
- **Steps:**
  1. `grep -n "strpos.*return_to\|return_to.*strpos" sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
  2. `grep -n "strpos.*return_to\|return_to.*strpos" sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`
- **Expected:** No results, OR results show the old pattern replaced with `preg_match` equivalent
- **Automated:** Yes

### TC-02 — Functional: protocol-relative URL rejected (CompanyController)
- **Suite:** `role-url-audit` / functional HTTP test
- **AC item:** `?return_to=//evil.com` results in redirect to `/jobhunter/my-jobs`
- **Steps:**
  1. As authenticated user with `access job hunter`, GET a route that reads `return_to` from query string (e.g., company delete confirm) with `?return_to=//evil.com`
  2. Follow redirect; check final destination
- **Expected:** Final redirect target is `/jobhunter/my-jobs`, not `//evil.com`
- **Roles covered:** authenticated job hunter user
- **Automated:** Yes if functional test suite available; manual otherwise

### TC-03 — Functional: valid relative path accepted
- **Suite:** functional HTTP test
- **AC item:** `?return_to=/jobhunter/my-jobs` redirects correctly
- **Steps:**
  1. GET/POST same route with `?return_to=/jobhunter/my-jobs`
  2. Follow redirect
- **Expected:** Final redirect target is `/jobhunter/my-jobs`
- **Roles covered:** authenticated job hunter user
- **Automated:** Yes

### TC-04 — Functional: absolute URL rejected
- **Suite:** functional HTTP test
- **AC item:** `?return_to=http://evil.com` fallback to default
- **Steps:**
  1. GET with `?return_to=http://evil.com`
  2. Follow redirect
- **Expected:** Final redirect target is `/jobhunter/my-jobs`
- **Automated:** Yes

## Pass/Block criteria

- **PASS:** TC-01 shows no unguarded validation; TC-02 confirms `//evil.com` rejected; TC-03 confirms valid paths still work
- **BLOCK:** `//evil.com` still passes through to redirect target (open redirect confirmed); or valid paths broken by overly strict check

### Acceptance criteria (reference)

# Acceptance Criteria — forseti-jobhunter-return-to-open-redirect

- Feature: Fix return_to parameter open redirect bypass
- Release target: 20260409-forseti-release-j
- PM owner: pm-forseti
- Date groomed: 2026-04-09
- Priority: P2

## Gap analysis reference

Feature type: `security` — Recurring LOW (flagged release-h and prior). Current validation `strpos($return_to, '/') !== 0` passes `//evil.com` because it starts with `/`. Protocol-relative redirect bypass must be closed.

PM decision: fix in-place using regex pattern; no centralized helper required this release (scope-limited).

## Knowledgebase check
- KB: `knowledgebase/lessons/` — no prior lesson on return_to redirect bypass found. This lesson should be written after fix.
- Pattern from CSRF routing memory: split-route pattern already applied to POST routes; this fix is validation-only, no routing change needed.

## Happy Path

- [ ] `?return_to=/jobhunter/my-jobs` → redirect to `/jobhunter/my-jobs` (valid relative path, accepted)
- [ ] `?return_to=/companies/123` → redirect to `/companies/123` (valid relative path, accepted)

## Edge Cases

- [ ] `?return_to=//evil.com` → redirect to `/jobhunter/my-jobs` (protocol-relative, rejected)
- [ ] `?return_to=http://evil.com` → redirect to `/jobhunter/my-jobs` (absolute URL, rejected — already caught by existing check)
- [ ] `?return_to=` (empty string) → redirect to `/jobhunter/my-jobs` (empty, rejected)
- [ ] `?return_to=javascript:alert(1)` → redirect to `/jobhunter/my-jobs` (non-slash prefix, rejected)

## Failure Modes

- [ ] If regex is too strict (e.g., rejects valid paths with special chars), legitimate navigations break — test with edge-case paths before merging

## Permissions / Access Control

- [ ] `return_to` handling occurs post-authentication; no change to permission requirements on affected routes.

## Data Integrity

- [ ] No DB changes required.
- [ ] Rollback: revert the validation pattern change.

## Dev definition of done
- [ ] All `return_to` validation blocks in `CompanyController.php` and `ApplicationActionController.php` use `!preg_match('/^\/(?!\/)/', $return_to)` (or equivalent that rejects `//` prefix)
- [ ] `//evil.com` passes QA test as rejected (fallback to default URL)
- [ ] No bare `strpos($return_to, '/') !== 0` pattern remains in these files without the `//` rejection guard

## QA test path
- TC: GET/POST a request with `?return_to=//evil.com` (or POST body equivalent) to an affected route; confirm response redirects to `/jobhunter/my-jobs`.
- Static check: `grep -n "strpos.*return_to\|return_to.*strpos" CompanyController.php ApplicationActionController.php` — confirm no unguarded simple strpos check remains.
