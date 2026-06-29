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
