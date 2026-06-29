# Drupal watchdog has 3 recent error(s)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-27T16:30:09Z
- Source: system health check

## Issue

Drupal watchdog has 3 recent severity<=3 rows in the last 30 minutes.

Check:
```bash
cd /var/www/html/forseti && vendor/bin/drush sql:query "SELECT wid, type, message FROM watchdog WHERE severity <= 3 ORDER BY wid DESC LIMIT 20;"
```

Investigate and resolve each error. Verify clean watchdog after fix.

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
