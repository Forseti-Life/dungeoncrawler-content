- Status: done
- Completed: 2026-04-12T10:50:14Z

# Suite Activation: forseti-jobhunter-company-interest-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-12T10:09:23+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-company-interest-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-company-interest-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-company-interest-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-company-interest-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-company-interest-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-company-interest-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-company-interest-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-company-interest-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-company-interest-tracker

- Feature: forseti-jobhunter-company-interest-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one company in `jobhunter_companies` (e.g., company_id=1)
- `jobhunter_company_interest` table exists: `drush sql:query "DESCRIBE jobhunter_company_interest"`

## Test cases

### TC-1: Save new company interest row (smoke)

- **Type:** functional / smoke
- **Given:** authenticated user with no prior interest row for company_id=1
- **When:** submit "Track this company" form with `interest_level=3`, `status=researching`
- **Then:** HTTP 200; row created in `jobhunter_company_interest`
- **Verify:** `SELECT interest_level, status FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=1` → `3 | researching`

---

### TC-2: Company watchlist page renders

- **Type:** functional / smoke
- **Given:** user has tracked 1+ companies
- **When:** GET `/jobhunter/companies/my-list`
- **Then:** HTTP 200; page contains tracked company names and status badges
- **Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/my-list` → HTTP 200, contains `company-watchlist`

---

### TC-3: Form pre-populates on revisit

- **Type:** functional / state persistence
- **Given:** prior interest row with `interest_level=4` for company_id=1
- **When:** GET company detail page for company 1
- **Then:** form field `interest_level` shows 4; `status` shows saved value
- **Verify:** page HTML contains pre-selected value matching DB row

---

### TC-4: Update existing row (idempotency)

- **Type:** functional / update path
- **Given:** prior interest row with `status=researching`
- **When:** submit form with `status=interviewing`
- **Then:** row updated (not duplicated); `SELECT COUNT(*) FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=1` → 1
- **Verify:** `SELECT status FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=1` → `interviewing`

---

### TC-5: Cross-user isolation

- **Type:** security / data isolation
- **Given:** user A has interest row for company_id=1; user B has none
- **When:** user B loads company detail page for company 1
- **Then:** form is empty (no pre-populated data); user B's watchlist is empty
- **Verify:** `SELECT COUNT(*) FROM jobhunter_company_interest WHERE uid=<uid_B> AND company_id=1` → 0

---

### TC-6: Unauthenticated POST returns 403

- **Type:** security / auth gate
- **When:** POST to company interest save endpoint without session cookie
- **Then:** HTTP 403
- **Verify:** `curl -s -o /dev/null -w "%{http_code}" -X POST https://forseti.life/jobhunter/company-interest` → `403`

---

### TC-7: CSRF token required on POST

- **Type:** security / CSRF
- **Given:** authenticated session
- **When:** POST without valid CSRF token
- **Then:** HTTP 403
- **Verify:** POST without `X-CSRF-Token` header → 403

---

### TC-8: XSS — notes stored as plain text

- **Type:** security / input sanitization
- **Given:** authenticated user
- **When:** submit `notes='<script>alert(1)</script>'`
- **Then:** DB row stores stripped plain text; rendered page does not execute script
- **Verify:** `SELECT notes FROM jobhunter_company_interest WHERE uid=<uid>` → no `<script>` tag

---

### TC-9: Unauthenticated GET of watchlist returns 403

- **Type:** security / auth gate
- **When:** GET `/jobhunter/companies/my-list` without session cookie
- **Then:** HTTP 403 or redirect to login
- **Verify:** `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter/companies/my-list` → `403` or `302`

### Acceptance criteria (reference)

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
