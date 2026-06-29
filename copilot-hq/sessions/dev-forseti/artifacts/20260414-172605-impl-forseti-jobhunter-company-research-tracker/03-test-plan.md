# Test Plan: forseti-jobhunter-company-research-tracker

- Feature: forseti-jobhunter-company-research-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least 2 rows in `jobhunter_companies` (global catalog, admin-managed)
- `jobhunter_company_research` table exists: `drush sql:query "DESCRIBE jobhunter_company_research"`

## Test cases

### TC-1: /jobhunter/companies renders with tracked companies (smoke)

- **Type:** functional / smoke
- **When:** GET `/jobhunter/companies` as authenticated user with 1 tracked company
- **Then:** HTTP 200; page contains `company-research` markup; company name visible
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies | grep -c 'company-research'` → ≥ 1

---

### TC-2: Submit research form — all fields saved

- **Type:** functional / happy path
- **When:** POST research form for company_id 7 with score=8, notes="Good WLB", link="https://glassdoor.com/company7"
- **Then:** HTTP 200; DB row created; page shows success flash
- **Command:** `SELECT culture_fit_score, notes FROM jobhunter_company_research WHERE uid=<uid> AND company_id=7` → 8 | 'Good WLB'

---

### TC-3: Research form pre-populates on revisit

- **Type:** functional / state persistence
- **When:** GET company detail after saving research
- **Then:** form fields pre-filled with saved values
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/<id>` | grep 'Good WLB' → match

---

### TC-4: Update existing research row (no duplicate)

- **Type:** functional / idempotency
- **When:** POST updated score=9 for company_id 7
- **Then:** row updated; `SELECT COUNT(*)... WHERE uid=<uid> AND company_id=7` → 1 (not 2)

---

### TC-5: Culture-fit score 11 rejected (boundary)

- **Type:** validation
- **When:** POST with `culture_fit_score=11`
- **Then:** HTTP 422; no DB row created/updated
- **Command:** check response status → 422

---

### TC-6: javascript: link rejected

- **Type:** security / URL validation
- **When:** POST with `research_links_json='["javascript:alert(1)"]'`
- **Then:** HTTP 422; DB has no javascript: URI
- **Command:** check response → 422; `SELECT research_links_json ... WHERE company_id=7` → no javascript: content

---

### TC-7: Unauthenticated GET returns 403

- **Type:** security / auth gate
- **When:** GET `/jobhunter/companies` with no session cookie
- **Then:** HTTP 403 or redirect to login
- **Command:** `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter/companies` → 403 or 302

---

### TC-8: Cross-user isolation

- **Type:** security / data isolation
- **When:** user B loads `/jobhunter/companies` (has no research rows)
- **Then:** empty list; no data from user A visible
- **Command:** `drush sql:query "SELECT COUNT(*) FROM jobhunter_company_research WHERE uid=<uid_B>"` → 0; page shows empty-state

---

### TC-9: CSRF required on POST route

- **Type:** security / CSRF
- **When:** POST to research save endpoint without valid CSRF token
- **Then:** HTTP 403
- **Command:** POST without `X-CSRF-Token` header → 403

---

### TC-10: No research content in watchdog debug logs

- **Type:** observability / privacy
- **When:** save research notes; then check watchdog
- **Then:** `drush watchdog:show --count=10 --severity=debug` shows no notes content in log entries
