# Feature Brief

- Work item id: forseti-jobhunter-profile
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260406-forseti-release-b
- Priority: P0
- Feature type: stabilization
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti

## Goal
Improve the JobHunter profile page so resume upload/parsing and consolidated profile editing are correct, safe, and verifiable.

## Non-goals
- New resume parsing models or new external integrations.

## Acceptance Criteria (link)
- sessions/pm-forseti/artifacts/20260220-job-hunter-profile-review/pm-review.md

## Risks
- Access control leaks across users.
- Data integrity issues in custom tables.

## Latest updates

- 2026-04-06: Scoped into release — suite activation sent to QA.

- 2026-04-05: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-03-27: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-02-26: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-02-26: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-02-20: PM review recorded; improvement loops seeded (lesson + proposal).
- 2026-04-08: Deferred from 20260407-forseti-release-b — no dev implementation or Gate 2 APPROVE before release close.

## Security acceptance criteria
- Authentication/permission surface: All profile routes (view, edit, resume upload) are auth-required; users may only access their own profile data (per-user ACL enforced in controllers).
- CSRF expectations: Profile edit and resume upload POST routes must use split-route pattern with `_csrf_token: TRUE` on POST-only entries.
- Input validation: Resume file uploads restricted to allowed MIME types (PDF, DOCX); file size limits enforced; filenames sanitized.
- PII/logging constraints: Resume content and parsed fields (name, email, phone) must not be logged to watchdog or Apache access logs.
