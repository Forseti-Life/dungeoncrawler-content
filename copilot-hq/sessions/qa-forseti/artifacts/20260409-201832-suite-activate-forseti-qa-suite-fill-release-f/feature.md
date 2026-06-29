# Feature Brief

- Work item id: forseti-qa-suite-fill-release-f
- Website: forseti.life
- Module: qa_suites
- Status: ready
- Release: TBD (release-g or release-h)
- Feature type: qa-infrastructure
- PM owner: pm-forseti
- Dev owner: qa-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: PROJ-002 Phase 1 (dispatched 2026-04-09)

## Summary

Populate `test_cases` for the empty suite shells created during release-f feature development. All 5 release-f features are shipped and active in production. The suite entries exist in `qa-suites/products/forseti/suite.json` as empty shells; this feature fills them with executable test cases so the suites are re-runnable without re-reading session outboxes.

## Suites in scope

- forseti-jobhunter-application-status-dashboard-static
- forseti-jobhunter-application-status-dashboard-functional
- forseti-jobhunter-application-status-dashboard-regression
- forseti-jobhunter-google-jobs-ux-static
- forseti-jobhunter-google-jobs-ux-functional
- forseti-jobhunter-google-jobs-ux-regression
- forseti-jobhunter-profile-completeness-static
- forseti-jobhunter-profile-completeness-functional
- forseti-jobhunter-profile-completeness-regression
- forseti-jobhunter-resume-tailoring-display-static
- forseti-jobhunter-resume-tailoring-display-functional
- forseti-jobhunter-resume-tailoring-display-regression
- forseti-ai-conversation-user-chat-static
- forseti-ai-conversation-user-chat-acl
- forseti-ai-conversation-user-chat-csrf-post
- forseti-ai-conversation-user-chat-regression

## Goal

Each suite has at least 2 executable `test_cases` entries (bash commands with PASS/FAIL output) covering the key acceptance criteria for the shipped feature.

## Non-goals

- E2E Playwright tests (deferred to forseti-qa-e2e-auth-pipeline)
- New features or behavior changes

## Security acceptance criteria
- Security AC exemption: QA-infrastructure only — no Drupal routes, no user-facing input, no PII handled. All changes are confined to qa-suites/ config/test files.
