# PHP Fatal errors in Apache log: forseti (80 active, 83 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T19:45:26Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Sat Apr 25 19:44:49.905187 2026] [php:notice] [pid 3385351] [client 185.191.171.6:36036] Uncaught PHP Exception Drupal\Component\Plugin\Exception\PluginNotFoundException: "The "taxonomy_term" entity type does not exist." at /var/www/html/forseti/web/core/lib/Drupal/Core/Entity/EntityTypeManager.php line 139

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
