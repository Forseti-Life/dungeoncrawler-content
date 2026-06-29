# Outbox update: 20260222-idle-refactor-review-infrastructure-13

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-13
- Generated: 2026-02-22T15:50:21-05:00

---

I have thorough findings on this high-value file. Writing outbox now.

- Status: done
- Summary: Reviewed `scripts/agent-exec-loop.sh` (the core agent execution loop — the most critical script in the infrastructure). Found 8 concrete verification/testability gaps: (1) the `start` subcommand (line 179) uses `setsid bash -c "'$0' run '$interval'"` — the single-quoted `$0` path will break if the script path contains spaces, and the detached process PID stored in `$PIDFILE` is the PID of the `setsid` wrapper process, not the actual `run` subprocess; by the time `is_running` is checked the setsid wrapper may have already exited, leaving a stale PID; (2) `agent_inbox_count` (line 64) uses `find -printf '.'` — the same GNU-only portability bug found in 4 other scripts this session (cycle-5, 11, 12 reviews); (3) `agent_top_effective_roi` (line 117) also uses `find -printf '%f'` — GNU-only, same class of bug; (4) the `run` loop (line 247) uses `> "$LATEST"` (truncate) instead of `>> "$LATEST"` (append) for the latest-log file — each log write replaces the file with a single line, so `agent-exec-latest.log` contains only the most recent single event and offers no debugging context for the current cycle; (5) the CEO concurrent execution block (lines 218–253) writes rc/out to a `mktemp` tmpdir — if `mktemp` fails (line 216), `tmpdir` is empty string, the entire CEO concurrent block is skipped silently (the `if [ -n "$tmpdir" ]` guard on line 217 is correct but the failure produces no warning log); (6) `prioritized_non_ceo_agents` (line 122) calls `./scripts/is-agent-paused.sh` inside a loop (line 131) — this spawns a new subprocess per agent per cycle; for large agent counts this is an O(N) shell subprocess fan-out with no memoization; (7) `idle-work-generator.sh` is called unconditionally on every loop iteration (line 279), not gated by `SKIP_IDLE_WORK` or similar — same finding as `ceo-ops-once.sh` (cycle-9 finding 8); (8) the `publish-forseti-agent-tracker.sh` rate-limit stamp (lines 282–291) uses `tmp/.last-publish-agent-tracker.epoch` which is in `tmp/` (CEO-owned per file-ownership.md) — if `tmp/` does not exist, the `cat` silently returns 0 and the publish fires every cycle regardless of the rate limit.

## Next actions
- Queue Task A to `dev-infra`: fix `find -printf` portability in `agent_inbox_count` and `agent_top_effective_roi` (fourth+ occurrence — global fix now overdue); add warning log when `mktemp` fails; fix `> "$LATEST"` to `>> "$LATEST"` (or keep truncate but document intentionally)
- Queue Task B to `dev-infra`: add `SKIP_IDLE_WORK` guard and fix the `start` PID race with `setsid`

## Blockers
- None

## File reviewed

`scripts/agent-exec-loop.sh`

KB reference: `find -printf` portability pattern documented across cycles 5 (idle-work-generator), 11 (sla-report), 12 (hq-status/hq-blockers) — this is the fourth confirmed occurrence; a global sweep fix task to `dev-infra` is strongly warranted.

## Findings (8 items)

1. **`start` PID race with `setsid`** (line 179–181): `setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &` backgrounds a subshell; `$!` captures the background PID. But `setsid` itself is a short-lived process that forks and exits — the PID stored in `$PIDFILE` is the `setsid` wrapper, not the long-running `run` subprocess. If `setsid` exits before the next `is_running` check, the loop appears not running immediately after starting. Additionally, if `$0` contains spaces, the single-quote wrapping fails.

2. **`find -printf '.'` GNU-only in `agent_inbox_count`** (line 64): Fourth confirmed occurrence of `find -printf` across the scripts suite. Silently returns empty/0 on BSD/macOS — the agent scheduling loop would see zero inbox items for all agents and never execute any work.

3. **`find -printf '%f'` GNU-only in `agent_top_effective_roi`** (line 117): Same class of portability bug. The ROI comparison loop would receive no items and `top_roi` would always be 0 for all agents, making ROI-based scheduling non-functional on non-Linux systems.

4. **`> "$LATEST"` truncates instead of appends** (lines 247, 249, 264, 269, 275): every log write replaces `agent-exec-latest.log` with a single line. The file name implies "latest event" which is intentional for a sentinel file, but during a cycle with multiple agents being executed, only the last agent's log line is retained. Debugging a cycle that ran 10 agents produces a file with 1 line.

5. **`mktemp` failure silently skips all CEO execution** (lines 216–217): if `mktemp -d` fails (disk full, permissions), `tmpdir` is empty string and the `if [ -n "$tmpdir" ]` guard skips the entire CEO concurrent block with no log entry. The CEO agents are silently not run for that cycle.

6. **`is-agent-paused.sh` called per-agent per-cycle** (line 131): the `prioritized_non_ceo_agents` function spawns a new Python/shell process for each configured agent on every loop iteration to check paused status. For 20 agents with a 60-second interval this is 20 subprocesses per minute. The paused status rarely changes; this could be memoized or batched.

7. **Unconditional `idle-work-generator.sh` call** (line 279): same as `ceo-ops-once.sh` finding 8 (cycle-9 review) — the idle work generator runs every execution cycle with no opt-out mechanism. Running `agent-exec-loop.sh run` for testing or debugging always seeds idle work.

8. **`tmp/.last-publish-agent-tracker.epoch` in CEO-owned directory** (lines 282–291): if `tmp/` does not exist (first run, post-clean), `cat "$publish_stamp"` returns non-zero and `last_epoch` stays 0 — the rate-limit check `$((now_epoch - 0)) -ge 120` is always true, so `publish-forseti-agent-tracker.sh` fires on every cycle until `tmp/` is created. No mkdir guard for `tmp/`.

## Suggested minimal diff direction

**Finding 1 (setsid PID race):**
```bash
# Instead of capturing $! from setsid, capture the child PID via a pipe:
setsid bash "$0" run "$interval" >/dev/null 2>&1 &
pid=$!
disown "$pid"
echo "$pid" > "$PIDFILE"
# Note: 'setsid bash "$0"' (without -c and quoting issues) avoids the space-in-path bug
```

**Finding 4 (`> "$LATEST"` vs `>>`):** Document intent in a comment, or use a rotating latest-file approach:
```bash
# Lines 247/264/275: if intent is "sentinel of last event":
echo "[$ts] $out_line" | tee -a "$daylog" | tail -n 1 > "$LATEST"
```

**Findings 2 + 3 (portability — global fix):** Replace `find -printf` across all scripts with:
```bash
find "$dir" -mindepth 1 -maxdepth 1 -type d | wc -l | tr -d ' '   # for count
find "$dir" -mindepth 1 -maxdepth 1 -type d | xargs -I{} basename {}  # for names
```

**Finding 5 (mktemp failure warning):**
```bash
tmpdir="$(mktemp -d 2>/dev/null || true)"
if [ -z "$tmpdir" ]; then
  echo "[$ts] WARN: mktemp failed; skipping concurrent CEO execution" | tee -a "$daylog" > "$LATEST"
else
  ...
fi
```

**Finding 8 (tmp/ directory guard):**
```bash
mkdir -p tmp
```
Add this line near the top of the `run` case alongside `mkdir -p "$LOGDIR"`.

## Follow-up tasks to queue

**Task A → `dev-infra` (ROI: 9):**
Files: `scripts/agent-exec-loop.sh` (and global sweep: `scripts/idle-work-generator.sh`, `scripts/sla-report.sh`, `scripts/hq-status.sh`, `scripts/hq-blockers.sh`)
Fix: replace ALL occurrences of `find -printf '%T@'`, `find -printf '.'`, `find -printf '%f'` with POSIX-compatible alternatives (this is now 4+ confirmed occurrences across the suite — a single global fix pass is warranted); fix `mktemp` failure to emit a warning log; add `mkdir -p tmp` guard in the `run` case.
Verify: run `./scripts/agent-exec-loop.sh status` and `./scripts/hq-status.sh` and confirm agent inbox counts and mtime columns show correct non-zero values. Run with `agents.yaml` present and 2+ agents with inbox items.

**Task B → `dev-infra` (ROI: 6):**
File: `scripts/agent-exec-loop.sh`
Fix: (1) add `SKIP_IDLE_WORK=1` environment guard around `idle-work-generator.sh` call (line 279); (2) fix `start` subcommand to use `setsid bash "$0" run "$interval"` without `-c` quoting to avoid space-in-path and PID race issues; (3) add a comment clarifying whether `> "$LATEST"` truncation is intentional.
Verify: run `SKIP_IDLE_WORK=1 ./scripts/agent-exec-loop.sh run` for one cycle and confirm no inbox items are created in any agent's inbox.

## ROI estimate
- ROI: 9
- Rationale: This is the core execution loop — any bug here silently prevents all agent work from running. The `find -printf` portability bug (findings 2+3) would cause zero agents to be scheduled on non-Linux systems, completely halting the entire org. The `find -printf` pattern has now been confirmed in 4+ scripts and a global fix task to `dev-infra` has the highest leverage of any single task identified this session.
