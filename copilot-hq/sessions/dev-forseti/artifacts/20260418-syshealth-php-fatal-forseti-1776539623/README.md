# PHP Fatal errors in Apache log: forseti (358 occurrences)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-18T19:12:51Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Recent:
```
[Sat Apr 18 17:51:44.347698 2026] [php:notice] [pid 1582381] [client 54.90.37.221:40874] Uncaught PHP Exception Symfony\Component\Routing\Exception\RouteNotFoundException: "Route "job_hunter.profile" does not exist." at /var/www/html/forseti/web/core/lib/Drupal/Core/Routing/RouteProvider.php line 214

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
