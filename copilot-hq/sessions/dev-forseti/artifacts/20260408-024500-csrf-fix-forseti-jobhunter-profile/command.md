# Security Fix — CSRF gap on job_hunter.delete_resume

- Release: 20260407-forseti-release-c
- Feature: forseti-jobhunter-profile
- Priority: P1 (security fix — MEDIUM severity)
- Dispatched by: pm-forseti
- Finding source: qa-forseti outbox `20260408-unit-test-20260408-022038-impl-forseti-jobhunter-profile.md`

## Finding

The `job_hunter.delete_resume` route executes a destructive DB delete without CSRF protection:
- No `_csrf_token: 'TRUE'` in routing definition
- No `methods: [POST]` constraint (accessible via GET)
- Controller does not manually validate any CSRF token

This is a MEDIUM security gap: a malicious link could trigger resume deletion for an authenticated user.

## Required fix

In the routing file (likely `job_hunter.routing.yml`), for the `job_hunter.delete_resume` route:
1. Add `_csrf_token: 'TRUE'`
2. Add `methods: [POST]`

In the Twig template that links to delete_resume:
- Use `path('job_hunter.delete_resume', {...})|url_with_csrf` or the standard Drupal `{{ path(...) }}` which auto-appends `?token=` for CSRF-protected routes
- Wrap the link in a form with POST method

Per CSRF routing knowledge base: use the **split-route pattern** — separate GET route (for display/confirmation page) and POST-only route (for the destructive action). See existing pattern on `credentials_delete` and `credentials_test` routes in `forseti-jobhunter-browser-automation`.

## Definition of done

- `job_hunter.delete_resume` route has `_csrf_token: 'TRUE'` and `methods: [POST]`
- Twig template uses POST form for the delete action (not a bare GET link)
- PHP lint clean on modified files
- Code committed with message referencing `forseti-jobhunter-profile CSRF fix`

## Verification

QA will re-verify: route cannot be triggered via GET, and `anon: deny` still holds per `forseti-jobhunter-resume-delete` ACL rule.
