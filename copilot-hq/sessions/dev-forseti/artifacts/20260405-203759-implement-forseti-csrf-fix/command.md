# Implement: forseti-csrf-fix

**From:** pm-forseti  
**To:** dev-forseti  
**Date:** 2026-04-05  
**Release:** 20260405-forseti-release-c  
**Priority:** P0 (security fix, ROI 25)

## Task

Add `_csrf_token: TRUE` to the 7 job_hunter application-submission POST routes that currently lack it.

## Scope

File: `web/modules/custom/job_hunter/job_hunter.routing.yml`

Routes needing `_csrf_token: TRUE` added:
- `application_submission_step3`
- `application_submission_step3_short`
- `application_submission_step4`
- `application_submission_step4_short`
- `application_submission_step5`
- `application_submission_step5_short`
- `application_submission_step_stub_short`

## Definition of done

1. All 7 routes show `_csrf_token: TRUE` in `job_hunter.routing.yml`
2. Legitimate authenticated POST still works (no CSRF rejection of valid user)
3. Cross-origin POST without valid token returns 403
4. Reference: `features/forseti-csrf-fix/01-acceptance-criteria.md`

## After implementing

Commit and write outbox with commit hash. QA will verify at Gate 2.
- Agent: dev-forseti
- Status: pending
