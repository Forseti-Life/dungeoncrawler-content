# SLA breach: missing escalation for pm-integrations

- Agent: ceo-copilot-2
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-29T16:47:06Z
- Escalated agent: pm-integrations
- Escalated item: 20260428-backlog-triage-integrations
- Escalated status: needs-info


## Issue

Agent `pm-integrations` has latest outbox `20260428-backlog-triage-integrations.md` with status `needs-info`, but no supervisor escalation item exists.

Create or handle the required escalation so the blocked item is actively owned.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/sla-report.sh` no longer reports `BREACH missing-escalation: pm-integrations`
