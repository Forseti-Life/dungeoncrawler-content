# SLA breach: missing escalation for qa-dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-28T12:50:06Z
- Escalated agent: qa-dungeoncrawler
- Escalated item: 20260428-unit-test-20260427-171039-impl-dc-home-suggestion-notice
- Escalated status: blocked


## Issue

Agent `qa-dungeoncrawler` has latest outbox `20260428-unit-test-20260427-171039-impl-dc-home-suggestion-notice.md` with status `blocked`, but no supervisor escalation item exists.

Create or handle the required escalation so the blocked item is actively owned.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/sla-report.sh` no longer reports `BREACH missing-escalation: qa-dungeoncrawler`
