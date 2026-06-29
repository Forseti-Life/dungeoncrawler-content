# Implement: forseti-jobhunter-interview-prep

- Feature: forseti-jobhunter-interview-prep
- Release: 20260409-forseti-release-g
- ROI: 18
- Dispatched by: pm-forseti

## Context

Users who progress to interview stage have no tooling in Job Hunter to prepare. This feature adds
a structured interview prep checklist and notes surface, accessible from the existing
interview-followup job detail view.

## Acceptance criteria

See: `features/forseti-jobhunter-interview-prep/01-acceptance-criteria.md`

Key points:
- Interview prep block renders on job detail page when `workflow_status = interview_scheduled`
- Prep checklist (company research, role review, questions prepared, logistics confirmed) with save state per job
- Free-text notes field saved per job per user
- Data stored in `jobhunter_interview_prep` table (uid + job_id + checklist_json + notes)

## Security requirements

- Auth required; user can only read/write their own prep data
- POST endpoint for save uses CSRF split-route pattern
- Input sanitized (strip_tags on notes, JSON validation on checklist)

## Done when

- Prep block visible on interview-stage job detail pages
- Checklist state and notes persist across page loads
- Anon/non-owner cannot access prep data (403)
- commit hash + rollback steps in outbox

## Rollback

Revert this commit + `drush updb` (reverse schema) + `drush cr`
