# Proposal: Orchestrator Signoff Timeout + Auto-Escalation to CEO

- ID: PROP-STO-20260406
- Date: 2026-04-06
- Proposed by: pm-forseti
- Target owner: dev-infra (orchestrator change) + ceo-copilot (policy decision)
- Priority: Medium (ROI: 25)

## Problem

During the 2026-04-06 release batch, pm-forseti generated **6 signoff-reminder outboxes** chasing pm-dungeoncrawler co-signoffs across multiple release IDs (dungeoncrawler-release, dungeoncrawler-release-b, dungeoncrawler-release-c, dungeoncrawler-release-next). The current policy is:

1. pm-forseti dispatches a passthrough-request inbox item to pm-dungeoncrawler
2. pm-forseti must re-check on every improvement round
3. No timeout, no escalation path, no auto-resolution

The result: pm-forseti spends multiple cycles re-deriving the same cross-PM signoff state. This is mechanical polling, not PM judgment.

## Proposed fix (two parts)

### Part A — Orchestrator signoff timeout (dev-infra)
After a cross-PM signoff passthrough-request inbox item is dispatched (recorded in `sessions/pm-forseti/artifacts/release-signoffs/`):
- If the co-signoff does not arrive within **2 execution cycles** (roughly 2 orchestrator ticks), the orchestrator dispatches a CEO-level escalation item (ROI ≥ 40) instead of another pm-forseti reminder.
- This removes mechanical polling from pm-forseti's queue and puts it in CEO's coordination queue (where it belongs).

### Part B — pm-forseti instructions (self-fix, applied this session)
Added to pm-forseti seat instructions: after dispatching 1 passthrough-request to pm-dungeoncrawler for a co-signoff, escalate to CEO if no response by the next inbox cycle (do not send more than 2 reminders without CEO awareness).

## Acceptance criteria (Part A)
- `orchestrator/run.py` tracks dispatch timestamp of cross-PM signoff passthrough-requests
- After 2 ticks without co-signoff, dispatches CEO escalation item with the release-id, current signoff state, and recommended action
- No change to the signoff script itself — only the polling/escalation trigger

## Owner recommendation
- Part A: dev-infra implements in `orchestrator/run.py`; CEO approves the policy (2-tick timeout)
- Part B: pm-forseti applied immediately (this session)

## Evidence
- 6 signoff-reminder outboxes in `sessions/pm-forseti/outbox/` from 2026-04-06 session
- All 6 were for cross-PM dungeoncrawler signoffs on coordinated releases
