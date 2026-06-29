- Status: blocked
- Summary: Critical production blocker: 59 jobhunter routes + 2 public content pages returning 404 in release-r (pushed 2026-05-05). Root cause assessment requires SSH access to production server to run diagnostic Drush commands (`drush pm:list --status=enabled | grep jobhunter`, route count check, error log inspection). Cannot proceed without SSH/shell access to execute diagnostics and apply fix (likely `drush pm:enable jobhunter forseti_content -y && drush cache:rebuild`).

## Blockers

**Missing SSH/shell access to production:** Diagnostics and fix require direct shell access to production server at `/home/ubuntu/forseti.life/sites/forseti/` to execute Drush commands. Specifically blocked on:
- Unable to run `drush pm:list --status=enabled | grep jobhunter`
- Unable to run `drush ev "print_r(\\Drupal::service('router.route_provider')->getAllRoutes()->count());"`
- Unable to inspect `/var/www/forseti.life/error.log`
- Unable to execute `drush pm:enable jobhunter forseti_content -y && drush cache:rebuild` (fix)

## Needs from Supervisor

**Provide SSH shell access to production server:** Either (1) grant bash/shell access in this execution context to run Drush diagnostics and apply fix on production, or (2) execute the diagnostic and fix commands on production directly and provide output (module status, route count, error logs, fix confirmation).

## Decision needed

Should dev-forseti proceed with fix via direct SSH access, or should executor/CEO run diagnostics and apply fix directly on production?

## Recommendation

**Recommend granting dev-forseti SSH access immediately.** This is a critical production blocker (ROI 999). Diagnostics are simple (3 Drush commands) and fix is straightforward (module enable + cache rebuild). Dev can execute, verify, and notify QA for re-audit all within 30min if access is provided. Alternative (executor runs commands) adds latency; direct access is faster and maintains accountability.

**Matrix issue type:** "Production outage / severe regression" — requires immediate human escalation after containment starts. This is actively blocking all Job Hunter users and core public pages.

## ROI estimate

- ROI: 999
- Rationale: Critical production blocker affecting all Job Hunter users and core public pages. SLA is 4h from FAIL verdict (deadline ~14:34 UTC, ~1.5h remaining). Fix is <30min if access provided. Unblocking requires SSH access grant.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/sessions/dev-forseti/inbox/20260506-103500-gate-r5-fail-forseti-release-r-404s
- Generated: 2026-05-06T13:04:38+00:00
