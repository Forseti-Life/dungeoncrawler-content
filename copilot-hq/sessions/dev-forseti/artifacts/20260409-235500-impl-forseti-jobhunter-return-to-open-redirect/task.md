# Implement: forseti-jobhunter-return-to-open-redirect

- Agent: dev-forseti
- Release: 20260409-forseti-release-j
- Priority: P2 (security)
- Status: pending
- Dispatched by: pm-forseti
- ROI: 10

## Task

Fix the `return_to` parameter open redirect bypass in `CompanyController.php` and `ApplicationActionController.php`. The current check `strpos($return_to, '/') !== 0` is insufficient — it passes `//evil.com` (starts with `/`). Replace with a check that also rejects protocol-relative URLs.

## Implementation

Replace all instances of the bare validation pattern:
```php
if (strpos($return_to, '/') !== 0) {
    $return_to = '/jobhunter/my-jobs';
}
```

With a guard that rejects both non-slash-prefixed and protocol-relative values. Recommended:
```php
if (!preg_match('/^\/(?!\/)/', $return_to)) {
    $return_to = '/jobhunter/my-jobs';
}
```

This rejects: `http://evil.com`, `//evil.com`, `javascript:alert(1)`, empty string.
This accepts: `/jobhunter/my-jobs`, `/companies/123`, `/admin/...`

## Affected files

- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` — check lines 558 and 1789 (plus any similar patterns)
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` — check lines 397, 436 (plus any similar patterns)

Grep first to find all instances:
```bash
grep -n "strpos.*return_to\|return_to.*strpos" sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
```

## Context

- Feature spec: `features/forseti-jobhunter-return-to-open-redirect/feature.md`
- AC: `features/forseti-jobhunter-return-to-open-redirect/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-return-to-open-redirect/03-test-plan.md`
- Source: Code review LOW-1 (recurring) from release-h and prior releases

## Verification

After fix:
```bash
grep -n "strpos.*return_to\|return_to.*strpos" sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
```
Must return no results (all bare strpos checks replaced).

## Git rule

After implementation: `git status`, `git diff`, `git add`, `git commit` with message referencing feature ID.
