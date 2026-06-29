# Acceptance Criteria: forseti-jobhunter-application-analytics

- Feature: forseti-jobhunter-application-analytics
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Add a read-only personal analytics dashboard at `/jobhunter/analytics` that
aggregates the current user's application data from `jobhunter_saved_jobs`,
`jobhunter_applications`, and `jobhunter_interview_rounds` when present. The
dashboard should show total applications, a stage funnel, response rate by job
source, and a weekly activity summary without exposing other users' data.

## Acceptance criteria

### AC-1: Analytics dashboard renders at /jobhunter/analytics

**Given** an authenticated user with at least 5 saved jobs in various statuses,
**When** they navigate to `/jobhunter/analytics`,
**Then** the page renders with at minimum: total application count, a stage
funnel showing counts at each status level (applied, phone screen, technical
interview, offer, accepted/rejected), and a response rate percentage.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/analytics | grep -Eq 'analytics-funnel|response-rate'`

---

### AC-2: Stage funnel counts are accurate

**Given** the user has 10 saved jobs: 5 at status `applied`, 3 at `phone
screen`, 1 at `technical`, 1 at `offer`,
**When** the analytics page loads,
**Then** the funnel displays exactly those counts at each stage.

**Verify:**
```sql
SELECT status, COUNT(*)
FROM jobhunter_saved_jobs
WHERE uid=<uid>
GROUP BY status;
```

---

### AC-3: Response rate by job source is displayed

**Given** the user has saved jobs with `source` values such as `google_jobs`,
`manual`, and `adzuna`,
**When** the analytics page loads,
**Then** a breakdown section shows response rate per source using the user's own
saved-job data only.

**Verify:** page contains `source-breakdown`; compare rendered rows to
`SELECT source, COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid> GROUP BY source`.

---

### AC-4: All data is scoped to current user

**Given** users A and B both have analytics data,
**When** user A views `/jobhunter/analytics`,
**Then** only user A's application data is counted and rendered.

**Verify:** all controller queries include `uid = currentUser()->id()` or an
equivalent ownership join.

---

### AC-5: Empty state renders cleanly for new users

**Given** an authenticated user with 0 saved jobs,
**When** they navigate to `/jobhunter/analytics`,
**Then** the page renders HTTP 200 with an empty-state prompt pointing them to
job discovery instead of throwing warnings or errors.

**Verify:** response contains `"You haven't saved any jobs yet"` or equivalent
empty-state copy; watchdog shows no new warnings for the request.

---

## Security acceptance criteria

### SEC-1: Authentication required

`/jobhunter/analytics` requires `_user_is_logged_in: 'TRUE'`.

### SEC-2: No CSRF surface introduced

This dashboard is read-only. No POST endpoints are added for this feature, so
no CSRF token handling is required.

### SEC-3: Strict uid scoping

No uid parameter may be accepted from URL or request input. All aggregation is
derived from `currentUser()->id()`.

### SEC-4: Aggregate-only rendering

Do not render raw notes, resume text, or other user-authored free text in the
analytics view. Show counts, percentages, labels, and dates only.

### SEC-5: No sensitive debug logging

Do not log individual job titles, company names, or notes content at debug
severity for analytics requests.
