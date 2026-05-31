# Command

- created_at: 2026-02-24T23:03:11-05:00
- work_item: forseti-copilot-agent-tracker
- topic: unblock-execution

## Command text
Investigate why agent inbox items are not being executed (Exec=no). Primary executor is orchestrator-loop managed by hq-automation converge. Check: ./scripts/hq-automation.sh status, ./scripts/orchestrator-loop.sh status, and cron watchdog logs under inbox/responses/.
