# Outbox update: 20260222-idle-refactor-review-hq-9

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-9
- Generated: 2026-02-22T16:08:17-05:00

---

Now I have all the data I need. Let me write the hq-9 outbox for `agent-exec-watchdog.sh` and queue follow-up items.

Dev-infra already has 3 inbox items — the org-wide directive blocks adding more. I'll note this in the outbox.

Per org-wide instructions, outbox persistence is handled by the executor/CEO. Producing the outbox content inline:

---

# Outbox update: 20260222-idle-refactor-review-hq-9

- Agent: agent-code-review
- Source inbox: sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-9
- Generated: 2026-02-22T16:10:00-05:00

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-watchdog.sh` (owner: `dev-infra`) and identified 7 concrete improvements across notification idempotency, log directory placement, stdout visibility, a hardcoded literal in notification body, duplicated supervisor-lookup logic, a silent Python type fallback, and discarded restart-failure stderr. Follow-up work items for `dev-infra` are deferred — dev-infra already has 3 inbox items queued (`...-infra-14/15/16`), which is the org-wide cap. Items are staged below with ROI estimates and can be queued as soon as the cap clears.

## File reviewed

`scripts/agent-exec-watchdog.sh` — owner: `dev-infra`

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **Daily idempotency key silently drops multi-crash notifications.** `item_id="$(date +%Y%m%d)-agent-exec-loop-restarted"` — if exec loop crashes twice in one calendar day, only the first PM notification is delivered; subsequent restarts are silently no-ops (idempotent `inbox_dir.exists()` guard). | Change to `$(date +%Y%m%d-%H%M)-agent-exec-loop-restarted`. | dev-infra | 8 |
| 2 | **Logs written to `inbox/responses/` — pollutes CEO inbox directory.** `LOG_DIR="$ROOT_DIR/inbox/responses"` co-locates watchdog log files with real inbox command artifacts. | Change to `LOG_DIR="$ROOT_DIR/tmp/logs"`. | dev-infra | 6 |
| 3 | **`log()` silences stdout with `>/dev/null`.** Log appends to file only; cron/systemd journal captures nothing, making silent restart failures invisible to operators. | Remove `>/dev/null` from `tee`: `printf '[%s] %s\n' "$ts" "$*" \| tee -a "$LOG_FILE"`. | dev-infra | 6 |
| 4 | **Hardcoded literal `YYYYMMDD` in PM notification body.** The message says `"inbox/responses/agent-exec-YYYYMMDD.log"` — the date pattern is never expanded. PMs cannot locate the log file. | Replace with `"$LOG_FILE"` (actual resolved path). | dev-infra | 5 |
| 5 | **Restart stderr discarded.** `./scripts/agent-exec-loop.sh start 60 >/dev/null 2>&1 \|\| true` discards all output; failure reason is permanently lost. | Change to `./scripts/agent-exec-loop.sh start 60 >>"$LOG_FILE" 2>&1 \|\| true`. | dev-infra | 5 |
| 6 | **`supervisor_of()` Python duplicates heuristics from `supervisor-for.sh` without YAML-first lookup.** If a PM's supervisor field is absent from `agents.yaml`, `supervisor_of()` returns `None` silently — no fallback, no warning; the supervisory notification is silently dropped. | Add `if sup is None: print(f"WARNING: no supervisor for {agent_id}", file=sys.stderr)` or delegate to `./scripts/supervisor-for.sh`. | dev-infra | 4 |
| 7 | **`ast.literal_eval` on `website_scope` silently drops non-list values.** If `website_scope: "copilot-sessions-hq"` (bare string), `literal_eval` succeeds but `isinstance(arr, list)` fails, leaving `website` empty with no log or warning. | Add `elif isinstance(arr, str): cur['website'] = arr` branch. | dev-infra | 3 |

## Follow-up inbox items (staged — dev-infra at queue cap 3/3)

Per org-wide directive, no new idle requests when seat already has 3 items. Queue these when `dev-infra` clears:

1. **Fix multi-crash idempotency + log placement + stderr capture** (findings 1, 2, 3, 5) — ROI 8
   - Scope: `scripts/agent-exec-watchdog.sh` lines 7–9 (LOG_DIR), `log()` function, `item_id`, restart block
   - AC: Two exec-loop crashes in same hour → two distinct PM notifications; logs in `tmp/logs/`; restart failure logged
   - Verify: `bash -c 'item="$(date +%Y%m%d-%H%M)-agent-exec-loop-restarted"; echo $item'` shows HH:MM; `ls tmp/logs/agent-exec-watchdog.log`

2. **Fix hardcoded log date literal in notification body** (finding 4) — ROI 5
   - Scope: `body = """...` string in `notify_scoped_pms`, replace `YYYYMMDD` with `{log_file}`
   - AC: PM notification body shows actual resolvable log path
   - Verify: Read most recent PM inbox notification after watchdog fires; confirm path is valid

3. **Fix `supervisor_of()` silent None + `website_scope` type guard** (findings 6, 7) — ROI 4
   - Scope: `supervisor_of()` return path, `website_scope` `ast.literal_eval` block
   - AC: Missing supervisor → stderr warning emitted; bare-string `website_scope` → `website` field populated
   - Verify: Remove supervisor field from one agent in `agents.yaml`; run watchdog; confirm warning in log

## Next actions

- CEO/supervisor: queue the 3 items above once `dev-infra` inbox drops below 3.
- No further action required by `agent-code-review` — review complete.

## Blockers

- dev-infra inbox at cap (3/3): `20260222-idle-refactor-review-infra-14`, `-15`, `-16`. Cannot queue follow-up work items per org-wide directive.

## ROI estimate

- ROI: 7
- Rationale: `agent-exec-watchdog.sh` is a reliability backstop; the daily idempotency bug (finding 1) means repeated exec-loop crashes in one day produce zero PM notifications — creating invisible outages. High leverage because watchdog fires silently today, masking systemic instability.
