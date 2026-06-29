# PHP Fatal errors in Apache log: forseti (266 active, 352 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T20:15:47Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Sat Apr 25 20:15:38.481267 2026] [php:notice] [pid 967189] [client 85.208.96.201:30102] Uncaught PHP Exception Drupal\Component\Plugin\Exception\PluginNotFoundException: "The "taxonomy_term" entity type does not exist." at /var/www/html/forseti/web/core/lib/Drupal/Core/Entity/EntityTypeManager.php line 139

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
