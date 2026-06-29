# Outbox update: 20260222-idle-refactor-review-infrastructure-10

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-10
- Generated: 2026-02-22T14:46:01-05:00

---

`dev-infra` inbox is now clear. I have enough material to write the outbox. Writing findings on `scripts/notify-pending.sh`.

- Status: done
- Summary: Reviewed `scripts/notify-pending.sh` (the script that detects pending CEO inbox items and SLA breaches and notifies via log, sendmail, or Twilio). Found 7 concrete verification/testability gaps: (1) the cooldown check on line 33 uses integer arithmetic `$((now - last))` but `last` is read from a file with no format validation — a corrupted or non-numeric `STATE_FILE` causes the arithmetic to fail under `set -euo pipefail`, aborting the script silently; (2) `pending_count` counts ALL subdirectories in `ceo-copilot/inbox`, including already-processed items that were not cleaned up — the metric may be permanently nonzero with no actionable items; (3) `send_twilio` sends only `$subject` as the SMS body (line 102), discarding `$body` — SLA breach count and inbox count are in the subject but the action items (which paths to check) are lost; (4) `STATE_FILE` is written unconditionally after send (line 116), even if the send method fails — the cooldown is marked as served for a notification that was never delivered; (5) the script has no verification that `ceo-copilot/inbox` directories are actually pending (vs. processed/locked) — it counts everything including `.exec-lock` dirs; (6) `NOTIFY_METHOD` accepts only `log|sendmail|twilio` with an unknown-method fallback to log — but there is no smoke-test or dry-run mode to verify configuration without triggering real notifications; (7) the absolute path in the body (line 60–61) is hardcoded to `/home/keithaumiller/copilot-sessions-hq` rather than derived from `$ROOT_DIR`, breaking portability if the repo moves. `dev-infra` inbox was at capacity during cycles 6–7; it is now clear, so follow-up tasks are ready to queue.

## Next actions
- Queue Task A to `dev-infra`: fix corrupted-STATE_FILE arithmetic crash and decouple cooldown write from send success
- Queue Task B to `dev-infra`: fix hardcoded path in body and Twilio body truncation (drop `$body` fix)

## Blockers
- None

## File reviewed

`scripts/notify-pending.sh`

KB reference: none found for notify-pending-specific patterns.

## Findings (7 items)

1. **Corrupted STATE_FILE aborts script** (lines 28–31): `last="$(cat "$STATE_FILE" 2>/dev/null || echo 0)"` correctly suppresses read errors, but `last="${last:-0}"` only guards empty strings — a file containing non-numeric content like `abc` or `\n` causes `$((now - last))` on line 33 to abort under `set -euo pipefail` with "invalid arithmetic operator".

2. **`pending_count` counts all inbox subdirs including processed/locked ones** (lines 39–41): `find "$ceo_inbox" -mindepth 1 -maxdepth 1 -type d` counts every directory including `.exec-lock` subdirectories of active items, previously processed but not cleaned up items, and in-progress work. The metric is unreliable; CEO may receive repeated spurious notifications.

3. **Twilio send drops `$body`** (line 102): `--data-urlencode "Body=${subject}"` sends only the subject line as the SMS body. The `$body` variable containing the "Check:" paths is constructed but never sent for Twilio method. Recipient gets counts but not the actionable paths.

4. **Cooldown written even on send failure** (line 116): `echo "$now" > "$STATE_FILE"` executes after the `case` statement regardless of whether the send succeeded. A failed `sendmail` or Twilio call (non-zero exit) is suppressed by `||` patterns inside the functions, so line 116 still runs — marking a 30-minute cooldown for a notification that was never delivered.

5. **No filtering of `.exec-lock` dirs from `pending_count`** (lines 39–41): `.exec-lock` is a sub-directory of each active inbox item, not a top-level inbox item, so this specific issue is less severe — but the count still includes items that have outbox files (already actioned). There is no cross-check against `sessions/ceo-copilot/outbox/` to subtract already-processed items.

6. **No dry-run/smoke-test mode** (lines 106–114): There is no `--dry-run` flag to verify that credentials/addresses are configured correctly without sending. Testing Twilio or sendmail configuration requires a live notification or manual inspection of env vars.

7. **Hardcoded absolute path in body** (lines 60–61): `/home/keithaumiller/copilot-sessions-hq/sessions/ceo-copilot/inbox` is hardcoded. If the repo moves, the notification body points to a stale path. Fix: use `$ROOT_DIR` variable already set on line 12.

## Suggested minimal diff direction

**Finding 1 (STATE_FILE arithmetic safety):**
```bash
# After line 31, add numeric validation:
if ! [[ "$last" =~ ^[0-9]+$ ]]; then last="0"; fi
```

**Finding 3 (Twilio drops body):**
```bash
# Line 102 — change Body to include body:
--data-urlencode "Body=${subject}: ${body}" \
```

**Finding 4 (cooldown on failure):** Track send result:
```bash
send_result=0
case "$METHOD" in
  log) send_log ;;
  sendmail) send_sendmail || send_result=$? ;;
  twilio) send_twilio || send_result=$? ;;
  *) send_log; echo "Unknown NOTIFY_METHOD" >&2 ;;
esac
if [ "$send_result" -eq 0 ]; then
  echo "$now" > "$STATE_FILE"
fi
```

**Finding 7 (hardcoded path):**
```bash
# Line 60 — replace hardcoded path:
- /home/keithaumiller/copilot-sessions-hq/sessions/ceo-copilot/inbox
# with:
- ${ROOT_DIR}/sessions/ceo-copilot/inbox
```

## Follow-up tasks to queue

**Task A → `dev-infra` (ROI: 7):**
File: `scripts/notify-pending.sh`
Fix: (1) add numeric validation of `last` after reading STATE_FILE (finding 1); (2) decouple STATE_FILE write from send — only update cooldown if send succeeded (finding 4); (3) replace hardcoded `/home/keithaumiller/...` path with `$ROOT_DIR` (finding 7).
Verify: create a non-numeric STATE_FILE (`echo abc > inbox/responses/.notify-pending.last`) and run the script — confirm it does not crash and sends normally. After a send, confirm STATE_FILE is only updated on success.

**Task B → `dev-infra` (ROI: 5):**
File: `scripts/notify-pending.sh`
Fix: fix Twilio `Body` to include `$body` content (finding 3); add `--dry-run` flag that prints the notification to stdout without sending (finding 6).
Verify: set `NOTIFY_METHOD=twilio` with a dry-run flag — confirm output shows full body with path references. Run unit test with mock Twilio env vars.

## ROI estimate
- ROI: 6
- Rationale: Finding 1 (STATE_FILE crash) is a latent reliability bug that would silence all notifications after a file corruption event, and finding 4 (cooldown on failure) could suppress alerts for 30 minutes after a failed notification delivery. Both are correctness issues in the primary alerting path for the CEO.
