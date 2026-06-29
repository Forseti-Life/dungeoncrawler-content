# CSRF FINDING-4: MISSING CSRF — job_hunter application submission routes

- Agent: dev-forseti
- Status: pending

## Context

7 `job_hunter` controller POST routes were missed by the GAP-002 CSRF patch (`694fc424f`). These are authenticated multi-step application workflow routes. `addposting` is a special case (GET/POST combo — direct CSRF token addition caused a regression and was reverted).

**Finding IDs:** FINDING-4a, FINDING-4b, FINDING-4c, FINDING-4d
**Severity:** MEDIUM (all require authentication)
**Registry:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md`
**Full patches:** `sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-forseti-release-b/gap-review.md`

**File:** `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`

## Affected routes

| Finding | Route(s) | Path | Risk |
|---|---|---|---|
| 4a | `application_submission_step3` / `step3_short` | `/jobhunter/application-submission/{id}/identify-auth-path` | MEDIUM |
| 4b | `application_submission_step4` / `step4_short` | `/{id}/create-account` | MEDIUM |
| 4c | `application_submission_step5` / `step5_short` | `/{id}/submit-application` | MEDIUM — highest risk: submits application |
| 4d | `addposting` | `/jobhunter/addposting` | MEDIUM — special case (GET/POST combo) |

## Fix for FINDING-4a/4b/4c (step3/4/5)

Add `_csrf_token: 'TRUE'` under `requirements:` in each route block. These are browser-form controller routes — not JSON API, so `_csrf_token: 'TRUE'` is correct (not `_csrf_request_header_mode`).

Example:
```yaml
job_hunter.application_submission_step5:
  ...
  requirements:
    _permission: 'apply for jobs'
    _csrf_token: 'TRUE'
```

Apply to both canonical and `_short` variants of each step.

## Fix for FINDING-4d (addposting — dev judgment required)

Adding `_csrf_token: TRUE` to the combined GET/POST route caused a GET 403 regression (reverted in `60f2a7ab8`). Fix options:
1. **Split route**: create `job_hunter.addposting_get` (GET, no CSRF) and `job_hunter.addposting_post` (POST, with CSRF).
2. **Controller-level CSRF**: apply CSRF token validation inside the POST handler via `\Drupal::formBuilder()` or manual token check.

Choose whichever approach fits the controller structure. Document the chosen approach in the confirmation artifact.

## Acceptance criteria

1. Verification script exits with PASS (no controller POST routes without CSRF):
   ```bash
   python3 -c "
   import re
   with open('sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml') as f:
       content = f.read()
   blocks = re.split(r'\n(?=[a-zA-Z][a-zA-Z0-9_.]+:\s*\n)', content)
   issues = []
   for block in blocks:
       is_controller = '_controller:' in block and '_form:' not in block
       has_post = bool(re.search(r'methods:.*\[.*POST', block))
       has_csrf = '_csrf_token' in block or '_csrf_request_header_mode' in block
       if has_post and is_controller and not has_csrf:
           route = block.split('\n')[0].rstrip(':')
           issues.append(route)
   print('FAIL:', issues) if issues else print('PASS')
   "
   ```
2. No GET regression on `/jobhunter/addposting` (HTTP 200 expected for anonymous GET).
3. No QA suite regressions.

## Confirmation artifact required

After applying patches, write:
```
sessions/dev-forseti/artifacts/csrf-finding-4-applied.txt
```
Contents: date, commit hash(es), verification script output (PASS), approach chosen for addposting.

This artifact is required for pm-infra Gate 2 approval for forseti releases.

## Definition of done

- FINDING-4a/4b/4c: all step3/4/5 routes (including `_short` variants) have `_csrf_token: 'TRUE'`
- FINDING-4d: addposting fixed via split-route or controller-level CSRF
- Verification script exits PASS
- No GET regression on addposting
- `sessions/dev-forseti/artifacts/csrf-finding-4-applied.txt` written
- Commit hash in outbox
