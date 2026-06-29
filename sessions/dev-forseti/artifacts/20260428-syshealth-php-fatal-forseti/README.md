# PHP Fatal errors in Apache log: forseti (1 active, 1 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-28T16:40:09Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Tue Apr 28 16:30:55.850420 2026] [php:notice] [pid 960009] [client 17.241.75.144:43274] Uncaught PHP Exception Drupal\Core\Entity\Query\QueryException: "'field_total_power' not found" at /var/www/html/forseti/web/core/lib/Drupal/Core/Entity/Query/Sql/Tables.php line 376
```

Investigate and fix. Verify site returns HTTP 200 after fix.

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
- Status: pending
