# Tailoring queue has 795 error/exception lines in log

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-10T14:18:27Z
- Source: system health check

## Issue

The Drupal tailoring queue log (/var/log/drupal/tailoring_queue.log) contains 795 error/exception/failed lines.\n\nRecent errors:\n```\n UnrecognizedClientException (client): The security token included in the request is invalid. - {"message":"The security token included in the request is invalid."} 

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
