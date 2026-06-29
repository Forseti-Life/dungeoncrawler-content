# Acceptance Criteria: forseti-jobhunter-company-interest-tracker

- Feature: forseti-jobhunter-company-interest-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Per-user company interest layer on top of the global `jobhunter_companies` catalog. New table `jobhunter_company_interest` keyed by `(uid, company_id)` storing: interest level (1–5), culture fit score (1–5, nullable), status (researching/interviewing/rejected/accepted), research links (text), and notes. A user watchlist page at `/jobhunter/companies/my-list` surfaces all tracked companies for that user.

## Acceptance criteria

### AC-1: Company interest form saves a new interest row

**Given** an authenticated user viewing a company detail page,
**When** they submit the "Track this company" form with `interest_level=4`, `culture_fit_score=3`, `status=researching`, and `notes="Strong culture match"`,
**Then** a row is created in `jobhunter_company_interest` with those values and `uid=currentUser()->id()`.

**Verify:**
```sql
SELECT interest_level, culture_fit_score, status, notes
FROM jobhunter_company_interest
WHERE uid=<uid> AND company_id=<id>;
-- Expected: 4 | 3 | researching | Strong culture match
```

---

### AC-2: User watchlist page at /jobhunter/companies/my-list

**Given** an authenticated user has tracked 2 or more companies,
**When** they navigate to `/jobhunter/companies/my-list`,
**Then** the page renders HTTP 200 with a list showing at minimum: company name, interest level, status badge, and a link to the company detail for each tracked company.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/my-list` → HTTP 200; page contains `company-watchlist` markup and tracked company names.

---

### AC-3: Interest form pre-populates on revisit

**Given** a prior interest row exists for (uid, company_id=7),
**When** the user revisits the company detail page,
**Then** the form fields are pre-populated with the saved interest level, culture fit score, status, and notes.

**Verify:** `SELECT interest_level, culture_fit_score, status FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=7` → matches rendered form field values.

---

### AC-4: DB schema — jobhunter_company_interest table

**Given** the module update hook has run,
**When** querying the schema,
**Then** the table `jobhunter_company_interest` exists with columns: `id` (serial PK), `uid` (int), `company_id` (int), `interest_level` (tinyint 1–5), `culture_fit_score` (tinyint 1–5, nullable), `status` (varchar 16), `research_links` (text, nullable), `notes` (text, nullable), `created` (int), `changed` (int). Unique key on `(uid, company_id)`.

**Verify:** `drush sql:query "DESCRIBE jobhunter_company_interest"` → all columns present; `SHOW INDEX FROM jobhunter_company_interest` → unique key on `(uid, company_id)`.

---

### AC-5: Cross-user isolation — user B cannot see user A's interest data

**Given** user A has tracked company_id=7 with `interest_level=5`,
**When** user B views the same company detail page,
**Then** user B sees an empty/default form (no pre-populated data from user A); user B's watchlist does not include company 7.

**Verify:** `SELECT COUNT(*) FROM jobhunter_company_interest WHERE uid=<uid_B> AND company_id=7` → 0.

---

## Security acceptance criteria

### SEC-1: Authentication required
All company interest routes (`GET` watchlist, `POST` save) must require `_user_is_logged_in: 'TRUE'` in routing.yml. Unauthenticated requests receive HTTP 403.

### SEC-2: CSRF protection on POST
Save route is POST-only with `_csrf_token: 'TRUE'` (split-route pattern; GET page has no CSRF requirement to avoid GET 403 regression).

**Verify:** `grep -A5 "job_hunter.company_interest" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep "_csrf_token"` → present on POST entry only.

### SEC-3: UID scoped in all DB queries
All selects and inserts use `WHERE uid = \Drupal::currentUser()->id()`. No uid parameter accepted from URL or request body.

### SEC-4: Input sanitization
Notes and research links stored as plain text; no HTML. Display uses Twig auto-escaping only (no `|raw`).

### SEC-5: No PII in logs
Notes content must NOT be logged to watchdog at any severity. Log only `uid` and `company_id` for save events.
