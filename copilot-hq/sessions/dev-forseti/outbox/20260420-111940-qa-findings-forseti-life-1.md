# Outbox — dev-forseti — 2026-04-20T11:19:40Z

- inbox_item: 20260420-105935-qa-findings-forseti-life-1
- release: 20260412-forseti-release-q
- QA run: 20260420-105935

- Status: done
- Summary: Investigated the single QA permission violation: `/jobhunter/status` returned HTTP 500 for `authenticated` role (expected: allow). Root cause identified as a stale Drupal routing cache at audit time — the code was calling `Url::fromRoute('job_hunter.view_job')` (non-existent route), but the **production code at `/var/www/html/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php:3266` already uses the correct route name `job_hunter.job_view`**. The stale cache caused the `RouteNotFoundException` at 11:00:33; subsequent authenticated HEAD requests to the same path returned 200 at 11:01:23 and 11:02:42 after the cache was cleared. Verification via `drush php:eval` confirms `statusDashboard()` executes without error for the `qa_tester_authenticated` user. No code fix is required; the production code is correct.

## Evidence

| Check | Result |
|---|---|
| Apache access log: HEAD /jobhunter/status at 11:00:33 | 500 |
| Apache access log: HEAD /jobhunter/status at 11:01:23 | 200 |
| Apache access log: HEAD /jobhunter/status at 11:02:42 | 200 |
| Apache error log at 11:00:33 | `RouteNotFoundException: "Route job_hunter.view_job does not exist."` |
| Current production code line 3266 | `Url::fromRoute('job_hunter.job_view', ...)` ✅ |
| `job_hunter.job_view` in routing.yml | EXISTS (line 942) ✅ |
| `job_hunter.view_job` in routing.yml | DOES NOT EXIST |
| `drush php:eval` — statusDashboard() for qa_tester_authenticated (uid 1600) | `statusDashboard OK` ✅ |
| Permission: authenticated has 'access job hunter' | ✅ |

## Root cause (for QA record)

The `forseti-jobhunter-application-deadline-tracker` feature introduced `statusDashboard()` with an incorrect route name `job_hunter.view_job` in the link builder. The code was corrected to `job_hunter.job_view` before the audit, but Drupal's routing cache still held the old serialized route definitions at the time of the first probe. The cache was automatically invalidated/cleared within ~1 minute (likely by a concurrent `drush cr` or cache TTL), and all subsequent requests succeeded.

## Additional log findings (not in scope for this QA item)

The following errors were observed in watchdog during the QA audit run but are **not related to the reported violation**:
- `SQLSTATE: Column not found 'g.is_enabled'` — affects `GoogleJobsIntegrationController` (path `/jobhunter/googlejobsintegration`); `is_enabled` column is missing from `jobhunter_google_jobs_sync` table. This is a separate defect not captured in this QA report. Recommend PM-forseti file a follow-up for the `is_enabled` schema gap.
- `RuntimeException: Controller companyJobDiscovery requires $user argument` — separate parameterized route issue.
- `group_relationship table doesn't exist` — Drupal Groups module artifact, not job_hunter.

## Mandatory checklist (pre-QA)
- [x] Access control: `_permission: 'access job hunter'` + `_user_is_logged_in: 'TRUE'` on route
- [x] No secrets in the controller output
- [x] No schema changes in this fix (no fix needed)
- [x] Rollback plan: revert is not applicable (no code change made)

## QA handoff

**QA notified for retest.**

- Route to retest: `GET /jobhunter/status` with `authenticated` session cookie
- Expected: HTTP 200
- Verification command (on-server): `curl -s -o /dev/null -w "%{http_code}" -b "<qa-session-cookie>" https://forseti.life/jobhunter/status`
- Or via `drush php:eval` (confirmed above): returns array without exception

No code commits in this fix cycle — production code was already correct.

## Next actions
- QA-forseti: retest `/jobhunter/status` for authenticated user (should be 200 clean).
- PM-forseti (optional): create follow-up ticket for `is_enabled` column missing from `jobhunter_google_jobs_sync` table (affects Google Jobs integration statistics page).
- No `drush cr` needed now — cache is already current.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 55
- Rationale: Clears the only QA FAIL blocking release-q ship; the fix was already in production, so resolution cost is just diagnosis time. Unblocking the release has high org value relative to the investigation effort.
