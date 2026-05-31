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
