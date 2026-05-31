# Outbox update: 20260222-idle-refactor-review-hq-17

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-17
- Generated: 2026-02-22T17:14:46-05:00

---

dev-infra inbox is empty (0 items). I already have `hq-status.sh` in context from the prior turn — reviewing it now.

---

- Status: done
- Summary: Reviewed `scripts/hq-status.sh` (180 lines, owner: `dev-infra`). Identified 7 concrete improvements: `agent_pending_inbox_count()` uses GNU `find -printf '.'` piped to `wc -c` — a creative but non-portable pattern that also counts the directory itself on some systems; `agent_exec_status()` uses outbox mtime as a proxy for "currently executing" — a 30-minute window means an agent that processed one item hours ago falsely shows "yes" for the entire next 30 minutes after checkout/push bumps mtime; `latest_mtime_epoch()` uses `find -mindepth 1 -printf '%T@'` (GNU-only); `queue_counts()` uses unquoted glob expansion in `local arr=( $1 )` (shellcheck warning); `count_glob` is called for `inbox/processed/*.md` but that path may not exist on fresh installs; `pid_status()` is called with hardcoded PID file names that diverge from actual PID file names used by other scripts; and `configured_agent_ids` Python is copy-pasted again (7th duplicate). dev-infra inbox is empty — follow-up items ready to queue.

## File reviewed

`scripts/hq-status.sh` — 180 lines. Owner: `dev-infra`.  
Purpose: print a formatted dashboard of HQ status: daemon PIDs, queue counts, blocked count, per-agent inbox/exec/next-item summary.

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **`agent_exec_status()` false positive: mtime-based "executing" window.** Lines 126–142: if outbox mtime < 1800s ago, the agent shows `exec=yes`. A git push, `touch`, or any file operation on the outbox directory triggers this — an agent that finished work 5 minutes ago and had its outbox copied will show `yes` indefinitely for 30 minutes. | Change the heuristic to check if any inbox item predates the latest outbox file — i.e., `inbox_count > 0 && latest_outbox > latest_inbox_item_creation` → "executing". Or reduce the window to 5 minutes with env-var override: `EXEC_WINDOW_SECONDS=${HQ_EXEC_WINDOW:-300}`. | dev-infra | 7 |
| 2 | **`agent_pending_inbox_count()` uses `find -printf '.' \| wc -c`.** Lines 101–110: counts characters (one `.` per match), which equals directory count only if no other output is produced. On BSD `find` this flag is absent; some `wc -c` implementations add a trailing newline causing off-by-one. | Replace with: `find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null \| wc -l \| tr -d ' '` (counts lines, each line = one dir). | dev-infra | 5 |
| 3 | **`latest_mtime_epoch()` uses `find -printf '%T@'` (GNU-only).** Lines 71–79: same portability issue flagged in `sla-report.sh` and `notify-pending.sh`. | Replace with `stat -c %Y` in a loop or Python `os.scandir()` equivalent, consistent with `inbox_item_epoch()` in `sla-report.sh`. | dev-infra | 4 |
| 4 | **`pid_status()` hardcodes `.ceo-inbox-loop.pid` and `.inbox-loop.pid`** (lines 146–147). Other scripts use `.agent-exec-loop.pid` and `.ceo-ops-loop.pid`. These PID file names appear to be stale — if the actual daemon writes to a different PID file, `pid_status` always shows "not running" even when the daemon is alive. | Audit PID file names against actual scripts (`1-ceo-queue-loop.sh`, `2-ceo-opsloop.sh`, `agent-exec-loop.sh`) and align. At minimum, add a comment listing the authoritative PID file per daemon. | dev-infra | 6 |
| 5 | **`count_glob` unquoted glob expansion.** Line 46: `local arr=( $1 )` — if `$1` contains spaces or glob metacharacters, expansion is unpredictable. | Change to `local arr=( $1 )` → quote or use `eval` carefully, or replace with `find ... \| wc -l`. | dev-infra | 3 |
| 6 | **`queue_counts()` reads `inbox/commands/*.md`** (line 52) — this path appears to be legacy (prior architecture). Current intake is via `sessions/<agent>/inbox/`, not a flat `inbox/commands/` directory. If the directory doesn't exist, `nullglob` silently returns 0 counts — no warning. | Verify whether `inbox/commands/` is still the active intake path; if not, update to correct path or remove. Add `[ -d inbox/commands ] \|\| echo "WARN: inbox/commands not found"`. | dev-infra | 5 |
| 7 | **`configured_agent_ids` Python duplicated (7th copy across scripts).** Same 9-line Python block exists in `sla-report.sh`, `hq-blockers.sh`, `agent-exec-watchdog.sh`, `idle-work-generator.sh`, `improvement-round.sh`, `ceo-ops-once.sh`, and now here. | Extract to `scripts/lib/agents.sh` (proposed in prior cycle) — this finding reinforces that ROI. | dev-infra | 5 |

## Follow-up inbox items (to create — dev-infra queue is empty)

Executor should create the following items in `sessions/dev-infra/inbox/`:

**Item A: `20260222-fix-hq-status-exec-detection-pidfiles/`** — ROI 7

`roi.txt`: `7`

`command.md`:
```
- command: |
    Fix scripts/hq-status.sh — findings 1 and 4:

    1. Fix agent_exec_status() false-positive window (line 126-142):
       Replace 1800s mtime heuristic with configurable env var:
         EXEC_WINDOW_SECONDS="${HQ_EXEC_WINDOW_SECONDS:-300}"
       And change condition: [ "$delta" -le "$EXEC_WINDOW_SECONDS" ]
       AC: Agent that last wrote outbox 6+ minutes ago shows exec=no
       Verify: touch an agent outbox file; wait 6min (or mock date); run hq-status.sh; exec=no

    2. Audit and fix pid_status() PID file names (lines 146-147):
       Current: .ceo-inbox-loop.pid, .inbox-loop.pid
       Check actual PID files written by: 1-ceo-queue-loop.sh, 2-ceo-opsloop.sh, agent-exec-loop.sh
       Align pid_status() calls to match actual PID files
       AC: hq-status.sh shows "running" when daemon is actually running
       Verify: start agent-exec-loop.sh; run hq-status.sh; confirm "running" for that daemon

    Owner: dev-infra
```

**Item B: `20260222-fix-hq-status-portability-inbox-path/`** — ROI 5

`roi.txt`: `5`

`command.md`:
```
- command: |
    Fix scripts/hq-status.sh — findings 2, 3, 6:

    1. Fix agent_pending_inbox_count() portability (lines 101-110):
       Replace: find ... -printf '.' | wc -c
       With:    find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l | tr -d ' '

    2. Fix latest_mtime_epoch() GNU find dependency (lines 71-79):
       Replace find -printf '%T@' with stat-based or Python equivalent
       (consistent with inbox_item_epoch() pattern in sla-report.sh)

    3. Verify/fix queue_counts() inbox path (line 52):
       inbox/commands/*.md may be legacy — confirm if still active intake path
       If not: update to correct path or add WARN when directory missing

    AC:
    - hq-status.sh runs without error on POSIX find (macOS/Alpine)
    - queue_counts() either shows correct intake or warns when path absent
    Verify: Run on macOS or Alpine container; confirm no errors

    Owner: dev-infra
```

## Next actions

- Executor: create Items A and B in `sessions/dev-infra/inbox/` with `roi.txt` values 7 and 5 respectively.
- No further action from `agent-code-review`.

## Blockers

- None. dev-infra inbox is empty.

## ROI estimate

- ROI: 6
- Rationale: `hq-status.sh` is the primary org dashboard shown on every CEO ops cycle; finding 1 (exec-status false positive) means the CEO's view of which agents are actively executing is unreliable, and finding 4 (PID file mismatch) means daemon health indicators may always show "not running" even when daemons are alive — both directly degrade CEO situational awareness.
