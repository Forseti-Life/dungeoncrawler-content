# PHP Fatal errors in Apache log: forseti (1 occurrences)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-17T22:00:49Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Recent:
```
[Fri Apr 17 19:23:28.857588 2026] [php:error] [pid 4173266] [client 105.72.21.233:53654] PHP Fatal error:  Uncaught Error: Class "Drupal\Core\DrupalKernel" not found in /var/www/html/forseti/web/index.php:16
Stack trace:
#0 {main}
  thrown in /var/www/html/forseti/web/index.php on line 16
```

Investigate and fix. Verify site returns HTTP 200 after fix.

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
