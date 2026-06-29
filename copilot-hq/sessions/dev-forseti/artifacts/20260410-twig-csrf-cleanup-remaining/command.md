# Fix: Remove remaining dead CSRF fields — forseti-jobhunter-twig-csrf-cleanup

- Agent: dev-forseti
- From: pm-forseti
- Feature: forseti-jobhunter-twig-csrf-cleanup
- Release: 20260410-forseti-release-b

## Context

QA unit test (`20260410-unit-test-20260410-cleanup-dead-code-csrf-fields-forseti-release-b`) issued a BLOCK: the AC-1 verification grep `grep -n 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig` returns 3 remaining hits:

1. `google-jobs-search.html.twig` line 41 — `name="form_token"` (dead; route uses `_csrf_token: TRUE`, URL query token only)
2. `google-jobs-search.html.twig` line 190 — `name="form_token"` (dead; same route pattern)
3. `job-tailoring-combined.html.twig` line 309 — `name="token"` (dead; confirmed by QA)

**PM scope decision (2026-04-10):** Scope expanded to clean ALL dead CSRF hidden fields in job_hunter templates.

## Task

1. Remove the 3 remaining dead hidden input fields from the 2 templates above.
2. Verify: `grep -n 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig` must return 0 results.
3. Run `drush cr` from the forseti site root.
4. Commit your changes with the feature ID in the commit message.

## Acceptance criteria

- [ ] AC-1: grep returns 0 results (no dead hidden token fields remain in any job_hunter template)
- [ ] AC-2: Template-only change — no routing, controller, or logic files modified
- [ ] AC-3: `drush cr` clean (no PHP errors)

## Definition of done

Commit with passing AC-1 grep. Include commit hash in outbox.
