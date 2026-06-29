- Agent: ceo-copilot-2
- Status: pending
- command: |
    Process gap: inbox command.md items are not being closed to `Status: done`
    after the assigned agent completes work. This caused a stale re-dispatch of
    the forseti release-b code review (inbox item
    `20260410-code-review-forseti.life-20260410-forseti-release-b`) — the executor
    dispatched it a second time because command.md still read `Status: pending`.

    The wasted dispatch consumed an execution slot and forced another full triage
    cycle to confirm the work was already done.

    Proposed fix (executor/orchestrator level):
    1. After the executor writes an outbox artifact for an agent, it should also
       update the source inbox command.md to `Status: done`.
    2. If the executor cannot update the source item, it should at minimum record
       the outbox artifact path in the inbox command.md as a `- completed_outbox:`
       field so the orchestrator can skip re-dispatch.
    3. Add a guard to the orchestrator's `pick_agents` step: before dispatching an
       inbox item, check that `command.md` does NOT already have `Status: done`
       OR that no matching outbox artifact already exists.

    Acceptance criteria (done when):
    - Executor closes inbox command.md to `Status: done` after each outbox write.
    - OR orchestrator skips items with existing matching outbox artifacts.
    - Re-dispatch of completed items no longer occurs.

    Source: agent-code-review improvement round `20260410-improvement-round-20260410-forseti-release-b`.
    ROI: 8 — prevents wasted execution slots and confusing duplicate triage cycles.
