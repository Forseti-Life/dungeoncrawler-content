# Feature Brief

- Work item id: forseti-jobhunter-tailoring-feedback
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260410-forseti-release-d
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: release-d backlog (dispatched 2026-04-10)

## Summary

Users who generate tailored resumes at `/jobhunter/tailor-resume` have no way to rate or flag the output quality. This feature adds a thumbs-up/thumbs-down widget (with optional freetext note) on the tailoring result display. Ratings are stored in a new `jobhunter_tailoring_feedback` table and can be used to tune prompts over time.

The AI already stores a `confidence_score` (0–100) on `jobhunter_tailored_resumes` — this feature adds the complementary **user** signal.

## Goal

After generating a tailored resume, the user can submit a 👍/👎 rating and an optional note. Rating is stored per `(uid, tailored_resume_id)`. The result page updates in-place via AJAX (no full reload).

## Non-goals

- Automatic prompt tuning or training loops (separate future feature)
- Rating of cover letters (separate scope)
- Admin dashboard for aggregate ratings (separate scope)

## Security acceptance criteria

See `01-acceptance-criteria.md` § Security acceptance criteria.
