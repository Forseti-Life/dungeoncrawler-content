# PHP Fatal errors in Apache log: forseti (6 active, 7 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-27T17:30:08Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Mon Apr 27 17:22:34.392412 2026] [php:notice] [pid 3964193] [client 54.90.37.221:51478] Uncaught PHP Exception Error: "Call to undefined method Drupal\drupal_langgraph\Controller\LangGraphConsoleController::flowWorkspacePage()" at /home/ubuntu/forseti.life/drupal-langgraph/src/Controller/LangGraphConsoleController.php line 1144

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
