# PHP Fatal errors in Apache log: forseti (266 active, 349 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T20:15:32Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Sat Apr 25 20:15:07.552799 2026] [php:notice] [pid 922637] [client 54.90.37.221:57454] Uncaught PHP Exception InvalidArgumentException: "No check has been registered for access_check.user.login_status" at /var/www/html/forseti/web/core/lib/Drupal/Core/Access/CheckProvider.php line 110

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
