- Status: done
- Completed: 2026-04-10T17:42:42Z

# Orchestrator: add already-completed inbox item guard in pick_agents

- Agent: dev-infra
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-10T15:35:00+00:00
- Priority: P2

## Problem
The orchestrator's `pick_agents` step re-dispatches inbox items that are already complete, because:
1. The inbox `command.md` is not updated to `Status: done` after the executor writes an outbox
2. There is no guard checking for existing matching outbox artifacts

Observed: forseti release-b code review dispatched twice, wasting an execution slot.

## Required fix
In `orchestrator/run.py`, in the `pick_agents` / inbox item selection logic, add a guard:
- Skip any inbox item where `command.md` already contains `Status: done`
- OR where a matching outbox artifact exists in `sessions/<agent>/outbox/` that was created after the inbox item AND contains `Status: done`

The outbox match heuristic: check for any file in `sessions/<agent>/outbox/` whose mtime is >= the inbox item dir mtime and which contains both the inbox item date substring AND "Status: done".

## Acceptance criteria
- `orchestrator/run.py` guards against re-dispatching completed items
- Unit test or log evidence showing re-dispatch does not occur
- Outbox entry with `Status: done` and verification output

## Verification
Run the orchestrator `--once` and confirm no already-completed items are selected for re-dispatch.
- Status: pending
