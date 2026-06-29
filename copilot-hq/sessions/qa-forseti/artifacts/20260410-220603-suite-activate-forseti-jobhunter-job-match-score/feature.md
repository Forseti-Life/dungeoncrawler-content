# Feature Brief

- Work item id: forseti-jobhunter-job-match-score
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260410-forseti-release-d
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: low
- Source: release-d backlog (dispatched 2026-04-10)

## Summary

Users at `/jobhunter/my-jobs` see saved jobs but have no indication of how well a job matches their profile. This feature surfaces a simple match score (0–100%) per saved job based on keyword overlap between the user's skills (`jobhunter_job_seeker.skills`) and the job's requirements/description (`jobhunter_job_requirements`). The `ProfileCompletenessService` already extracts skills data; this feature adds a lightweight scoring pass and displays the result as a badge on each saved-job card.

## Goal

On `/jobhunter/my-jobs` each saved-job card displays a match score badge ("78% match"). Score is computed server-side on page load (or on-demand per job) using keyword intersection between profile skills and job description tokens.

## Non-goals

- Semantic/embedding-based matching (future)
- Sorting/filtering by match score (follow-on feature)
- Match explanation/breakdown UI (follow-on feature)

## Security acceptance criteria

See `01-acceptance-criteria.md` § Security acceptance criteria.
