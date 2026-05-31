# Implementation Notes: forseti-jobhunter-application-analytics

- Feature: forseti-jobhunter-application-analytics
- Author: ba-forseti / dev-forseti
- Date: 2026-04-13
- Status: implemented — commit 7607c362a; route fix commit 5158c8f78

## Approach

Route `job_hunter.analytics` (GET `/jobhunter/analytics`) already existed in routing.yml pointing to `ApplicationSubmissionController::analytics()` as a TODO stub. Implemented the method fully; no new routes, services, or tables required.

Query strategy — all queries uid-scoped (SEC-3/SEC-4):

1. Early empty-state check: COUNT from `jobhunter_saved_jobs WHERE uid AND archived=0`. If 0 → return empty-state render array pointing to discover page (AC-5).
2. Funnel: JOIN `jobhunter_saved_jobs` → `jobhunter_job_requirements` ON `sj.job_id = jr.id`; GROUP BY `jr.application_status`. Canonical stage order defined as a `$funnel_stages` map; unknown statuses appended at end.
3. Response rate: counted statuses from funnel_raw — `interview_scheduled`, `interview_completed`, `offer`, `accepted`, `rejected`, `confirmed` = "responded". `response_rate = responded / total_applied × 100`.
4. Source breakdown (AC-3): same JOIN; GROUP BY `jr.source`; output total/applied/responded per source; per-source response rate computed in PHP.
5. Weekly activity: `YEARWEEK(FROM_UNIXTIME(sj.created), 3)` GROUP BY ISO week, last 8 weeks. Simple inline-CSS bar chart.

Rendering: `wrapWithNavigation()` render array pattern (consistent with rest of controller). Inline CSS for bars — no JS or chart lib required.

## Data model notes (actual, post-investigation)

- `application_status` lives on `jobhunter_job_requirements`, not `jobhunter_saved_jobs`. Join is required.
- `source` also on `jobhunter_job_requirements`.
- `jobhunter_saved_jobs` columns: id, uid, job_id, created, updated, archived, deadline_date, follow_up_date.
- `jobhunter_interview_rounds` has `saved_job_id` (not job_id), `round_type`, `uid`. Not used in v1 analytics; gracefully absent.
- No new tables added.

## Funnel mapping (implemented)

## UI / rendering notes

- Keep the page server-rendered; no JavaScript dependency is required.
- Empty state should appear when the user has no saved jobs.
- Weekly activity can be a simple list or bar-style markup; no chart library is
  required for v1.
- Do not surface company names or freeform notes in the analytics widgets.

## Post-implementation fix (2026-04-18)

QA first-run (audit 20260418-172927) returned HTTP 500 on `/jobhunter/analytics` for the QA user (uid=1600, zero saved jobs). Root cause: the empty-state branch called `Url::fromRoute('job_hunter.discover')` — a route that does not exist. The correct route is `job_hunter.job_discovery` (`/jobhunter/job-discovery`).

Fix committed in `5158c8f78`. Drupal cache cleared (`drush cr`). Verified:
- Authenticated + no saved jobs: HTTP 200 with empty-state markup (`analytics-empty`)
- Authenticated + ≥1 saved job: HTTP 200 with `analytics-funnel`, `response-rate`, `source-breakdown`
- Unauthenticated: HTTP 403



```bash
# Verify page renders (requires auth cookie)
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/analytics | grep -E 'analytics-funnel|response-rate|source-breakdown'

# Actual queries for data validation
drush sql:query "SELECT jr.application_status, COUNT(*) FROM jobhunter_saved_jobs sj JOIN jobhunter_job_requirements jr ON sj.job_id=jr.id WHERE sj.uid=<uid> AND sj.archived=0 GROUP BY jr.application_status"
drush sql:query "SELECT jr.source, COUNT(*) FROM jobhunter_saved_jobs sj JOIN jobhunter_job_requirements jr ON sj.job_id=jr.id WHERE sj.uid=<uid> AND sj.archived=0 GROUP BY jr.source"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.

## Risks / notes

- `application_status` and `source` are on `jobhunter_job_requirements`; the JOIN is required. BA notes referencing `jobhunter_saved_jobs` directly for these fields were inaccurate.
- `jobhunter_interview_rounds` not used in v1 — graceful absence.
- No hard-coded strings scattered: funnel mapping is a single `$funnel_stages` PHP array in `analytics()`.
- Pre-existing cron errors (`unknown column 'external_source'`) in watchdog are unrelated to this feature; flagged as a separate issue for dev-forseti backlog.
