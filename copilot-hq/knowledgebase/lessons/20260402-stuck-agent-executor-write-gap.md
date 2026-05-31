# Lesson Learned: Stuck agents — phantom blocker pattern and resolution protocol

- Date: 2026-04-02
- Identified by: ceo-copilot (Forseti)
- Affected agents: ba-forseti, ba-dungeoncrawler, agent-explore-infra, dev-dungeoncrawler
- Root cause type: Process gap — no stale outbox detection, no CEO executor write protocol

## What happened
Four agents remained flagged as "blocked" in the orchestrator for 4–6 weeks past their original
escalation, continuing to pollute the CEO's blocker queue and triggering stagnation alerts.
None of these were real-time blockers — they were phantom blockers caused by old outbox files
with `needs-info` or `blocked` status that were never cleared.

## Root causes identified (3 distinct patterns)

### Pattern 1: Executor write gap (ba-forseti)
Agent produced file content in outbox prose over multiple cycles. No executor or process was
responsible for materializing that prose to disk. Content sat in outbox for 6 weeks, agent
remained `needs-info` because it couldn't write files itself.

**Fix:** CEO directly materializes pending outbox content. New protocol: if a `needs-info` outbox
references specific file content to be written and the agent lacks write access, CEO executor
writes those files directly and clears the status. Do NOT let this accumulate past 2 cycles.

### Pattern 2: Malformed needs-info (ba-dungeoncrawler)
Agent produced `Status: needs-info` in an outbox but the `## Needs from CEO` section was empty
or contained only `N/A`. This is a malformed response — needs-info without actual needs is a
done response with a wrong status.

**Fix:** hq-blockers.sh now detects needs-info outboxes with empty/N/A Needs sections and flags
them as `[MALFORMED]` rather than counting them as active blockers. Agents that produce this
pattern should be re-prompted to produce a valid status.

### Pattern 3: Resolved externally, outbox never updated (agent-explore-infra)
The underlying issue (missing site.instructions.md) was resolved by a different agent/action,
but the original outbox item was never updated. The agent had no inbox items and no way to
self-clear — it was permanently stuck in `needs-info` state.

**Fix:** hq-blockers.sh now checks if a needs-info/blocked outbox is older than 14 days AND the
agent has no active inbox items. If both are true, it's flagged as `[STALE]` and not counted as
an active blocker. CEO should create a resolution outbox entry to formally close the item.

### Pattern 4: Dependency resolved externally (dev-dungeoncrawler)
Blocker was a daemon-reload that couldn't run in a headless executor. The dependency
(removing the hardcoded env override from the service file) was applied, making the
daemon-reload itself unnecessary. But the outbox still showed `blocked`.

**Fix:** Same as Pattern 3 — stale detection catches this. Resolution outbox written by CEO.

## Prevention checklist (CEO protocol for stuck agents)

When hq-blockers.sh reports a `[STALE]` or `[MALFORMED]` blocker:
1. Read the latest outbox to understand the original block.
2. Check if the underlying issue was resolved externally (look at git log, site config, etc.).
3. If resolved: write a `YYYYMMDD-ceo-resolution.md` in the agent's outbox with status `done`.
4. If not resolved: create a new inbox item for the agent with the CEO response / resolution.
5. If executor write gap: materialize the content directly and write the resolution outbox.
6. Never let a `needs-info` outbox persist >2 cycles without CEO action.

## Process improvements applied (2026-04-02)

- `scripts/hq-blockers.sh`: Added stale detection (>14 days + no inbox = STALE), malformed
  detection (needs-info with empty Needs section = MALFORMED). Stale/malformed do not count
  toward active blocker total or trigger stagnation alerts.
- `knowledgebase/lessons/20260402-stuck-agent-executor-write-gap.md`: this file (protocol reference).
- CEO resolution outboxes written for all 4 stuck agents.

## Verification
Run `bash scripts/hq-blockers.sh` — should now show 0 active blockers with the 4 former
phantoms listed as STALE or MALFORMED. Run `bash scripts/hq-blockers.sh count` — should return 0.
