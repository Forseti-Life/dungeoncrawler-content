# PHP Fatal errors in Apache log: forseti (251 active, 361 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T20:20:09Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Sat Apr 25 20:16:15.973105 2026] [php:notice] [pid 922635] [client 54.90.37.221:40192] Uncaught PHP Exception InvalidArgumentException: "No check has been registered for access_check.user.login_status" at /var/www/html/forseti/web/core/lib/Drupal/Core/Access/CheckProvider.php line 110

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
