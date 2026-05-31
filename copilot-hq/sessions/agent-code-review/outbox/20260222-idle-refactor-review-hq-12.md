# Outbox update: 20260222-idle-refactor-review-hq-12

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-12
- Generated: 2026-02-22T16:29:48-05:00

---

Good, I have everything I need. `dev-infra` is at cap (3/3). Producing the outbox:

---

- Status: done
- Summary: Reviewed `scripts/notify-pending.sh` (118 lines, owner: `dev-infra`). Identified 6 concrete improvements: the cooldown state file and log are both written to `inbox/responses/` which pollutes the CEO inbox directory with operational state files; `pending_count` counts all CEO inbox subdirectories including already-processed items (no outbox deduplication), producing false-positive counts; the notification subject hardcodes `[Forseti]` even though the script is org-wide HQ tooling unrelated to Forseti; `sla-report.sh` exit-code is ignored (the breach count reads stdout only, so if sla-report exits non-zero the `|| true` swallows it silently and breach_count stays 0); the Twilio body sends only the subject line, not the full body; and there is no `--help` or usage output. Follow-up items for `dev-infra` deferred — queue still at cap (3/3: `-infra-16/17/18`).

## File reviewed

`scripts/notify-pending.sh` — 118 lines. Owner: `dev-infra`.  
Purpose: periodic notification to CEO when CEO inbox has pending items or SLA breaches exist. Supports log/sendmail/Twilio delivery.

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **State file + log written to `inbox/responses/`** — pollutes CEO inbox directory with `.notify-pending.last` and `notify-pending.log`. Other operational scripts (watchdog, sla-report) already use `tmp/logs/` or `inbox/responses/` inconsistently; this one adds more state to `inbox/responses/`. | Change `STATE_DIR="inbox/responses"` → `STATE_DIR="tmp/notify-pending"`. | dev-infra | 6 |
| 2 | **`pending_count` counts ALL CEO inbox subdirs, including already-processed items.** CEO inbox items whose outbox file exists are completed — not pending. The count should exclude any `${item}` where `sessions/ceo-copilot/outbox/${item}.md` exists. | Add a Python or shell loop filtering out items with a matching outbox file before counting. | dev-infra | 7 |
| 3 | **Subject hardcodes `[Forseti]`.** This script is org-wide HQ tooling; the `[Forseti]` prefix is misleading in email/SMS subject lines, especially when all breaches are in HQ-scoped agents unrelated to Forseti. | Change `"[Forseti] Pending decisions:..."` to `"[HQ] Pending decisions:..."`. | dev-infra | 4 |
| 4 | **Twilio send dispatches only the `$subject` as the SMS body.** Line 102: `--data-urlencode "Body=${subject}"` — the `$body` variable (with inbox count, breach count, and check paths) is never sent. Recipients get a one-liner with no actionable context. | Change to `--data-urlencode "Body=${subject}: ${body}"` or a condensed one-liner from `$body`. | dev-infra | 5 |
| 5 | **`breach_count` relies on stdout of `sla-report.sh` but exit code is ignored via `|| true`.** If `sla-report.sh` errors (e.g., missing `agents.yaml`), it may still emit partial "BREACH" lines — or emit nothing and `breach_count` stays 0 with no warning. The silent `|| true` means notification is silently suppressed on tooling failures. | Add stderr capture: `breach_count="$(./scripts/sla-report.sh 2>>"$STATE_DIR/notify-pending.log" | grep -c '^BREACH' || true)"`. | dev-infra | 5 |
| 6 | **Hardcoded absolute path in notification body.** Lines 61–62 embed `/home/keithaumiller/copilot-sessions-hq/...` — breaks if repo is cloned elsewhere. | Replace with `$ROOT_DIR/sessions/ceo-copilot/inbox` and `$ROOT_DIR/inbox/responses/forseti-triage-latest.log`. | dev-infra | 3 |

## Follow-up inbox items (staged — dev-infra at cap 3/3)

Queue when `-infra-16/-17/-18` clear:

**Item A — Fix pending_count to exclude completed items + fix state dir** (findings 1, 2) — ROI 7
- Scope: `scripts/notify-pending.sh` lines 22–24 (`STATE_DIR`), lines 38–41 (`pending_count` logic)
- AC: `pending_count` reflects only items without a matching outbox file; state written to `tmp/notify-pending/`
- Verify: Create CEO inbox item + outbox file → `pending_count` = 0; create item with no outbox → `pending_count` = 1; `ls inbox/responses/` contains no notify files

**Item B — Fix Twilio body + sla-report silent failure + hardcoded path** (findings 4, 5, 6) — ROI 5
- Scope: `send_twilio()` line 102, breach_count line 45, body lines 61–62
- AC: Twilio sends full body; sla-report stderr captured to log; paths use `$ROOT_DIR`
- Verify: `TWILIO_*` mocked; `NOTIFY_METHOD=twilio` run; SMS body contains breach/inbox counts; `notify-pending.log` receives sla-report stderr on error

**Item C — Fix `[Forseti]` subject prefix** (finding 3) — ROI 4
- Scope: `subject=` line 52
- AC: Subject reads `[HQ] Pending decisions:`
- Verify: `grep '\[Forseti\]' scripts/notify-pending.sh` returns no matches

## Next actions

- CEO/supervisor: queue Items A, B, C once `dev-infra` inbox drops below 3.
- No further action needed from `agent-code-review`.

## Blockers

- `dev-infra` inbox at cap (3/3): `-infra-16`, `-17`, `-18`. Follow-up work items cannot be queued per org-wide directive.

## ROI estimate

- ROI: 6
- Rationale: Finding 2 (false pending_count) means every notification overstates the CEO action queue — completed items remain in the inbox directory and inflate the count, creating noise that reduces signal value of the notification system over time. Fixing pending_count restores trust in the dashboard metric.
