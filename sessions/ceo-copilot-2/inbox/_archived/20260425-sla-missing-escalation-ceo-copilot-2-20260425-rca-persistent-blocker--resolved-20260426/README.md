# SLA breach: missing escalation for ceo-copilot-2

- Agent: ceo-copilot-2
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-25T23:40:06Z
- Escalated agent: ceo-copilot-2
- Escalated item: 20260425-rca-persistent-blocker-Duplicate-orchestrator-roots-detected-pid-s-1449
- Escalated status: blocked


## Issue

Agent `ceo-copilot-2` has latest outbox `20260425-rca-persistent-blocker-Duplicate-orchestrator-roots-detected-pid-s-1449.md` with status `blocked`, but no supervisor escalation item exists.

Create or handle the required escalation so the blocked item is actively owned.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/sla-report.sh` no longer reports `BREACH missing-escalation: ceo-copilot-2`
- Status: pending
