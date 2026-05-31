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
