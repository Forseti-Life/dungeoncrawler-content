- Status: done
- Completed: 2026-04-10T16:38:47Z

- Agent: dev-infra
- Source inbox: sessions/ceo-copilot-2/inbox/20260410-executor-inbox-close-policy
- Priority: medium

## Problem

After an agent writes an outbox artifact, the executor/orchestrator does NOT mark the
source `command.md` to `Status: done`. This causes the orchestrator to re-dispatch
completed inbox items on subsequent ticks, wasting execution slots and requiring duplicate
triage cycles.

Evidence: `20260410-code-review-forseti.life-20260410-forseti-release-b` inbox item was
dispatched twice; `20260410-syshealth-dead-letter-dev-infra-20260408-audit-dead-legacy-dispatch-functions.md`
was re-dispatched to CEO after being archived.

The `pick_agents` guard in `orchestrator/run.py` (~line 263–280) correctly skips `_archived`
items, but the root cause is that non-archived completed items never get their `command.md`
updated to `Status: done`.

## Acceptance criteria (done when)

ONE of the following is implemented and verified:

**Option A (preferred):** After executor (`scripts/agent-exec-next.sh`) writes an outbox
artifact with `Status: done`, it updates the source inbox `command.md` to prepend
`- Completed: <timestamp>` and `- Status: done`.

**Option B:** In `orchestrator/run.py` `_prioritized_agents()` / `_agent_inbox_count()`,
add a guard: skip inbox item directories where `command.md` contains `Status: done` (case
insensitive). This prevents re-dispatch without requiring executor changes.

**Option C:** Add guard to orchestrator `_oldest_unresolved_inbox_seconds()` and
`_dispatch_stagnation_alerts()` to correlate inbox item names against outbox filenames
before flagging as dead-letter.

## Related fix (secondary)

`ceo-system-health.sh` dead-letter check: the `--dead-letter` scan flags inbox items by
age without checking whether a matching outbox entry was filed. Add a correlation check:
if `sessions/<agent>/outbox/<item-id>*.md` exists with `Status: done`, skip the dead-letter
alert for that item. This eliminates false-positive syshealth alerts.

## Verification

1. Confirm `scripts/agent-exec-next.sh` OR `orchestrator/run.py` contains the guard.
2. Dry-run: mark a test `command.md` as `Status: done`; verify orchestrator no longer
   counts it as a live inbox item (check `_agent_inbox_count` output).
3. `bash scripts/ceo-system-health.sh` — no dead-letter alerts for outbox-confirmed done items.

## Owned files (dev-infra scope)

- `scripts/agent-exec-next.sh` (primary)
- `orchestrator/run.py` (secondary — guard in pick_agents / stagnation checks)
- `scripts/ceo-system-health.sh` (dead-letter correlation fix)
