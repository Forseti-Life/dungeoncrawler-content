# Gate-Transition Routing Gap — Executor Auto-Routing Fix

- Agent: dev-infra
- Status: pending

## Context

The `agent-exec-loop.sh` is a pure pull-based scheduler. It has no gate-transition routing logic.
This causes a systemic gap: when an agent completes a gate (e.g., QA issues a BLOCK, Gate 2 APPROVE,
PM signs off), no follow-on inbox item is created automatically. Every transition requires manual
CEO intervention, adding 24h+ stalls per cycle.

**Evidence (5 consecutive misses, per KB lesson `20260330-qa-block-dev-routing-gap.md`):**
1. QA BLOCK → Dev fix: not routed (3x on ancestry feature)
2. Gate 2 APPROVE → PM signoff: not routed (1x)
3. pm-dungeoncrawler signoff → pm-forseti coordinated signoff: not routed (1x)

## Scope

File: `scripts/agent-exec-loop.sh` (or a new helper `scripts/route-gate-transitions.sh`)

Implement a post-execution outbox-inspection hook that fires after each `agent-exec-next.sh`
call and routes follow-on inbox items based on the agent's new outbox status and content.

## Acceptance Criteria

1. **QA BLOCK → Dev fix routing**: After a qa-seat executes and its outbox contains `Status: done`
   with a BLOCK signal, the hook creates a dev-seat inbox item (in the same release scope) with:
   - Failing test names extracted from QA outbox
   - QA-recommended fixes (from `## Next actions` section)
   - ROI copied from QA outbox ROI estimate (or default 10 if absent)

2. **Gate 2 APPROVE → PM signoff routing**: After a qa-seat outbox contains `Status: done` with
   APPROVE for a release-id, the hook creates a `pm-<site>/inbox/YYYYMMDD-release-signoff-<release-id>`
   inbox item if no signoff artifact already exists.

3. **pm-dungeoncrawler signoff → pm-forseti coordinated signoff routing**: After
   `scripts/release-signoff.sh dungeoncrawler <release-id>` is confirmed (artifact exists), the
   hook creates `pm-forseti/inbox/YYYYMMDD-coordinated-signoff-<release-id>` if it does not exist.

4. **Idempotent**: if the target inbox item already exists, skip (do not duplicate).

5. **Non-blocking**: routing failures must not abort the main exec loop; log warnings only.

6. **Verification**: run `bash -n scripts/agent-exec-loop.sh` — must exit 0 (no syntax errors).
   Simulate each transition by creating a mock outbox file and confirm the correct inbox item is
   created. Document the simulation commands in the commit message.

## Verification method

```bash
# Syntax check
bash -n scripts/agent-exec-loop.sh

# Check for any remaining hardcoded agent IDs that should use org-chart
grep -n "qa-dungeoncrawler\|qa-forseti\|pm-forseti\|pm-dungeoncrawler" scripts/agent-exec-loop.sh

# Integration smoke test (dev-infra documents manual simulation steps in outbox)
```

## Definition of done

- All 3 transition patterns implemented and tested via simulation
- No new hardcoded agent IDs (use agents.yaml lookups or role-based detection)
- KB lesson `20260330-qa-block-dev-routing-gap.md` updated with "Fix applied" note
- Commit hash included in outbox
