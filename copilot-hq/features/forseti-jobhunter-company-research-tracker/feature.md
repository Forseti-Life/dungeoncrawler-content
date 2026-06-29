# Feature Brief

- Work item id: forseti-jobhunter-company-research-tracker
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 4
- Group: contacts-companies
- Group Title: Contacts & Companies
- Group Sort: 3
- Status: done
- Release: 20260412-forseti-release-l
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO feature brief request 2026-04-12 (release-e)

## Summary

The `jobhunter_companies` table already exists and stores global company records (name, website, industry, careers page URL, notes). However, users have no per-user way to mark companies as "interested," assign a culture-fit score, add personal research notes, or attach research links. This feature adds a per-user company research overlay (`jobhunter_company_research` table keyed by `(uid, company_id)`) and a research view at `/jobhunter/companies` where users can browse their tracked companies with their personal scores and notes alongside the global company data. The global `jobhunter_companies` table remains admin-managed; this feature adds user-layer annotations only.

## User story

As a job seeker doing proactive company research, I want to track companies I'm interested in, record my culture-fit assessment and research links, so that I can prioritize applications and reference my research during interviews.

## Non-goals

- Admin management of the global company catalog (existing functionality)
- Scraping company data from external APIs (separate research automation feature)
- Sharing company research with other users

## Acceptance criteria

### AC-1: Company research list at /jobhunter/companies

Given an authenticated user, when they navigate to `/jobhunter/companies`, then the page displays a list of companies they have tracked, showing: company name, industry, culture-fit score (0–10, user-set), last research date, and a link to the detail view. Untracked companies from the global catalog are not shown.

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies` → HTTP 200; page contains tracked company entries.

### AC-2: Add/edit research overlay for a company

Given an authenticated user viewing a company (from the global catalog or a saved-job's company link), when they click "Track this company," then a form appears with fields: culture-fit score (integer 0–10), research notes (textarea, optional), research links (one or more URL fields, optional). On save, a row is created/updated in `jobhunter_company_research`.

Verify: `drush sql:query "SELECT culture_fit_score, notes FROM jobhunter_company_research WHERE uid=<uid> AND company_id=<id>"` → returns saved values.

### AC-3: DB schema — jobhunter_company_research table

Given the module update hook has run, when querying the schema, then the table `jobhunter_company_research` exists with columns: `id` (serial PK), `uid` (int), `company_id` (int FK to jobhunter_companies), `culture_fit_score` (int 0–10, nullable), `notes` (text, nullable), `research_links_json` (text, nullable — JSON array of URLs), `created` (int), `changed` (int). Unique key on `(uid, company_id)`.

Verify: `drush sql:query "DESCRIBE jobhunter_company_research"` → all expected columns.

### AC-4: Research data is per-user — no cross-user leakage

Given user A has tracked company_id 5 with score 8 and user B has not tracked company_id 5, when user B visits `/jobhunter/companies`, then company_id 5 does not appear in user B's list.

Verify: `drush sql:query "SELECT COUNT(*) FROM jobhunter_company_research WHERE uid=<uid_B> AND company_id=5"` → 0.

### AC-5: Culture-fit score validation

Given an authenticated user submits a culture-fit score of 11, when the form is submitted, then the endpoint returns HTTP 422 with an error; no row is created/updated.

Verify: POST with `culture_fit_score=11` → 422 response with validation error message.

## Security acceptance criteria

- `/jobhunter/companies` route and all company-research POST endpoints require `_user_is_logged_in: 'TRUE'`
- CSRF token required on POST save route (split-route pattern; no CSRF on GET)
- Controller verifies `uid = currentUser()->id()` on all DB writes; no uid parameter accepted from URL
- `research_links_json` field stored as plain JSON string; URLs must be validated as valid HTTP/HTTPS URLs before storage (no javascript: or data: URIs)
- Notes field stored as plain text; Twig auto-escaping on display (no `|raw`)
- Research links rendered as anchor tags with `rel="noopener noreferrer"` and `target="_blank"` to prevent tab-napping
