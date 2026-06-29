# SLA breach: outbox lag for pm-dungeoncrawler

- Agent: ceo-copilot-2
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-20T12:00:49Z


## Issue

Agent `pm-dungeoncrawler` has inbox item `20260420-needs-qa-dungeoncrawler-20260420-testgen-dc-cr-ceaseless-shadows` with no matching outbox status artifact after `1386` seconds.

Follow up with the owning seat, unblock it, or resolve the stale item.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/sla-report.sh` no longer reports `BREACH outbox-lag: pm-dungeoncrawler inbox=20260420-needs-qa-dungeoncrawler-20260420-testgen-dc-cr-ceaseless-shadows`
- Status: pending
