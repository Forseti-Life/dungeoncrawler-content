# Feature Brief

- Work item id: forseti-jobhunter-application-analytics
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260412-forseti-release-d
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO feature brief request 2026-04-12

## Summary

Job seekers accumulate significant application data over time but have no visibility into their own effectiveness: how many applications were submitted, what percentage reached a phone screen, what sources yield the most responses. This feature adds a personal analytics dashboard at `/jobhunter/analytics` that aggregates the user's own application data from `jobhunter_saved_jobs`, `jobhunter_applications`, and (when available) `jobhunter_interview_rounds`. The dashboard shows: total applications, stage funnel (applied → phone screen → technical → offer → accepted), response rate by job source (Google Jobs, manual, AdzunaAPI), and a simple weekly activity chart. All data is scoped to the current user. No AI or ML required — pure aggregation from existing tables.

## User story

As an active job seeker, I want to see how my job search is performing — response rates, funnel stages, and weekly activity — so I can adjust my approach and focus on what's working.

## Non-goals

- Benchmarking against other users or industry averages (privacy concern)
- Real-time streaming updates (page-load aggregation is sufficient)
- CSV/PDF export (separate analytics export feature)
- Predictive scoring or AI-generated recommendations (separate AI feature)

## Acceptance criteria

### AC-1: Analytics dashboard renders at /jobhunter/analytics

Given an authenticated user with at least 5 saved jobs in various statuses, when they navigate to `/jobhunter/analytics`, then the page renders with at minimum: total application count, a stage funnel showing counts at each status level (applied, phone screen, technical interview, offer, accepted/rejected), and a response rate percentage ((non-applied statuses / total) × 100).

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/analytics` → HTTP 200; page contains `analytics-funnel` and `response-rate` markup.

### AC-2: Stage funnel counts are accurate

Given the user has 10 saved jobs: 5 at status "applied," 3 at "phone screen," 1 at "technical," 1 at "offer," when the analytics page loads, then the funnel displays exactly those counts at each stage.

Verify:
```sql
SELECT status, COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid> GROUP BY status;
-- Compare to rendered funnel counts on page
```

### AC-3: Response rate by job source is displayed

Given the user has saved jobs with `source` values of "google_jobs," "manual," and "adzuna," when the analytics page loads, then a breakdown section shows response rate (phone screen or beyond) per source.

Verify: page contains `source-breakdown` section; each source row matches `SELECT source, COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid> GROUP BY source`.

### AC-4: All data is scoped to current user — no cross-user leakage

Given users A and B both have analytics data, when user A views `/jobhunter/analytics`, then only user A's application data is counted. No aggregated totals include user B's data.

Verify: all DB queries in the analytics controller use `WHERE uid = \Drupal::currentUser()->id()`.

### AC-5: Empty state — graceful display for new users

Given an authenticated user with 0 saved jobs, when they navigate to `/jobhunter/analytics`, then the page renders without error and shows a prompt to start searching ("You haven't saved any jobs yet. Start at /jobhunter/discover").

Verify: load page as user with no saved jobs → HTTP 200, no PHP warnings in watchdog, empty-state message visible.

## Security acceptance criteria

- `/jobhunter/analytics` route requires `_user_is_logged_in: 'TRUE'`
- No POST endpoints needed (read-only dashboard); no CSRF token required
- All DB queries scoped with `uid = currentUser()->id()`; no uid parameter accepted from URL
- No raw application text or notes data rendered in analytics (counts only)
- No logging of individual job titles or company names at debug severity
