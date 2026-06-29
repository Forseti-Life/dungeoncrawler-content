# Feature Brief

- Work item id: forseti-jobhunter-company-interest-tracker
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260412-forseti-release-d
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO feature brief request 2026-04-12 (release-e)

## Summary

The global `jobhunter_companies` table is a reference catalog — it holds company metadata but has no per-user interest, culture-fit scoring, or watchlist status. This feature adds a per-user company interest layer: a new `jobhunter_company_interest` table keyed by `(uid, company_id)` that stores: interest level (1–5 stars), culture fit score (1–5), freetext research notes, research links (comma-separated URLs), and a status (researching / interviewing / rejected / accepted). Users can view and manage their company watchlist at `/jobhunter/companies/my-list`, separate from the global company catalog.

## User story

As a job seeker, I want to rate and annotate companies I'm researching so I can prioritize which ones to apply to, track my culture-fit assessment, and see at a glance which companies I'm actively engaged with versus those I've deprioritized.

## Non-goals

- Adding new companies to the global catalog (that's handled by `company_add` route)
- Sharing company watchlists with other users
- Automated culture-fit scoring from AI (separate future feature)

## Acceptance criteria

### AC-1: Company interest form accessible from company detail view

Given an authenticated user viewing a company detail page (`/jobhunter/companyresearch` or `/jobhunter/companies/{id}`), when they click "Track this company," then a form appears with: interest level (1–5 stars), culture fit score (1–5), status (select: researching/interviewing/rejected/accepted), research links (text, optional), and notes (textarea, optional). The form saves via CSRF-protected AJAX POST.

Verify: `drush sql:query "SELECT * FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=<id>"` → row exists with submitted values.

### AC-2: User's company watchlist at /jobhunter/companies/my-list

Given an authenticated user has tracked 3 or more companies, when they navigate to `/jobhunter/companies/my-list`, then all tracked companies are listed with: company name, interest stars, culture fit score, status badge, and a link to the company detail. The list is sortable by status and interest level.

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/my-list` → HTTP 200; page contains tracked company names.

### AC-3: Interest row pre-populates on revisit

Given the user has previously saved interest data for company_id 7, when they revisit the company detail view, then the form is pre-populated with their saved interest level, culture fit score, status, and notes.

Verify: form field values match `SELECT interest_level, culture_fit_score, status, notes FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=7`.

### AC-4: DB schema — jobhunter_company_interest table

Given the module update hook has run, when querying the schema, then the table exists with columns: `id` (serial PK), `uid` (int), `company_id` (int, FK to `jobhunter_companies.id`), `interest_level` (tinyint 1–5), `culture_fit_score` (tinyint 1–5, nullable), `status` (varchar 16: researching/interviewing/rejected/accepted), `research_links` (text, nullable), `notes` (text, nullable), `created` (int), `changed` (int). Unique key on `(uid, company_id)`.

Verify: `drush sql:query "DESCRIBE jobhunter_company_interest"` → all columns present.

### AC-5: Cross-user isolation

Given user A has tracked company_id 7 with interest_level=5 and user B has not tracked that company, when user B views the company detail, then no pre-populated interest data from user A is shown to user B; user B sees a blank form.

Verify: `SELECT COUNT(*) FROM jobhunter_company_interest WHERE uid=<uid_B> AND company_id=7` → 0.

## Security acceptance criteria

- All company interest routes require `_user_is_logged_in: 'TRUE'`
- CSRF token required on POST save route (split-route pattern; no CSRF on GET page)
- Controller must use `uid = currentUser()->id()` for all DB queries — no uid accepted from URL params
- Notes and research links stored as plain text; no HTML rendered without Twig auto-escaping
- Notes/links must NOT be logged to watchdog at debug severity
