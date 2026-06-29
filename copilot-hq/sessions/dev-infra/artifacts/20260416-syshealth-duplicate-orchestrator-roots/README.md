# Duplicate top-level orchestrator loops detected

- Agent: dev-infra
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-16T14:00:45Z
- Source: system health check

## Issue

More than one top-level orchestrator-loop process is running.

Observed top-level pid(s): 1197518 1200585

This can duplicate scheduler ticks and compound agent execution / Copilot rate-limit pressure.

Remediate with a clean restart:
```bash
bash scripts/orchestrator-loop.sh stop
bash scripts/orchestrator-loop.sh start 60
bash scripts/orchestrator-loop.sh status
```

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
- Status: pending
