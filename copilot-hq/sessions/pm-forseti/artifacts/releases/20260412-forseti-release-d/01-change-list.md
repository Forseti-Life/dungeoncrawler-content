# Change List: 20260412-forseti-release-d

- Release ID: 20260412-forseti-release-d
- Site: forseti.life
- Activated: 2026-04-12T10:09:23+00:00
- Features: 5

## Features in scope

### forseti-jobhunter-company-interest-tracker
- Module: job_hunter
- Status: in_progress
- Summary: Adds per-user company interest layer — `jobhunter_company_interest` table keyed by `(uid, company_id)` storing interest level, culture fit score, research notes, research links, and status. Users manage their company watchlist at `/jobhunter/companies/my-list`.
- Dev inbox: sessions/dev-forseti/inbox/20260412-100923-impl-forseti-jobhunter-company-interest-tracker
- QA inbox: sessions/qa-forseti/inbox/20260412-100923-suite-activate-forseti-jobhunter-company-interest-tracker

### forseti-jobhunter-company-research-tracker
- Module: job_hunter
- Status: in_progress
- Summary: Adds per-user company research overlay (`jobhunter_company_research` table keyed by `(uid, company_id)`) with a research view at `/jobhunter/companies` for personal scores and notes alongside global company data.
- Dev inbox: sessions/dev-forseti/inbox/20260412-100923-impl-forseti-jobhunter-company-research-tracker
- QA inbox: sessions/qa-forseti/inbox/20260412-100923-suite-activate-forseti-jobhunter-company-research-tracker

### forseti-jobhunter-contact-tracker
- Module: job_hunter
- Status: in_progress
- Summary: Per-user contact/referral CRM — `jobhunter_contacts` table storing people the user knows at companies, with relationship type, referral status, last contact date, and optional link to a saved job. Managed at `/jobhunter/contacts`.
- Dev inbox: sessions/dev-forseti/inbox/20260412-100923-impl-forseti-jobhunter-contact-tracker
- QA inbox: sessions/qa-forseti/inbox/20260412-100923-suite-activate-forseti-jobhunter-contact-tracker

### forseti-jobhunter-job-board-preferences
- Module: job_hunter
- Status: in_progress
- Summary: Per-user job board preferences page at `/jobhunter/preferences` backed by `jobhunter_user_preferences` table. Users toggle which job sources are active and set global search filters (remote-only, location radius, minimum salary). `SearchAggregatorService` reads preferences when building queries.
- Dev inbox: sessions/dev-forseti/inbox/20260412-100923-impl-forseti-jobhunter-job-board-preferences
- QA inbox: sessions/qa-forseti/inbox/20260412-100923-suite-activate-forseti-jobhunter-job-board-preferences

### forseti-jobhunter-resume-version-labeling
- Module: job_hunter
- Status: in_progress
- Summary: Adds `version_label` and `version_notes` to `jobhunter_job_seeker_resumes`, a `source_resume_id` FK on `jobhunter_applications`, and UI to label resumes and view which applications used each version.
- Dev inbox: sessions/dev-forseti/inbox/20260412-100924-impl-forseti-jobhunter-resume-version-labeling
- QA inbox: sessions/qa-forseti/inbox/20260412-100924-suite-activate-forseti-jobhunter-resume-version-labeling

## Notes
- 6 features in the ready pool had missing `01-acceptance-criteria.md` and/or `03-test-plan.md` and could not be activated: `forseti-jobhunter-application-analytics`, `forseti-jobhunter-contact-referral-tracker`, `forseti-jobhunter-follow-up-reminders`, `forseti-jobhunter-interview-outcome-tracker`, `forseti-jobhunter-offer-tracker`, `forseti-jobhunter-resume-version-tracker`. These need full grooming before they can be scoped into a future release.
- KB reference: none found specific to this release pattern.
