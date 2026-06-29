# Outbox update: 20260222-idle-refactor-review-hq-20

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-20
- Generated: 2026-02-22T17:23:07-05:00

---

dev-infra is empty. I have `agent-exec-loop.sh` in context from hq-19. Producing the outbox now.

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-loop.sh` (302 lines, owner: `dev-infra`) — the core agent execution loop. Identified 7 concrete improvements: `LOGDIR="inbox/responses"` (line 155) continues the wrong-log-directory pattern found in 6+ prior scripts; `tee -a "$daylog" > "$LATEST"` (lines 247, 264) overwrites `agent-exec-latest.log` with only the last single log line, not a running tail of the session — debugging a looping issue requires reading N dated log files instead of one; the `start` subcommand writes `$!` (the subshell PID) to PIDFILE before the `run` process overwrites it with `$$` (line 204), creating a race window where `verify` returns false-negative "not running"; `role_for_agent()` spawns a Python subprocess per agent per loop cycle — for 30 agents this is 30 sequential Python+YAML-read calls every 60 seconds; CEO agent IDs are hardcoded in two separate locations (lines 128 and 218), requiring dual-edits to add a new CEO thread; `configured_agent_ids` Python block is copy-pasted (8th instance); and `idle-work-generator.sh` errors are silently discarded. dev-infra inbox is empty — follow-up items ready to queue.

## File reviewed

`scripts/agent-exec-loop.sh` — 302 lines. Owner: `dev-infra`.  
Purpose: background daemon that executes agent inbox items in priority order. Handles CEO concurrent threads, non-CEO prioritized queue, ROI-weighted scheduling, Forseti reply consumption, and idle-work seeding.

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **`tee -a "$daylog" > "$LATEST"` — latest log always contains only the last line.** Lines 247, 264, 275: `> "$LATEST"` (overwrite, not append) means `agent-exec-latest.log` holds only the single most recent log entry. Anyone tailing the latest log sees one line and nothing else. The day log accumulates correctly but the "quick status" log is useless for debugging. | Change `> "$LATEST"` to `\| tee -a "$LATEST"` (append both day and latest) or keep latest as a symlink to today's day log: `ln -sf "$(basename "$daylog")" "$LATEST"`. | dev-infra | 8 |
| 2 | **`LOGDIR="inbox/responses"` — wrong log directory.** Line 155. Same issue found in `agent-exec-watchdog.sh`, `forseti-triage-once.sh`, `notify-pending.sh`. All operational logs should go to `tmp/logs/`. | Change `LOGDIR="inbox/responses"` → `LOGDIR="tmp/logs"`. | dev-infra | 6 |
| 3 | **PID race condition on `start`.** Lines 179–182: `start` writes `$!` (background subshell PID) to PIDFILE. The `run` subcommand then overwrites PIDFILE with `$$` (line 204) when it actually starts. Between the `start` write and the `run` overwrite, `verify`/`status`/`is_running` reads a stale PID — it may report "not running" or find a non-exec-loop process with that PID. | Remove the `echo "$pid" > "$PIDFILE"` from the `start` case (lines 181); let `run` be the sole writer of its own PID. For the brief window before `run` starts, callers can tolerate "not running" (it will transition quickly). | dev-infra | 6 |
| 4 | **`role_for_agent()` spawns a Python subprocess per agent per loop cycle.** Called at line 136 inside `prioritized_non_ceo_agents()` for every non-CEO agent. For 30 agents at 60s intervals: 30 Python invocations × YAML parse = ~30× overhead per cycle. Same data is available in a single batch parse. | Batch-parse all agent roles in one Python invocation (returning `agent_id\trole` TSV), then look up from that in shell. Mirrors how `idle-work-generator.sh` uses `configured_agents_tsv()`. | dev-infra | 7 |
| 5 | **CEO agent IDs hardcoded in two locations.** Line 128 (`case` exclusion in `prioritized_non_ceo_agents`) and line 218 (array literal `ceo_agents`). Adding a `ceo-copilot-4` requires editing two places. | Extract `CEO_AGENTS=(ceo-copilot ceo-copilot-2 ceo-copilot-3)` as a top-level variable; reference it in both locations. | dev-infra | 4 |
| 6 | **`idle-work-generator.sh` errors silently discarded.** Line 279: `>/dev/null 2>&1 \|\| true` — same issue as `ceo-ops-once.sh`. If idle-work fails (Python crash, missing YAML), the loop continues with no warning, and agents silently receive no idle work. | Change to `./scripts/idle-work-generator.sh "$(date +%Y%m%d)" >> "$daylog" 2>&1 \|\| echo "[$ts] WARN: idle-work-generator failed" >> "$daylog"`. | dev-infra | 5 |
| 7 | **`configured_agent_ids` Python block duplicated (8th copy).** Lines 9–21. | Source from `scripts/lib/agents.sh` once this is extracted (proposed in prior cycles). | dev-infra | 4 |

## Follow-up inbox items (to create — dev-infra queue is empty)

Executor should create the following items in `sessions/dev-infra/inbox/`:

**Item A: `20260222-fix-agent-exec-loop-latest-log-pid-race/`** — ROI 8

`roi.txt`: `8`

`command.md`:
```
- command: |
    Fix scripts/agent-exec-loop.sh — findings 1 and 3 (highest ROI):

    1. Fix agent-exec-latest.log overwrite (finding 1, lines 247/264/275):
       Change all occurrences of:
         ... | tee -a "$daylog" > "$LATEST"
       To append to latest as well:
         ... | tee -a "$daylog" | tee -a "$LATEST" >/dev/null
       OR symlink: ln -sf "$(basename "$daylog")" "$LATEST"
       AC: tail -f tmp/logs/agent-exec-latest.log shows running stream of loop entries
       Verify: run loop for 3 cycles; wc -l tmp/logs/agent-exec-latest.log > 3

    2. Fix PID race on start (finding 3, lines 179-182):
       Remove: echo "$pid" > "$PIDFILE" from the 'start' case
       Let 'run' be sole PIDFILE writer (already does this on line 204)
       AC: is_running returns false immediately after start (brief window), then true
       once 'run' has written its PID — no stale non-loop PID in PIDFILE
       Verify: start loop; immediately call verify; confirm no false match on
       a random subshell PID

    Owner: dev-infra
```

**Item B: `20260222-fix-agent-exec-loop-role-batch-logdir/`** — ROI 7

`roi.txt`: `7`

`command.md`:
```
- command: |
    Fix scripts/agent-exec-loop.sh — findings 2, 4, 6:

    1. Fix LOGDIR (finding 2, line 155):
       Change: LOGDIR="inbox/responses"
       To:     LOGDIR="tmp/logs"
       AC: logs written to tmp/logs/; inbox/responses/ contains no exec-loop logs
       Verify: bash scripts/agent-exec-loop.sh start 5; sleep 6;
               ls tmp/logs/agent-exec-*.log

    2. Batch role_for_agent() (finding 4, lines 23-43 + line 136):
       Replace per-agent Python subprocess with single batch invocation
       returning agent_id<TAB>role TSV; read into associative array.
       Pattern: see configured_agents_tsv() in idle-work-generator.sh.
       AC: One Python call per loop cycle for role data (not N)
       Verify: add debug counter; confirm single invocation per prioritized_non_ceo_agents call

    3. Surface idle-work-generator failures (finding 6, line 279):
       Change: >/dev/null 2>&1 || true
       To: >> "$daylog" 2>&1 || echo "[$ts] WARN: idle-work-generator failed" >> "$daylog"
       AC: idle-work-generator crash logged to day log
       Verify: break idle-work-generator.sh (add exit 1); run loop; confirm WARN in daylog

    Owner: dev-infra
```

**Item C: `20260222-fix-agent-exec-loop-ceo-hardcode/`** — ROI 4

`roi.txt`: `4`

`command.md`:
```
- command: |
    Fix scripts/agent-exec-loop.sh — finding 5:

    Extract CEO agent IDs to a single top-level variable:
      CEO_AGENTS=(ceo-copilot ceo-copilot-2 ceo-copilot-3)

    Reference it in:
    - Line 128 (case exclusion): case "$agent" in ${CEO_AGENTS[*]// /|}) continue ;;
    - Line 218 (array literal): replace with ceo_agents=("${CEO_AGENTS[@]}")

    AC: grep for 'ceo-copilot-2' in script returns only the single variable definition
    Verify: grep -n 'ceo-copilot-2' scripts/agent-exec-loop.sh | wc -l → 1

    Owner: dev-infra
```

## Next actions

- Executor: create Items A, B, C in `sessions/dev-infra/inbox/` with `roi.txt` values 8, 7, 4 respectively.
- Item A (ROI 8) is highest priority — the latest-log overwrite makes the exec loop effectively unmonitorable without digging through dated logs.

## Blockers

- None. dev-infra inbox is empty.

## ROI estimate

- ROI: 8
- Rationale: `agent-exec-loop.sh` is the org's primary execution engine running continuously; finding 1 (latest log overwrite) means operators and the watchdog script have no running view of loop activity — a single-line "latest" log is effectively useless for incident diagnosis, making every exec loop issue harder to detect and debug.
