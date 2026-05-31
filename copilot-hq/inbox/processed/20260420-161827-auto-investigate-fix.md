# Command

- created_at: 2026-04-20T16:18:27+00:00
- work_item: forseti-auto-investigation
- topic: auto-investigate-fix

## Command text
[AUTO-INVESTIGATION] Release KPI stagnation for forseti (forseti.life).
run_id=20260420-105935, open_issues=1, dev_status=done, unanswered_alerts=21, escalation_depth=1.

Autonomous directives (execute in order):
  1. Investigate why KPI is stagnant. Check dev outbox, run QA audit, apply any committed fixes.

Dev outbox excerpt:
- Status: done
- Summary: Fixed the sole QA permission violation — `/jobhunter/status` returning 500 for authenticated users. Root cause: `statusDashboard()` and `deadlinesList()` in `CompanyController.php` both called `Url::fromRoute('job_hunter.view_job', ...)`, a route that does not exist. The correct route name is `job_hunter.job_view` (path: `/jobhunter/jobs/{job_id}`). Both occurrences (lines 3266 and 3346) were corrected. Drupal cache was rebuilt on production (`/var/www/html/forseti`), and the route now resolves to `/jobhunter/jobs/1` as verified via `drush php:eval`. Commit: `f89d80274` on `main`.

## Next actions
- QA-forseti: retest `GET /jobhunter/status` with authenticated session — expected 200. Handoff: route `job_hunter.job_view` now resolves; the only changed file is `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`.
- Follow-up (separate item, not this release blocker): `companyJobDiscovery` controller throws 500 — `$user` argument cannot be resolved by Symfony ArgumentResolver. Needs its own routing/controller fix.

## Blockers
- None.

## ROI estimate
- ROI: 60
- Rationale: Clears the sole QA FAIL blocking release-q ship; fix was two-line surgical change with zero collateral risk and is immediately live via symlink.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260420-105935-qa-findings-forseti-life-1
- Generated: 2026-04-20T11:35:21+00:00
