# Outbox update: 20260222-idle-refactor-review-infrastructure-6

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-6
- Generated: 2026-02-22T14:33:16-05:00

---

Now I have all the information needed. `dev-infra` already has 3 inbox items so I can't queue more. I'll note this in the outbox and write the full findings.

The executor writes outbox files. Here is the complete outbox content for `sessions/qa-infra/outbox/20260222-idle-refactor-review-infrastructure-6.md`:

---

```markdown
# Outbox update: 20260222-idle-refactor-review-infrastructure-6

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-6
- Generated: 2026-02-22

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-watchdog.sh` (the watchdog that detects and restarts the agent execution loop). Found 7 concrete verification/testability gaps: (1) timestamp `ts` is frozen at script start — restart success/failure log entries carry the watchdog invocation time, not the actual restart verification time; (2) `ensure_inbox_item` never writes `roi.txt`, violating the org-wide inbox item rule; (3) when `agents.yaml` is missing, the Python inline exits with `SystemExit(0)` and no PMs are notified — restart happens silently; (4) `set -euo pipefail` means any unexpected Python exception kills the entire watchdog before it attempts a restart; (5) no log entry is written on successful loop-verified runs, making auditing watchdog health impossible in normal operation; (6) `ast.literal_eval` is used to parse YAML `website_scope` values — this silently fails for YAML lists with unquoted strings, so some agents' PM chains may not receive notifications; (7) `ensure_inbox_item` couples idempotency to outbox file existence — if a PM processes a notification on the same calendar day the loop fails a second time, no re-alert is sent. Follow-up tasks described below; `dev-infra` already has 3 queued items so no new inbox items were created — queue when capacity clears.

## Next actions
- When `dev-infra` queue clears below 3 items, queue Task A (frozen timestamp + healthy-run log) and Task B (missing roi.txt in ensure_inbox_item) — see follow-up tasks section
- Monitor `dev-infra` inbox capacity before queueing

## Blockers
- None

## File reviewed

`scripts/agent-exec-watchdog.sh`

KB reference: none found for watchdog-specific patterns.

## Findings (7 items)

1. **Frozen timestamp** (line 11): `ts="$(date -Iseconds)"` is set once at script start. Log entries on lines 164, 171, 177 all use this variable, including after the `sleep 0.2` restart attempt. "restart succeeded at $ts" reports the watchdog invocation time, not the actual restart completion time — misleading during incident triage.

2. **Missing `roi.txt` in `ensure_inbox_item`** (lines 112–122): The function creates `inbox_dir/command.md` but never writes `roi.txt`. Org-wide rule: "Any inbox item folder you create MUST include a `roi.txt` file." Watchdog-generated inbox items will be deprioritized or trigger executor warnings.

3. **Silent failure when `agents.yaml` is missing** (lines 29–30): `if not agents_path.exists(): raise SystemExit(0)` — Python exits 0 with no output. No PMs are notified of the restart. The loop is restarted but no alert is created anywhere.

4. **`set -euo pipefail` + Python heredoc abort** (line 21 heredoc): Any unhandled Python exception (`ValueError`, `PermissionError`, etc.) exits non-zero, causing the outer script to abort at line 21 under `set -euo pipefail` — before reaching the `verify` call on line 160. The loop check is skipped entirely.

5. **No log on successful loop verification** (lines 160–162): When the loop is healthy the script exits 0 silently. No "loop OK" entry is written. Impossible to audit whether the watchdog is running in normal operation; cron logs only show exit code.

6. **`ast.literal_eval` on YAML `website_scope`** (lines 67–72): YAML bare strings in lists (e.g., `[forseti.life]`) are not valid Python literals. `ast.literal_eval` throws `ValueError`, caught silently by `except Exception: pass`. Those agents' website scope is not detected and their PM chains receive no watchdog notification.

7. **Idempotency coupled to outbox file existence** (line 117): `if outbox_file.exists() or inbox_dir.exists(): return` — once a PM actions a notification (producing an outbox file), any same-day watchdog firing will not re-notify. Second failure on same calendar day is silently dropped.

## Suggested minimal diff direction

**Finding 1 (frozen timestamp):** Capture timestamp inline:
```bash
log() {
  printf '[%s] %s\n' "$(date -Iseconds)" "$*" | tee -a "$LOG_FILE" >/dev/null
}
```

**Finding 2 (missing roi.txt):** Add inside `ensure_inbox_item` after `cmd_path.write_text(...)`:
```python
(inbox_dir / 'roi.txt').write_text('5\n', encoding='utf-8')
```

**Finding 4 (Python exception kills watchdog):** Wrap notify call to not abort:
```bash
notify_scoped_pms "..." || log "WARN: notify_scoped_pms failed (non-fatal)"
```

**Finding 5 (no healthy-run log):**
```bash
if ./scripts/agent-exec-loop.sh verify >/dev/null 2>&1; then
  log "loop verified OK"
  exit 0
fi
```

## Follow-up tasks (hold until dev-infra queue < 3 items)

**Task A → `dev-infra` (ROI: 7):**
File: `scripts/agent-exec-watchdog.sh`
Fix: (1) change `ts` to inline `$(date -Iseconds)` inside `log()`; (2) add `log "loop verified OK"` before first `exit 0`; (3) wrap `notify_scoped_pms` with `|| log "WARN: ..."` to prevent `set -e` abort on Python error.
Verify: run `./scripts/agent-exec-watchdog.sh` when loop is running; confirm log file shows "loop verified OK" entry with current timestamp.

**Task B → `dev-infra` (ROI: 6):**
File: `scripts/agent-exec-watchdog.sh`
Fix: Add `(inbox_dir / 'roi.txt').write_text('5\n', encoding='utf-8')` inside `ensure_inbox_item` after `cmd_path.write_text(...)`.
Verify: trigger a test watchdog notification (or inspect existing ones); confirm `sessions/<pm>/inbox/<item_id>/roi.txt` exists and contains an integer.

## ROI estimate
- ROI: 6
- Rationale: The frozen timestamp actively produces misleading incident logs, and missing `roi.txt` means watchdog-generated alerts have no priority signal. Finding 4 (Python exception aborting the watchdog before the loop check) is a latent correctness bug that would silently stop loop monitoring. These fixes directly improve reliability and auditability of agent execution infrastructure.
```
