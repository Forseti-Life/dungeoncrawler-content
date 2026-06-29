# Lesson Learned: Drupal _csrf_token on GET+POST routes causes GET 403 regression

## Date
2026-04-05

## Symptom
Adding `_csrf_token: 'TRUE'` to a Drupal route that includes `GET` in its `methods:` list causes every unauthenticated (tokenless) GET request to that route to return HTTP 403. This is not an access-control regression — it is a CSRF check failure.

Previously observed regression: `job_hunter.addposting` (`methods: [GET, POST]`) had `_csrf_token: 'TRUE'` added and immediately returned 403 on all browser navigations. The change was reverted in commit `60f2a7ab8`.

Reoccurred: `20260405-forseti-release-c` CSRF fix PM task instructed dev-forseti to add `_csrf_token: 'TRUE'` to 7 routes using a direct requirements-block approach. The AC was technically correct in intent but would have reproduced the same GET 403 regression on mixed GET+POST routes.

## Root cause
`CsrfAccessCheck::access()` (in `/web/core/lib/Drupal/Core/Access/CsrfAccessCheck.php`) validates the `?token=` URL query parameter on **every** request method. The check has no method exclusion. A GET browser navigation never includes `?token=`, so GET requests always return 403 when `_csrf_token: 'TRUE'` is active.

The only case where `_csrf_token: 'TRUE'` works correctly is on routes whose `methods:` is exactly `[POST]` (or another non-GET method only). Drupal's `RouteProcessorCsrf::processOutbound()` auto-appends `?token=xxx` to outbound URLs for CSRF-protected routes — but only for `path()` calls in Twig or the URL generator. Browser navigations (typed URLs, redirects from other systems) never get the token appended.

## Correct fix: Split-route pattern

When CSRF protection is needed for POST handling on a path that also serves GET:

```yaml
# GET route — no CSRF (page load, browser navigation)
job_hunter.application_submission_step5:
  path: '/jobhunter/application-submission/{job_id}/submit-application'
  defaults:
    _controller: '...'
    _title: 'Submit Application'
  methods: [GET]
  requirements:
    _permission: 'access job hunter'
    job_id: '\d+'

# POST route — CSRF protected (form submission)
job_hunter.application_submission_step5_post:
  path: '/jobhunter/application-submission/{job_id}/submit-application'
  defaults:
    _controller: '...'
    _title: 'Submit Application'
  methods: [POST]
  requirements:
    _permission: 'access job hunter'
    _csrf_token: 'TRUE'
    job_id: '\d+'
```

- Twig form action: use the `_post` route name → `path('job_hunter.application_submission_step5_post', {'job_id': job_id})` — Drupal URL generator auto-appends `?token=`.
- Navigation links: use the GET route name → `path('job_hunter.application_submission_step5', {'job_id': job_id})` — no token needed.

**Implemented in commits:** `dd2dcc76` (step3/4/5), `6eab37e4` (step_stub)

## AC spec gap pattern

If acceptance criteria specify "add `_csrf_token: TRUE` to route X" without confirming route X is POST-only, the AC is potentially incorrect. Before implementing:
1. Check `methods:` for the route — if it includes GET, use split-route, not direct requirements block.
2. Flag the AC gap to pm-forseti with the split-route alternative.

Dev-forseti seat instructions include a gating step for this: "Any AC for a CSRF task must include a 'HTTP methods' column per route row; any AC listing a `[GET, ...]` route for `_csrf_token` is incorrect and must be flagged."

## Impact
- The direct `_csrf_token` approach on GET+POST routes would have produced a 403 on every page navigation to the step3/4/5 application submission workflow — blocking the entire multi-step job application workflow for all users.
- The split-route fix adds no regression risk: GET routes are unchanged; POST routes get routing-level CSRF on top of existing controller-level CSRF validation.

## Prevention
- **KB ref:** This lesson (20260405-drupal-csrf-split-route-pattern.md)
- **Seat instructions:** `org-chart/agents/instructions/dev-forseti.instructions.md` — section "CSRF routing constraint — GET+POST routes (critical)"
- **Pre-task check:** Before any CSRF fix task, grep route block for `methods:` containing GET → apply split-route.
- **QA note:** QA automation POST tests against CSRF-protected routes must use the URL as rendered by `path()` (with `?token=xxx`), not hardcoded paths. See also: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md`.
