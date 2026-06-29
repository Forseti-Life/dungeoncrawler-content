- Status: done
- Completed: 2026-04-13T01:14:08Z

# Suite Activation: forseti-jobhunter-application-analytics

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-13T00:41:07+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-application-analytics"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-application-analytics/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-application-analytics-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-application-analytics",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-application-analytics"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-application-analytics-<route-slug>",
     "feature_id": "forseti-jobhunter-application-analytics",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-application-analytics",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
