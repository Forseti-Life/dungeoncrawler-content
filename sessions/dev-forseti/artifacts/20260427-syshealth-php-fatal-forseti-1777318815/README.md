# PHP Fatal errors in Apache log: forseti (7 active, 14 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-27T19:40:09Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Mon Apr 27 19:30:46.780322 2026] [php:notice] [pid 2362539] [client 50.232.247.82:43667] Uncaught PHP Exception TypeError: "Drupal\drupal_langgraph\Controller\LangGraphConsoleController::withFlowVisualization(): Argument #2 ($flow) must be of type array, null given, called in /home/ubuntu/forseti.life/drupal-langgraph/src/Controller/LangGraphConsoleController.php on line 900" at /home/ubuntu/forseti.life/drupal-langgraph/src/Controller/LangGraphConsoleController.php line 1150, referer: https://forseti.life/admin/reports/drupal-langgraph/langgraph-console/flows/release_cycle_automation/test/validate-structure

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
