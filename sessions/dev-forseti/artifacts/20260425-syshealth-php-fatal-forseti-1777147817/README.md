# PHP Fatal errors in Apache log: forseti (251 active, 308 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T20:10:09Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Sat Apr 25 20:10:00.788540 2026] [php:notice] [pid 922635] [client 66.249.68.32:41717] Uncaught PHP Exception Drupal\Component\Plugin\Exception\PluginNotFoundException: "The "taxonomy_term" entity type does not exist." at /var/www/html/forseti/web/core/lib/Drupal/Core/Entity/EntityTypeManager.php line 139

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
