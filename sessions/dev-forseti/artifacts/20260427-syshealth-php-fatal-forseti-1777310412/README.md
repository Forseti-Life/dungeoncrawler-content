# PHP Fatal errors in Apache log: forseti (3 active, 4 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-27T17:20:09Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Mon Apr 27 17:11:59.585205 2026] [php:notice] [pid 3345162] [client 54.90.37.221:51772] Uncaught PHP Exception RuntimeException: "Unable to write runtime request artifact: /home/ubuntu/forseti.life/tmp/langgraph-control-requests/feature_progress_pipeline/20260427-171159-manual-run.json" at /home/ubuntu/forseti.life/drupal-langgraph/src/Form/ProcessFlowRuntimeRequestForm.php line 106

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
