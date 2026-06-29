# Change List: 20260412-forseti-release-h

- Release ID: 20260412-forseti-release-h
- Site: forseti.life
- Activated: 2026-04-13T00:40:41+00:00
- Features: 4

## Features in scope

### forseti-jobhunter-interview-outcome-tracker
- Module: job_hunter
- Priority: high
- Status: in_progress
- Summary: Adds round-by-round interview log per saved job (`jobhunter_interview_rounds` table). Each round captures type (phone screen, technical, behavioral, final, other), outcome (pending, passed, failed, withdrawn), date conducted, and freetext notes. Visible on the saved-job detail view; drives the application status funnel used by analytics.
- Dev inbox: sessions/dev-forseti/inbox/20260413-004107-impl-forseti-jobhunter-interview-outcome-tracker
- QA inbox: sessions/qa-forseti/inbox/20260413-004107-suite-activate-forseti-jobhunter-interview-outcome-tracker

### forseti-jobhunter-offer-tracker
- Module: job_hunter
- Priority: high
- Status: in_progress
- Summary: Adds offer-tracking view to Job Hunter. When a saved job is marked "offer received," user records compensation, equity, benefits, deadline, and notes. Comparison view at `/jobhunter/offers` shows all active offers side-by-side. Data in new `jobhunter_offers` table keyed by `(uid, saved_job_id)`.
- Dev inbox: sessions/dev-forseti/inbox/20260413-004107-impl-forseti-jobhunter-offer-tracker
- QA inbox: sessions/qa-forseti/inbox/20260413-004107-suite-activate-forseti-jobhunter-offer-tracker

### forseti-jobhunter-application-analytics
- Module: job_hunter
- Priority: medium
- Status: in_progress
- Summary: Personal analytics dashboard at `/jobhunter/analytics`. Shows total applications, stage funnel (applied → phone screen → technical → offer → accepted), response rate by job source, and weekly activity chart. Pure aggregation from `jobhunter_saved_jobs`, `jobhunter_applications`, and `jobhunter_interview_rounds`. All data scoped to current user.
- Dev inbox: sessions/dev-forseti/inbox/20260413-004107-impl-forseti-jobhunter-application-analytics
- QA inbox: sessions/qa-forseti/inbox/20260413-004107-suite-activate-forseti-jobhunter-application-analytics

### forseti-jobhunter-follow-up-reminders
- Module: job_hunter
- Priority: medium
- Status: in_progress
- Summary: Per-saved-job follow-up reminders. User sets a follow-up date; `/jobhunter/my-jobs` visually flags overdue follow-ups (cards where date passed and no status change). In-app only — no email/push. Storage: `follow_up_date` column on `jobhunter_saved_jobs` or new `jobhunter_follow_ups` table — Dev-owned decision.
- Dev inbox: sessions/dev-forseti/inbox/20260413-004107-impl-forseti-jobhunter-follow-up-reminders
- QA inbox: sessions/qa-forseti/inbox/20260413-004107-suite-activate-forseti-jobhunter-follow-up-reminders

## Not activated (not fully groomed)
- `forseti-community-incident-report` — missing all grooming artifacts (only feature.md exists)
- `forseti-ai-local-llm-provider-selection` — missing 01-acceptance-criteria.md and 03-test-plan.md
- `forseti-langgraph-console-run-session` — missing 01-acceptance-criteria.md (has ac-traceability and tech-notes but wrong file names); missing 03-test-plan.md

## Notes
- KB reference: none found specific to this release pattern.
- 3 features remain in `ready` state with incomplete grooming — CEO/BA should prioritize grooming for release-i.
