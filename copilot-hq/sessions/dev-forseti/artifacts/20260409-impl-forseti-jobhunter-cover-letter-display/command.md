# Implement: forseti-jobhunter-cover-letter-display

- Feature: forseti-jobhunter-cover-letter-display
- Release: 20260409-forseti-release-g
- ROI: 20
- Dispatched by: pm-forseti

## Context

The cover letter queue worker and DB table (`jobhunter_cover_letters`) already exist from prior work.
This feature adds the **display surface**: a route, controller action, and Twig template to show
generated cover letters to the user.

## Acceptance criteria

See: `features/forseti-jobhunter-cover-letter-display/01-acceptance-criteria.md`

Key points:
- `/jobhunter/cover-letter/{job_id}` GET route (auth required, user owns the job)
- Display generated cover letter text; copy-to-clipboard button
- If generation not yet complete: "generating" spinner state
- If generation failed: user-friendly error message

## Security requirements

- Route must check job ownership (user cannot view another user's cover letter)
- `_permission: 'access job hunter'` + `_user_is_logged_in: 'TRUE'`
- No CSRF required for GET route (per split-route pattern)

## Done when

- Route `/jobhunter/cover-letter/{job_id}` accessible to authenticated owner, 403 to anon + non-owner
- Cover letter text rendered; copy-to-clipboard works
- commit hash + rollback steps in outbox

## Rollback

Revert this commit + `drush cr`
