# Tailoring queue has 64 error/exception lines in log (recent)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-23T22:00:09Z
- Source: system health check

## Issue

The Drupal tailoring queue log (/var/log/drupal/tailoring_queue.log) contains 64 recent error/exception/failed lines.

Recent errors:
```
 [warning] include_once(/var/www/html/forseti/web/modules/custom/forseti_games/forseti_games.module): Failed to open stream: No such file or directory Extension.php:162

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
- Status: pending
