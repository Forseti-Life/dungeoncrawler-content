# Feature Brief

- Work item id: forseti-jobhunter-return-to-open-redirect
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 6
- Group: infrastructure
- Group Title: Technical Infrastructure
- Group Sort: 6
- Status: shipped
- Release: 20260409-forseti-release-j
- Priority: P2
- Feature type: security
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260409-forseti-release-j
- Source: Code review LOW-1 (recurring) — flagged in 20260409-forseti-release-h and multiple prior releases

## Goal

Fix the `return_to` parameter validation in `CompanyController.php` and `ApplicationActionController.php` to reject protocol-relative URLs (`//evil.com`) that bypass the current leading-slash check. The current guard `strpos($return_to, '/') !== 0` incorrectly passes `//evil.com` because it starts with `/`.

## Problem

Current validation pattern (e.g., `CompanyController.php` line 558):
```php
if (strpos($return_to, '/') !== 0) {
    $return_to = '/jobhunter/my-jobs';
}
```

This allows `//evil.com` to pass because it starts with `/`. A correct check must also reject paths that start with `//` (protocol-relative open redirects).

## Acceptance criteria

- [ ] All `return_to` validation blocks in `CompanyController.php` and `ApplicationActionController.php` reject protocol-relative URLs (`//` prefix)
- [ ] Correct validation pattern applied everywhere: `if (!preg_match('/^\/(?!\/)/', $return_to))` OR equivalent check that rejects both non-slash-prefixed and `//`-prefixed values
- [ ] Default fallback remains `/jobhunter/my-jobs` when validation fails
- [ ] No new routes introduced

## Affected files

- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` (lines 558, 1789)
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` (lines 397, 436, and any similar patterns)

## Verification

```bash
grep -n "return_to\|strpos.*return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php
grep -n "return_to\|strpos.*return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
```

None of the remaining validation blocks should use bare `strpos($return_to, '/') !== 0` without also rejecting `//`.

QA test: Verify that a `?return_to=//evil.com` parameter on any redirect-accepting route results in redirect to `/jobhunter/my-jobs`, not to `//evil.com`.

## Non-goals

- Centralizing all `return_to` logic into a shared helper (out of scope for this feature — PM decision)
- Adding new controller tests beyond the redirect path

## Risks

Low — targeted validation change. Wrong fallback URL would be more disruptive than the current bypass; test first.

## Security acceptance criteria
- Authentication/permission surface: `return_to` redirect handling occurs post-authentication (routes require `access job hunter` permission); anonymous users cannot reach these redirects.
- CSRF expectations: POST routes with `return_to` already have `_csrf_token: 'TRUE'` (release-h confirmed). No change to CSRF posture.
- Input validation: Protocol-relative URL rejection (`//`) is the primary fix. After patch, only `/`-prefixed non-protocol-relative paths accepted as valid `return_to` values.
- PII/logging constraints: No PII involved in redirect parameter handling.
