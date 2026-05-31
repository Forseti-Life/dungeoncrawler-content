# Outbox update: 20260222-idle-refactor-review-hq-17

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-17
- Generated: 2026-02-22T17:45:12-05:00

---

- Status: done
- Summary: Reviewed `scripts/notify-pending.sh` (118 lines, full read via view tool). This script notifies the CEO of pending inbox items and SLA breaches via log/sendmail/Twilio. Found 5 concrete issues: (1) hardcoded absolute path in the notification body (line 60–61) breaks portability; (2) the state file is stored in `inbox/responses/.notify-pending.last` — inside the `inbox/` tree owned by `ceo-copilot`, not in `tmp/` where operational state belongs per the file-ownership map; (3) `pending_count` counts ALL CEO inbox items including already-locked (in-progress) items, so the notification count overstates true pending work; (4) no blocked-agent count included — operators get inbox count and SLA breaches but not the most actionable signal (how many agents are stuck); (5) cooldown state is stored as a raw epoch integer with no format validation — corrupt state file (e.g., non-numeric content) causes silent `$((now - last))` arithmetic errors under `set -euo pipefail`.

## File reviewed
`scripts/notify-pending.sh` — CEO notification script; fires when CEO inbox has pending items or SLA breaches; supports log/sendmail/Twilio delivery methods.

## Findings

### Finding 1 (HIGH) — Hardcoded absolute path in notification body (line 60–61)
```
- /home/keithaumiller/copilot-sessions-hq/sessions/ceo-copilot/inbox
```
Any operator on a different host or path gets a useless link. Should use `$ROOT_DIR`.

**Owner:** `dev-infra`

**Fix (lines 60–61):**
```bash
-  - /home/keithaumiller/copilot-sessions-hq/sessions/ceo-copilot/inbox
-  - /home/keithaumiller/copilot-sessions-hq/inbox/responses/forseti-triage-latest.log
+  - ${ROOT_DIR}/sessions/ceo-copilot/inbox
+  - ${ROOT_DIR}/inbox/responses/forseti-triage-latest.log
```

### Finding 2 (MEDIUM) — State file in `inbox/responses/` violates ownership map
Line 24: `STATE_FILE="$STATE_DIR/.notify-pending.last"` where `STATE_DIR="inbox/responses"`. Per `file-ownership.md`, `inbox/**` is owned by `ceo-copilot`; operational state files belong in `tmp/`. This creates a write conflict if `ceo-copilot` is ever given strict inbox write-protection.

**Owner:** `dev-infra`

**Fix:**
```bash
-STATE_DIR="inbox/responses"
+STATE_DIR="tmp/notify"
```

**Verification:** Confirm `tmp/notify/.notify-pending.last` is created on next run; confirm cooldown still works.

### Finding 3 (MEDIUM) — No blocked-agent count in notification
Lines 38–50: notification body includes CEO inbox count and SLA breaches, but not blocked-agent count — the most actionable signal for the current escalation flood. Adding it requires one call to `./scripts/hq-blockers.sh count`.

**Owner:** `dev-infra`

**Fix (after line 45):**
```bash
+blocked_agent_count=0
+if [ -x ./scripts/hq-blockers.sh ]; then
+  blocked_agent_count="$(./scripts/hq-blockers.sh count 2>/dev/null || echo 0)"
+fi
```
Add to subject and body:
```bash
-subject="[Forseti] Pending decisions: inbox=${pending_count} sla_breaches=${breach_count}"
+subject="[Forseti] Pending decisions: inbox=${pending_count} blocked=${blocked_agent_count} sla_breaches=${breach_count}"
```
Update `if` condition (line 48):
```bash
-if [ "$pending_count" -eq 0 ] && [ "$breach_count" -eq 0 ]; then
+if [ "$pending_count" -eq 0 ] && [ "$breach_count" -eq 0 ] && [ "$blocked_agent_count" -eq 0 ]; then
```

### Finding 4 (LOW) — Corrupt state file causes arithmetic error under `set -euo pipefail`
Lines 28–31: reads `last` from file and uses it in `$((now - last))`. If the file contains non-numeric content, bash arithmetic fails and the script exits with an error under `set -euo pipefail`, suppressing all notifications until the file is manually fixed.

**Owner:** `dev-infra`

**Fix (line 30):**
```bash
-  last="$(cat "$STATE_FILE" 2>/dev/null || echo 0)"
+  last="$(cat "$STATE_FILE" 2>/dev/null | tr -cd '0-9' || echo 0)"
```

### Finding 5 (LOW) — Twilio sends only subject, not full body (line 103)
`send_twilio()` line 103: `"Body=${subject}"` — sends only the subject line as the SMS body. The full `$body` (inbox count, SLA count, paths) is dropped. SMS operators see only the subject.

**Owner:** `dev-infra`

**Fix:**
```bash
-    --data-urlencode "Body=${subject}" \
+    --data-urlencode "Body=${subject}: ${body}" \
```
(Or truncate to 160 chars for SMS compatibility.)

## Next actions
- [ ] dev-infra: replace hardcoded paths with `$ROOT_DIR` in notification body (Finding 1, ROI=8)
- [ ] dev-infra: move state file from `inbox/responses/` to `tmp/notify/` (Finding 2, ROI=6)
- [ ] dev-infra: add blocked-agent count to notification subject/body (Finding 3, ROI=12)
- [ ] dev-infra: sanitize state file read to prevent arithmetic errors (Finding 4, ROI=5)
- [ ] dev-infra: fix Twilio send to include full body (Finding 5, ROI=4)

## Blockers
- None.

## ROI estimate
- ROI: 12
- Rationale: Finding 3 (missing blocked-agent count) is the highest-value change — it makes notifications actionable for the current escalation flood by surfacing the exact signal operators need. Findings 1 and 4 are portability/reliability fixes with zero risk. All are small, contained changes to a single file owned by dev-infra.
