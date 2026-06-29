# Feature Brief

- Work item id: forseti-qa-e2e-auth-pipeline
- Website: forseti.life
- Module: qa_suites
- Status: ready
- Release: TBD (release-h)
- Feature type: qa-infrastructure
- PM owner: pm-forseti
- Dev owner: qa-forseti
- QA owner: qa-forseti
- Priority: low
- Source: PROJ-002 Phase 1 (dispatched 2026-04-09)

## Summary

Unblock the Playwright E2E test pipeline by provisioning an authenticated session cookie via `drush user:login`. Currently the `jobhunter-e2e` Playwright suite cannot run because `FORSETI_COOKIE_AUTHENTICATED` is not provisioned. This feature creates the auth cookie provisioning script and wires it into the E2E test setup.

Additionally, provision a second QA user (`qa_tester_authenticated_2`) for cross-user isolation tests (TC-11, TC-16).

## E2E suites unblocked by this feature

- jobhunter-e2e (primary E2E flow)
- forseti-jobhunter-application-status-dashboard-e2e
- forseti-jobhunter-google-jobs-ux-e2e
- forseti-jobhunter-profile-completeness-e2e
- forseti-jobhunter-resume-tailoring-display-e2e
- forseti-ai-conversation-user-chat-e2e

## Goal

`FORSETI_COOKIE_AUTHENTICATED` can be populated by running `drush user:login --name=qa_tester_authenticated` and extracting the session cookie. A second user `qa_tester_authenticated_2` exists for cross-user isolation.

## Non-goals

- Filling test_cases for non-E2E suites (handled by fill features)
- Changes to Drupal module code

## Security acceptance criteria
- Security AC exemption: QA-infrastructure only — no Drupal routes, no user-facing input, no PII handled. All changes are confined to qa-suites/ config/test files.
