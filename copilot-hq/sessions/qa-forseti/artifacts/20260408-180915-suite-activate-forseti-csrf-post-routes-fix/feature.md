# Feature Brief: forseti-csrf-post-routes-fix

- Work item id: forseti-csrf-post-routes-fix
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260408-forseti-release-i
- Priority: P1 (security)
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- BA source: JH-R1 (features/forseti-refactor-inventory/ba-refactor-inventory.md)

## Goal

Add `_csrf_token: 'TRUE'` to the 7 job application POST routes that were added after the GAP-002 CSRF remediation (`694fc424f`) and are currently missing CSRF protection. The `submit-application` route triggers external ATS automation — a CSRF attack could submit job applications on behalf of a logged-in user without consent.

## Background

Routes `application_submission_step3`, `step3_short`, `step4`, `step4_short`, `step5`, `step5_short`, and `step_stub_short` accept POST requests with `_permission: 'access job hunter'` but have no `_csrf_token: TRUE`. This is the same class of vulnerability as GAP-002 (priority P1).

KB reference: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` (split-route pattern for GET+POST routes).

## Definition of Done

- All 7 routes have CSRF protection (either `_csrf_token: 'TRUE'` on POST-only routes per split-route pattern, or confirmed `X-CSRF-Token` header enforcement for AJAX routes).
- `python3 enumerate_post_routes.py job_hunter.routing.yml` shows no "access job hunter" POST route with CSRF=NO.
- QA confirms all step3/4/5 pages still render and POST correctly as authenticated user.
- Commit hash recorded in implementation notes.

## Security acceptance criteria

- Authentication/permission surface: All 7 POST routes require `_permission: 'access job hunter'` (authenticated users with job hunter access only). No anonymous access.
- CSRF expectations: All 7 POST routes must have `_csrf_token: 'TRUE'` on the POST-only route entry (split-route pattern per KB lesson), or confirmed `X-CSRF-Token` header enforcement for AJAX routes.
- Input validation: Step form inputs validated per existing controller logic (no new input surfaces added in this feature).
- PII/logging constraints: No additional logging introduced. Existing Drupal watchdog patterns apply.

## Rollback

Revert `_csrf_token` additions in `job_hunter.routing.yml`. No schema changes.
