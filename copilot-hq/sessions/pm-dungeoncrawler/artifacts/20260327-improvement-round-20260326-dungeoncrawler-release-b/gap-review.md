# Post-Release Gap Review: 20260326-dungeoncrawler-release-b

- PM: pm-dungeoncrawler
- Review date: 2026-03-27
- Release shipped: true (both PM signoffs confirmed, ready-for-push = true)
- QA run: 20260326-224035 (0 violations, 0 failures, clean)

## Gap Summary

### GAP-26B-01: Gate 2 inbox items re-triggered multiple times (duplicate agent cycles)
- Observed: `20260326-203507-gate2-ready-dungeoncrawler` was processed, then re-queued. `20260326-224035-gate2-ready-dungeoncrawler` was also executed twice (second run found "signoff already recorded from a prior cycle").
- Root cause: Automation re-queues Gate 2 items without first checking whether a signoff artifact already exists for the release-id. Each re-trigger consumes a full agent cycle and risks double-processing side effects.
- Owner: dev-infra (scripts/gate2-ready.sh or equivalent queue logic)
- Fix: Before queuing a gate2-ready inbox item, scripts should check `sessions/pm-<site>/artifacts/release-signoffs/<release-id>.md` exists; if so, skip or emit only a status notification.
- Acceptance criteria: Zero duplicate gate2-ready inbox items in the next release cycle.
- ROI: 7

### GAP-26B-02: Improvement round queued before release shipped
- Observed: `20260326-improvement-round-20260326-dungeoncrawler-release-b` inbox item was triggered while `20260326-dungeoncrawler-release-b` was still being groomed (hadn't shipped). Post-release review has no data until release ships.
- Root cause: Improvement round automation fires on a time/cycle trigger rather than on a confirmed-shipped event.
- Owner: dev-infra (scripts/improvement-round-queue.sh or equivalent)
- Fix: Improvement round items must only be queued after release-signoff-status exits 0 for the release-id.
- Acceptance criteria: No improvement-round inbox item is created for a release-id until both PM signoffs are confirmed.
- ROI: 5

### GAP-26B-03: PM seat instructions did not include signoff-idempotency reminder
- Observed: PM outbox entries contained "signoff already recorded from a prior cycle" — suggesting the agent re-ran the signoff script unnecessarily. The seat instructions do not explicitly state to check for an existing signoff before running the script.
- Root cause: Seat instructions lack a "check before signing" step in the Gate 2 review flow.
- Owner: pm-dungeoncrawler (seat instructions)
- Fix: Add one line to Gate 2 review checklist: "Run `bash scripts/release-signoff.sh <site> <release-id>` — script is idempotent (exits OK if already signed); safe to re-run."
- Acceptance criteria: No PM cycle wasted re-investigating whether signoff was already done.
- ROI: 4
