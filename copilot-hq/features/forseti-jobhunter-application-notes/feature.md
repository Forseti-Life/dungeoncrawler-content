# Feature Brief

- Work item id: forseti-jobhunter-application-notes
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 2
- Group: application
- Group Title: Application Management
- Group Sort: 3
- Status: shipped
- Release: 20260410-forseti-release-f
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: release-d backlog (dispatched 2026-04-10)

## Summary

Job seekers tracking applications at `/jobhunter/my-jobs` have no place to record recruiter/hiring manager contact details or freetext notes per application. This feature adds a contact log and notes field per saved job, stored in a new `jobhunter_application_notes` table. Users can inline-edit notes from the job detail view without leaving the page.

Note: `jobhunter_interview_notes` (shipped in release-g) is interview-prep notes; this feature is distinct — it is for **application-tracking** notes (who to call, what was discussed, next steps).

## Goal

From the saved-job detail view, the user can:
1. Enter/edit: hiring manager name, contact email, last contact date, freetext notes
2. Save via CSRF-protected AJAX endpoint
3. See notes pre-populated on revisit

## Non-goals

- Calendar/reminder integration (future)
- Shared notes across users
- Interview prep notes (already handled by `jobhunter_interview_notes`)

## Security acceptance criteria

See `01-acceptance-criteria.md` § Security acceptance criteria.
