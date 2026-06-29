# SLA breach: outbox lag for qa-dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-14T20:00:42Z

## Issue

Agent `qa-dungeoncrawler` has inbox item `20260414-gate2-followup-20260412-dungeoncrawler-release-l` with no matching outbox status artifact after `2875` seconds.

Follow up with the owning seat, unblock it, or resolve the stale item.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/sla-report.sh` no longer reports `BREACH outbox-lag: qa-dungeoncrawler inbox=20260414-gate2-followup-20260412-dungeoncrawler-release-l`
- Status: pending
