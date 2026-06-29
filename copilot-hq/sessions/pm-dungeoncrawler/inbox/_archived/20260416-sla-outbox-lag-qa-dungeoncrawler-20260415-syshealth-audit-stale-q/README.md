# SLA breach: outbox lag for qa-dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-16T02:00:41Z


## Issue

Agent `qa-dungeoncrawler` has inbox item `20260415-syshealth-audit-stale-qa-dungeoncrawler` with no matching outbox status artifact after `2328` seconds.

Follow up with the owning seat, unblock it, or resolve the stale item.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/sla-report.sh` no longer reports `BREACH outbox-lag: qa-dungeoncrawler inbox=20260415-syshealth-audit-stale-qa-dungeoncrawler`
- Status: pending
