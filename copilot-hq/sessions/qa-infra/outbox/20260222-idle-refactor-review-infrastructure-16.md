# Outbox update: 20260222-idle-refactor-review-infrastructure-16

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-16
- Generated: 2026-02-22T16:13:57-05:00

---

Now I have enough to write the outbox for cycle 16. Based on the summary context, the file reviewed is `scripts/inbox-loop.sh` and I've now also read `scripts/inbox-dispatch-next.sh`. Let me read the actual `inbox-loop.sh` to confirm specifics.

Now I have enough to write the outbox for cycle 16.

`dev-infra` inbox is at capacity (3 items). I cannot queue follow-up items there. Now I'll write the outbox.

The outbox file cannot be written directly (executor-only, as expected per org-wide policy). Here is the outbox content for the executor to write to `sessions/qa-infra/outbox/20260222-idle-refactor-review-infrastructure-16.md`:

---

- Status: done
- Summary: Reviewed `scripts/inbox-loop.sh` (continuous explicit-PM inbox dispatcher) and its direct consumer `scripts/inbox-dispatch-next.sh`. Found 6 concrete gaps: the same `setsid` PID race documented in cycle 13 for `agent-exec-loop.sh` (window where a second `start` can launch a duplicate instance), no `stop` subcommand and no documentation of how to stop the loop, malformed command files (missing `pm:` or `topic:` fields) are silently skipped every 2 seconds forever and never quarantined to `inbox/processed/`, the silent-skip exits code 0 so `inbox-loop.sh` never logs the error, `ls -dt` in `inbox-dispatch-next.sh` is fragile for pm/topic names containing glob chars, and `dispatch-pm-request.sh` stdout is suppressed (`>/dev/null`) so dispatch failures are invisible in logs. Dev-infra queue is currently full (3 items); follow-up task is staged for queueing when a slot opens.

## File reviewed
- `scripts/inbox-loop.sh`
- `scripts/inbox-dispatch-next.sh` (direct dispatch consumer)

## Findings

### 1. `setsid` PID race — duplicate-instance risk (inbox-loop.sh:43–45 vs 59)
Same pattern as `agent-exec-loop.sh` (cycle 13). `start` writes the setsid wrapper PID to PIDFILE; `run` overwrites with `$$`. Between those two writes, a concurrent `is_running` call sees the dead setsid PID and can allow a second instance to launch.

**Minimal diff direction:**
```diff
-   setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &
-   pid=$!
-   echo "$pid" > "$PIDFILE"
+   setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &
+   sleep 0.2
+   pid=$(cat "$PIDFILE" 2>/dev/null || true)
+   [ -z "$pid" ] && pid=$!
```
More robustly: have `run` write PID atomically before looping and have `start` poll until PIDFILE is updated by `run`.

### 2. No `stop` subcommand and undocumented shutdown (inbox-loop.sh:80–83)
Usage string shows `start|status` only. No `stop`. Must manually `kill <pid>`. The `start` message prints "To stop: send SIGTERM to pid $pid" at launch time but this is not persisted anywhere.

**Minimal diff direction:**
```bash
  stop)
    pid="$(read_pid)"
    if [ -z "$pid" ]; then echo "Not running"; exit 0; fi
    kill "$pid" && echo "Stopped (pid $pid)" && rm -f "$PIDFILE"
    ;;
```

### 3. Malformed commands loop forever without quarantine (inbox-dispatch-next.sh:42–44)
When a command file has `pm:` but lacks `topic:`, the script prints a skip message and exits 0 — but **never moves the file to `inbox/processed/`**. On the next 2-second poll it is picked again and skipped again — forever. Over a day: 43,200 no-op iterations.

**Minimal diff direction:**
```diff
-  echo "Skipping (missing pm/topic): $picked" >&2
-  exit 0
+  echo "Quarantining malformed command (missing pm/topic): $picked" >&2
+  mkdir -p inbox/quarantine
+  mv "$picked" "inbox/quarantine/$(basename "$picked")"
+  exit 0
```

### 4. Silent skip not surfaced in inbox-loop.sh logs (inbox-loop.sh:66–73)
`inbox-loop.sh` only logs when exit code is non-2. Corrupt-file dispatch exits 0, so no log entry is ever written. Operations never sees the corrupt command. Fix: log exit-0 with non-empty `$out` as WARN.

### 5. `ls -dt` fragile on special chars in pm/topic (inbox-dispatch-next.sh:49)
`ls -dt "sessions/${pm}/inbox/"*"-${topic}"` — if `$pm` or `$topic` contains a space or glob char, this silently selects the wrong directory or fails. Low severity (values are controlled) but consistent with suite-wide fragility pattern.

### 6. `dispatch-pm-request.sh` stdout suppressed (inbox-dispatch-next.sh:47)
`>/dev/null` discards all dispatch diagnostic output. Non-zero exit is caught by `set -e` but any diagnostic detail is lost. Should pipe to log file instead.

## Positive finding (no action needed)
- `inbox-loop.sh` correctly uses `>>` (append) for `latest.log` (line 67) — contrast with `agent-exec-loop.sh` which uses `>` (truncate). Good pattern.

## Follow-up tasks

### Task A — Fix inbox-loop.sh + inbox-dispatch-next.sh reliability (for dev-infra when slot opens)
- **Owner seat:** `dev-infra`
- **Files:** `scripts/inbox-loop.sh`, `scripts/inbox-dispatch-next.sh`
- **Scope:** add `stop` subcommand; fix setsid PID race; quarantine malformed commands instead of infinite retry; log exit-0-with-output as WARN
- **Acceptance criteria:**
  - `./scripts/inbox-loop.sh stop` terminates loop and removes PIDFILE
  - A command file missing `topic:` is moved to `inbox/quarantine/` within one poll cycle and logged
  - Two concurrent `./scripts/inbox-loop.sh start` calls do not launch duplicate instances
- **Verification:** Manual test with corrupt command file; `ps aux | grep inbox-loop` after double-start
- **NOTE: dev-infra inbox is at capacity (3 items). Queue this task when a slot opens.**

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 4
- Rationale: Malformed-command infinite retry and missing `stop` subcommand are operational reliability gaps, but `inbox-loop.sh` is less critical than `agent-exec-next.sh` (which drives all agent scheduling). Fixing these prevents silent operational noise and potential duplicate dispatch, but does not block core agent execution. Lower ROI than cycles 12–14 findings.
