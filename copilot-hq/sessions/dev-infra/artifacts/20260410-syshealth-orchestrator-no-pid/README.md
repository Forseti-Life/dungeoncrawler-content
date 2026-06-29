# Orchestrator has no pid file — may not be running

- Agent: dev-infra
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-10T14:18:27Z
- Source: system health check

## Issue

No orchestrator pid file found at tmp/orchestrator.pid.\n\nVerify if it's running and restart if needed:\n```bash\nsource orchestrator/.venv/bin/activate && python3 orchestrator/run.py\n```

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
