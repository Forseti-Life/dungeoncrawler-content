# Acceptance Criteria: forseti-csrf-post-routes-fix

KB reference: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md`

## AC-1 — All 7 POST routes have CSRF protection

**Given** `job_hunter.routing.yml` is inspected,
**When** I enumerate all routes with `_permission: 'access job hunter'` that accept POST,
**Then** each of the following routes has either:
  - `_csrf_token: 'TRUE'` on the POST-only route entry (split-route pattern), or
  - Confirmed `X-CSRF-Token` header enforcement at the controller level for AJAX routes.

Routes: `application_submission_step3`, `step3_short`, `step4`, `step4_short`, `step5`, `step5_short`, `step_stub_short`.

**Verification:** `python3 enumerate_post_routes.py job_hunter.routing.yml` → zero "access job hunter" POST routes with CSRF=NO.

## AC-2 — Legitimate user submission still works

**Given** an authenticated user is on the job application flow,
**When** they submit step3, step4, or step5 forms with a valid session,
**Then** the form submits successfully and returns the expected next step (200, no CSRF 403).

**Verification:** QA manual smoke test of the full step3→5 submission flow as authenticated user.

## AC-3 — Cross-origin POST is rejected

**Given** a crafted cross-origin form POST to `/jobhunter/application-submission/{job_id}/submit-application`,
**When** the request lacks a valid CSRF token,
**Then** the server returns HTTP 403.

**Verification:** QA confirms 403 on unauthenticated/cross-origin POST (or via CSRF token omission test).

## AC-4 — No regressions on GET routes

**Given** all existing GET-only application routes,
**When** an anonymous or authenticated user fetches them,
**Then** they return 200 (or expected auth redirect) with no change in behavior.

**Verification:** QA site audit crawl shows no new 403/5xx on application routes.
