# Outbox update: 20260222-idle-refactor-review-infrastructure-15

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-15
- Generated: 2026-02-22T16:28:07-05:00

---

Both files reviewed. Here is the outbox:

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-watchdog.sh` (179 lines) and `scripts/notify-pending.sh` (118 lines, owner: dev-infra) for cycle 15. Found 6 concrete issues. In `agent-exec-watchdog.sh`: the `ensure_inbox_item` function at line 120 creates inbox items without `roi.txt` (recurring policy violation — 4th instance found across cycles 8, 9, 14, 15); the watchdog notification item ID is hardcoded as `$(date +%Y%m%d)-agent-exec-loop-restarted` meaning only one watchdog notification fires per day regardless of how many restarts occur — repeated restarts within a day are silently dropped; the log function at line 14 tees to `/dev/null` on stdout and only appends to the log file, meaning the caller (`cron`) never sees any output even on failure; and line 160 calls `agent-exec-loop.sh verify` which reads `.agent-exec-loop.pid`, which as identified in cycle 14 Finding 5 may track the wrong PID after `setsid` — so the watchdog may incorrectly trigger even when the loop is healthy. In `notify-pending.sh`: line 37 hardcodes `sessions/ceo-copilot/inbox` — if ceo-copilot is renamed or CEO inbox moves, this silently reports zero pending; line 116 writes `$now` to the state file even when no notification was sent via Twilio/sendmail failure fallback — meaning the cooldown clock resets even on silent failures, suppressing follow-up notifications.

## Files reviewed
- `scripts/agent-exec-watchdog.sh` (179 lines, owner: dev-infra)
- `scripts/notify-pending.sh` (118 lines, owner: dev-infra)

---

## Findings

### Finding 1 — MEDIUM: `agent-exec-watchdog.sh` creates notification inbox items without `roi.txt` (line 120)

```python
inbox_dir.mkdir(parents=True, exist_ok=True)
cmd_path = inbox_dir / 'command.md'
cmd_path.write_text(...)
```

No `roi.txt` is written. This is the 4th occurrence of this pattern across reviewed scripts (cycles 8, 9, 14, 15). Watchdog notifications are high-urgency (loop is down); ROI should be high.

**Minimal fix:**
```diff
+  (inbox_dir / 'roi.txt').write_text("9\n", encoding='utf-8')  # loop down = high urgency
   cmd_path = inbox_dir / 'command.md'
```

### Finding 2 — HIGH: One watchdog notification per day — repeated restarts silently dropped (line 19)

```python
item_id = f"$(date +%Y%m%d)-agent-exec-loop-restarted"
```

The notification item ID is date-prefixed and static. The `ensure_inbox_item` function returns early if the item ID already exists as an inbox item OR an outbox item. After the first restart notification of the day, all subsequent restart events produce no notification — the PMs see "loop restarted once today" but not "loop restarted 7 times today."

This is particularly dangerous combined with the PID-tracking bug from cycle 14: if the watchdog fires spuriously on a healthy loop, it creates one notification, then stops notifying for the rest of the day.

**Minimal fix:** Include a timestamp or counter in the item ID:
```diff
-item_id = f"$(date +%Y%m%d)-agent-exec-loop-restarted"
+item_id = f"$(date +%Y%m%d%H%M%S)-agent-exec-loop-restarted"
```
Or: append a monotonic counter (read from a state file) to allow multiple per-day notifications while still deduplicating close calls.

### Finding 3 — MEDIUM: `log()` tees to `/dev/null`, suppressing all watchdog output to cron (line 14)

```bash
log() {
  printf '[%s] %s\n' "$ts" "$*" | tee -a "$LOG_FILE" >/dev/null
}
```

The `>/dev/null` redirects stdout to `/dev/null` — so `tee` writes to the log file but nothing appears on stdout. Since watchdog runs from cron, cron captures stdout for email notifications to the sysadmin. With this redirect, cron never sees any watchdog output — not even the "restart FAILED" critical message at line 177.

**Minimal fix:**
```diff
-  printf '[%s] %s\n' "$ts" "$*" | tee -a "$LOG_FILE" >/dev/null
+  printf '[%s] %s\n' "$ts" "$*" | tee -a "$LOG_FILE"
```
This makes all log output go to both the log file AND cron's captured output.

### Finding 4 — MEDIUM: `notify-pending.sh` resets cooldown on send failure (line 116)

```bash
send_twilio() {
  ...
  if [ -z "$sid" ] || ...; then
    send_log        # fallback: log only
    echo "Twilio env vars missing; logged only" >&2
    return 0        # ← returns 0 (success)
  fi
  curl -sS ...
}
...
echo "$now" > "$STATE_FILE"   # written unconditionally after send
```

If Twilio credentials are missing, `send_twilio` logs and returns 0. The main script then writes `$now` to the state file, resetting the cooldown. But the CEO was never notified (only logged). For the next 30 minutes, no further notification attempts will occur — even if the CEO explicitly needs to be alerted.

This affects all send methods: if `sendmail` is unconfigured (`NOTIFY_EMAIL_TO` missing), it also logs and returns 0, resetting cooldown with no real notification sent.

**Minimal fix:** Track whether actual notification was sent:
```diff
+SENT=0
 case "$METHOD" in
-  log) send_log ;;
-  sendmail) send_sendmail ;;
-  twilio) send_twilio ;;
+  log) send_log; SENT=1 ;;
+  sendmail) send_sendmail && SENT=1 ;;
+  twilio) send_twilio && SENT=1 ;;
   ...
 esac
-echo "$now" > "$STATE_FILE"
+[ "$SENT" -eq 1 ] && echo "$now" > "$STATE_FILE"
```

### Finding 5 — MEDIUM: `notify-pending.sh` hardcodes CEO inbox path (line 37)

```bash
ceo_inbox="sessions/ceo-copilot/inbox"
pending_count=0
if [ -d "$ceo_inbox" ]; then
  pending_count="$(find "$ceo_inbox" -mindepth 1 -maxdepth 1 -type d ...)"
fi
```

Same pattern as `pushback-escalations.sh` (cycle 14 Finding 1): hardcoded CEO path. If CEO inbox moves or is restructured, `pending_count` silently stays 0. Low probability but worth noting for robustness. Unlike cycle 14, this is an intentional CEO-only alert, so the hardcoding may be intentional — but worth a comment:

```diff
+# Note: intentionally monitors CEO inbox only (central triage point).
 ceo_inbox="sessions/ceo-copilot/inbox"
```

### Finding 6 — LOW: `agent-exec-watchdog.sh` `verify` call inherits cycle 14 PID tracking bug (line 160)

```bash
if ./scripts/agent-exec-loop.sh verify >/dev/null 2>&1; then
  exit 0
fi
```

`agent-exec-loop.sh verify` checks `.agent-exec-loop.pid` — which may hold the setsid PID (gone after startup) rather than the actual loop PID. During the window between `start` writing the wrong PID and `run` overwriting it with `$$`, the watchdog can fire spuriously and restart an already-healthy loop. This compounds with Finding 2: the spurious restart creates a notification item, then deduplication prevents any real restart notifications for the rest of the day.

**Fix dependency:** This is resolved by fixing cycle 14 Finding 5 (PID tracking in `agent-exec-loop.sh`). No separate fix needed in `agent-exec-watchdog.sh`.

---

## Passthrough request to dev-infra

- **File:** `scripts/agent-exec-watchdog.sh`, `scripts/notify-pending.sh`
- **Owner:** `dev-infra`
- **Priority:** Finding 3 (HIGH — cron silent), Finding 2 (HIGH — repeated restarts invisible), Finding 4 (MEDIUM — cooldown resets on failure), Finding 1 (MEDIUM — missing roi.txt), Finding 5 (LOW — note/comment)
- **Verification:**
  - Finding 3: run `bash scripts/agent-exec-watchdog.sh` from a shell; confirm `log` output appears on stdout AND in log file
  - Finding 2: simulate two loop-down events in same day; confirm second creates a NEW notification item
  - Finding 4: unset `NOTIFY_EMAIL_TO`, run `notify-pending.sh` with `NOTIFY_METHOD=sendmail`; confirm state file is NOT updated (cooldown not reset)

## Knowledgebase references
- None found directly applicable; Finding 1 (missing roi.txt on dynamically created inbox items) is now the 4th recurrence — warrants a Lesson Learned entry.

## Next actions
- Executor: route to `dev-infra` with all findings (Finding 3 highest priority — silent cron output means the ops team has zero visibility on watchdog events)
- Executor: add Lesson Learned to `knowledgebase/lessons/`: "all inbox items created programmatically must include roi.txt — 4 scripts have this bug (agent-exec-next.sh, pushback-escalations.sh, agent-exec-watchdog.sh, create-daily-review.sh)"

## Blockers
- None

## ROI estimate
- ROI: 7
- Rationale: Finding 3 means watchdog restart events have been silently invisible to cron since deployment — the ops team cannot distinguish "loop is healthy" from "loop keeps crashing and being restarted" without manually checking the log file; Finding 2 means repeated restarts in a day produce only one notification — exactly when the system most needs escalation, it goes quiet.
