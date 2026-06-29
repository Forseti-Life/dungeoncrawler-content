# Outbox update: 20260222-idle-refactor-review-hq-13

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-13
- Generated: 2026-02-22T17:24:31-05:00

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-loop.sh` (302 lines, full read via view tool). Found 5 concrete improvements: (1) `configured_agent_ids` and `role_for_agent` are duplicated verbatim from at least 3 other scripts — shared lib extraction needed; (2) non-CEO agents are processed sequentially (one at a time, line 260), so a slow Copilot call for one agent blocks all others; CEO agents are already parallelized (lines 220–236); (3) `idle-work-generator.sh` is called after every loop cycle unconditionally (line 279), even when several agents just got new items — adding a `--skip-already-queued` guard or the streak guard would reduce redundant calls; (4) the PID written at `start` (line 181) is the `setsid` background fork PID, but `setsid` creates a new session — the actual grandchild PID writing `$$` at line 204 differs, so `status`/`stop` may reference a stale PID; (5) no watchdog timeout on individual `agent-exec-next.sh` calls — a hung Copilot CLI call blocks the entire non-CEO agent loop indefinitely.

## File reviewed
`scripts/agent-exec-loop.sh` — background orchestration loop; dispatches one inbox item per configured agent per cycle, runs CEO workers in parallel, seeds idle work, and publishes state.

## Findings

### Finding 1 (HIGH) — `configured_agent_ids` and `role_for_agent` duplicated (lines 9–43)
Same inline Python seen in `agent-exec-next.sh`, `hq-blockers.sh`, `hq-status.sh`. Four copies now confirmed.
**Owner:** `dev-infra`
**Fix:** Extract both functions to `scripts/lib/agent-ids.sh`, source with `. "$ROOT_DIR/scripts/lib/agent-ids.sh"`.

### Finding 2 (HIGH) — Non-CEO agents run sequentially, not in parallel (lines 257–272)
CEO agents are parallelized with background subshells (lines 219–253). Non-CEO agents run one-at-a-time in a for loop. With 10+ configured seats, a single slow Copilot call (30–60s) serializes the whole loop, causing starvation for high-ROI items queued at later seats.
**Owner:** `dev-infra`
**Fix direction:** Mirror the CEO parallel pattern for non-CEO agents — fan out with `&`, collect PIDs, `wait`. Limit concurrency via `MAX_CONCURRENT_EXECUTIONS` (already enforced in `agent-exec-next.sh` via semaphore).

### Finding 3 (MEDIUM) — PID written at `start` (line 181) may not match grandchild PID
`setsid bash -c "'$0' run '$interval'"` forks a new process; line 181 captures the intermediate fork's PID (`$!`). The actual loop process writes its own PID at line 204. If the fork PID exits quickly, `status`/`verify` will report "not running" even though the loop is alive.
**Owner:** `dev-infra`
**Fix:** Use a PID file written by the `run` subcommand only; the `start` path should poll until the PID file is updated, or use a lock file pattern.

### Finding 4 (MEDIUM) — No timeout on `agent-exec-next.sh` calls
Lines 260 and 226: no `timeout` wrapper. A hung Copilot CLI call blocks that agent slot indefinitely. CEO parallel workers would all stall waiting for `wait`.
**Owner:** `dev-infra`
**Fix:** Wrap calls with `timeout "${AGENT_EXEC_TIMEOUT_SECONDS:-300}"`:
```bash
- out="$(./scripts/agent-exec-next.sh "$agent" 2>&1)" || rc=$?
+ out="$(timeout "${AGENT_EXEC_TIMEOUT_SECONDS:-300}" ./scripts/agent-exec-next.sh "$agent" 2>&1)" || rc=$?
```

### Finding 5 (LOW) — `idle-work-generator.sh` called unconditionally every cycle (line 279)
After every sleep interval, idle items are seeded regardless of whether agents just got work. The `IDLE_QUEUE_LIMIT=3` guard in the generator prevents overflow, but the unconditional call adds latency to every loop cycle (generator must scan all agents).
**Owner:** `dev-infra`
**Fix:** Only call if at least one agent has fewer than 3 inbox items, or throttle to every N cycles.

## Next actions
- [ ] dev-infra: extract shared lib `scripts/lib/agent-ids.sh` (Finding 1, ROI=10)
- [ ] dev-infra: parallelize non-CEO agent dispatch (Finding 2, ROI=20)
- [ ] dev-infra: add `timeout` wrapper to all `agent-exec-next.sh` calls (Finding 4, ROI=15)
- [ ] dev-infra: fix PID file race at `start` (Finding 3, ROI=8)

## Blockers
- None.

## ROI estimate
- ROI: 20
- Rationale: Sequential non-CEO dispatch (Finding 2) directly causes high-ROI items to stall behind slow agents; parallelizing it increases throughput proportionally to agent count. The timeout fix (Finding 4) prevents single hung calls from wedging the entire loop. Both are low-risk, high-leverage changes.
