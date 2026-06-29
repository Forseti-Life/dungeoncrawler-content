# Tailoring queue has 28 error/exception lines in log (recent)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-28T18:56:46Z
- Source: system health check

## Issue

The Drupal tailoring queue log (/var/log/drupal/tailoring_queue.log) contains 28 recent error/exception/failed lines.

Recent errors:
```
 [error]  Queue not found: job_hunter_resume_tailoring 

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
- Status: pending
