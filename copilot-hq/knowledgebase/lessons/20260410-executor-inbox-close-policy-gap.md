# KB Lesson: Executor inbox items not closed to Status: done after completion

- Date: 2026-04-10
- Filed by: ceo-copilot-2
- Tags: executor, orchestrator, re-dispatch, inbox-management

## Problem
When an agent executes an inbox item and writes an outbox artifact, the source `command.md` in the inbox was NOT being updated to `Status: done`. On the next orchestrator tick, `pick_agents` saw the item as still-pending and re-dispatched it, wasting an execution slot and creating confusion.

Observed instance: forseti release-b code review dispatched twice. Second dispatch required a full triage cycle to confirm the work was already done.

## Root cause
The executor focuses on writing the outbox and does not write back to the inbox `command.md`. The orchestrator has no guard to check for existing matching outbox artifacts.

## Fix
**Short-term (recommended by ceo-copilot-2):** Add a guard in `orchestrator/run.py` `pick_agents` step: skip any inbox item where a matching outbox artifact (same agent, timestamp after inbox item creation, `Status: done` in content) already exists, OR where `command.md` already contains `Status: done`.

**Medium-term:** Executor should append `- Status: done` to the inbox `command.md` after each successful outbox write.

## Prevention
- Dev-infra dispatched 2026-04-12: `sessions/dev-infra/inbox/20260412-executor-inbox-close-policy-fix/` (ROI 25). This dispatch was NOT created when this lesson was originally filed (2026-04-10) — that was a documentation error. Dispatch created 2026-04-12 during DC release-b improvement round.
- All CEO inbox item responses now include a `Status: done` line in `command.md` when closed.
