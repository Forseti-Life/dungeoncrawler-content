# Test Plan: forseti-jobhunter-application-analytics

- Feature: forseti-jobhunter-application-analytics
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one authenticated test user with saved-job data
- Optional: `jobhunter_interview_rounds` table present for enriched funnel checks

## Test cases

### TC-1: Analytics page renders for authenticated user

- **Type:** functional / smoke
- **When:** GET `/jobhunter/analytics`
- **Then:** HTTP 200 and page contains analytics summary markup
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/analytics | grep -Eq 'analytics-funnel|response-rate'`

---

### TC-2: Funnel counts match saved-job statuses

- **Type:** functional / data accuracy
- **Given:** user has saved jobs in multiple statuses
- **When:** analytics page loads
- **Then:** displayed funnel counts match DB counts for that user
- **Command:** `drush sql:query "SELECT status, COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid> GROUP BY status"`

---

### TC-3: Source breakdown matches saved-job source counts

- **Type:** functional / aggregation
- **When:** analytics page loads for a user with multiple job sources
- **Then:** each source row matches the current user's DB counts
- **Command:** `drush sql:query "SELECT source, COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid> GROUP BY source"`

---

### TC-4: Empty state renders for user with no saved jobs

- **Type:** functional / empty state
- **When:** GET analytics page as user with zero saved jobs
- **Then:** HTTP 200 with empty-state prompt; no PHP warnings
- **Command:** inspect response for empty-state copy and watchdog for no new errors

---

### TC-5: Cross-user isolation

- **Type:** security / data isolation
- **Given:** users A and B have different saved-job counts
- **When:** each user loads `/jobhunter/analytics`
- **Then:** each user sees only their own totals
- **Command:** compare rendered counts against per-user DB queries

---

### TC-6: Unauthenticated access blocked

- **Type:** security / auth gate
- **When:** GET `/jobhunter/analytics` without session cookie
- **Then:** HTTP 403 or redirect to login
- **Command:** `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter/analytics`

---

### TC-7: No raw notes or resume text rendered

- **Type:** security / privacy
- **When:** analytics page loads
- **Then:** page shows aggregate values only, not notes or resume content
- **Command:** inspect rendered HTML for absence of known note text / resume fragments
