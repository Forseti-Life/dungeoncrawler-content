# Implementation Notes: forseti-csrf-post-routes-fix

## Feature
Add `_csrf_token: 'TRUE'` to the 7 job application POST routes missing CSRF protection via the split-route pattern.

## Status
Complete — implemented in prior release (commits `dd2dcc764` and `6eab37e4c`, 2026-04-05) as part of `forseti-csrf-fix`. This inbox item arrived after the fix was already applied.

## KB reference
`knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` — split-route pattern (separate GET-only and POST-only route entries; POST-only gets `_csrf_token: 'TRUE'`).

## AC verification

| AC | Criterion | Result |
|---|---|---|
| AC-1 | All 7 POST routes have `_csrf_token: 'TRUE'` | **PASS** (7/7 verified by grep) |
| AC-2 | Legitimate user submission works (no false 403) | Pending QA smoke test |
| AC-3 | Cross-origin POST without token returns 403 | Pending QA CSRF omission test |
| AC-4 | No regressions on GET routes | Pending QA crawl |

## Manual verification

```
grep -A10 "step3_post:\|step3_short_post:\|step4_post:\|step4_short_post:\|step5_post:\|step5_short_post:\|step_stub_short_post:" \
  sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml \
  | grep -E "methods|_csrf_token"
```

Output: 7× `methods: [POST]` / `_csrf_token: 'TRUE'` pairs — zero missing.

## Routes fixed

| Route name (POST entry) | Path | Commit |
|---|---|---|
| `job_hunter.application_submission_step3_post` | `/jobhunter/application-submission/{job_id}/identify-auth-path` | `dd2dcc764` |
| `job_hunter.application_submission_step3_short_post` | `/application-submission/{job_id}/identify-auth-path` | `dd2dcc764` |
| `job_hunter.application_submission_step4_post` | `/jobhunter/application-submission/{job_id}/create-account` | `dd2dcc764` |
| `job_hunter.application_submission_step4_short_post` | `/application-submission/{job_id}/create-account` | `dd2dcc764` |
| `job_hunter.application_submission_step5_post` | `/jobhunter/application-submission/{job_id}/submit-application` | `dd2dcc764` |
| `job_hunter.application_submission_step5_short_post` | `/application-submission/{job_id}/submit-application` | `dd2dcc764` |
| `job_hunter.application_submission_step_stub_short_post` | `/application-submission/{job_id}/step/{step}` | `6eab37e4c` |

## Implementation approach
Each route was split into separate GET-only and POST-only entries. The POST-only `_post` variant adds `_csrf_token: 'TRUE'`. Drupal's `RouteProcessorCsrf` auto-appends `?token=xxx` to CSRF-protected form action URLs via `path()`. No template changes were required for stub routes (controller only does redirects).

## Rollback
`git revert dd2dcc764 6eab37e4c` — routing-only changes, no schema impact.
