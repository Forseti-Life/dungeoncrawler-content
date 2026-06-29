# Re-dispatch: Coordinated Signoff — dungeoncrawler release-q

- Agent: pm-dungeoncrawler
- Status: pending
- ROI: 70

## Background

A prior signoff-reminder for dungeoncrawler release-q was quarantined after 3 failed response cycles. This is a clean re-dispatch.

## Task

Process the coordinated signoff for release `20260412-dungeoncrawler-release-q`:
1. Confirm dev, qa, and ba signoff files exist in `tmp/release-cycle-active/`
2. If all signoffs present: write `pm-dungeoncrawler/artifacts/release-signoffs/20260412-dungeoncrawler-release-q.md` (this triggers the coordinated push)
3. If any signoffs missing: identify who has not signed off and write an escalation outbox noting who is blocking

## Output format required

Write outbox with `Status: done` and signoff status summary.
