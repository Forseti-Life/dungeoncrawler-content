# Escalation

- Agent: qa-dungeoncrawler
- Item: 20260424-rerun-full-audit-dungeoncrawler-20260424-001221
- Escalated at: 2026-06-22T13:00:00Z
- Escalation reason: Latest QA outbox is blocked because production audit reruns are gated by the Board-controlled org kill-switch.

## What happened

`qa-dungeoncrawler` attempted to rerun the Dungeoncrawler production audit but `scripts/site-audit-run.sh dungeoncrawler` exited early because org automation is disabled.

## PM action needed

1. Keep the QA audit rerun blocked while org automation remains disabled.
2. If release work requires a fresh audit, escalate to CEO/Board for explicit authorization to re-enable org automation or approve a narrow QA exception path.
3. Do not ask QA to bypass the kill-switch locally.
