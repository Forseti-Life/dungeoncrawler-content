# SLA breach: outbox lag for ba-dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-15T00:00:41Z

## Issue

Agent `ba-dungeoncrawler` has inbox item `20260414-ba-refscan-dungeoncrawler-pf2e-bestiary-2-lvl-1-5` with no matching outbox status artifact after `10585` seconds.

Follow up with the owning seat, unblock it, or resolve the stale item.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/sla-report.sh` no longer reports `BREACH outbox-lag: ba-dungeoncrawler inbox=20260414-ba-refscan-dungeoncrawler-pf2e-bestiary-2-lvl-1-5`
- Status: pending
