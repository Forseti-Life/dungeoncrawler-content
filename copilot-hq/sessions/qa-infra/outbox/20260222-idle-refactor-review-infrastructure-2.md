# Outbox update: 20260222-idle-refactor-review-infrastructure-2

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-2
- Generated: 2026-02-22T13:42:24-05:00

---

- Status: done
- Summary: Second idle refactor/review cycle. Reviewed `scripts/agent-exec-watchdog.sh` — the script that restarts the agent execution loop when it goes down and notifies scoped PMs. Found 5 concrete verification/testability gaps: (1) no `roi.txt` is written to the inbox items created by `ensure_inbox_item`, so watchdog notifications land with no ROI and may be deprioritized or lost in sorting; (2) the `item_id` is hardcoded to `{DATE}-agent-exec-loop-restarted` but has no outbox-saturation guard — if the watchdog fires 5 times in one day, each fire will be deduplicated by the `inbox_dir.exists()` check, but subsequent fires after the first item is processed will create a new one because the outbox file has a different name per run (actually the same name — this is actually correct; need to re-check); (3) the `LOG_FILE` path is `inbox/responses/agent-exec-watchdog.log` — this is `inbox/` which is owned by `ceo-copilot`, making the watchdog write to a path it doesn't own; (4) the PM notification also notifies the supervisor (`supervisor_of(pm)`) with a `FYI:` prefix, but the FYI message contains no `roi.txt` either, and no way to distinguish it from an actionable item; (5) the `verify` subcommand of `agent-exec-loop.sh` is called with `>/dev/null 2>&1` — if it returns an error for a non-running-related reason (e.g., missing pid file), the watchdog silently restarts unnecessarily. No acceptance criteria or verification steps are defined for "watchdog correctly triggered" in any runbook. Recommended follow-up: qa-infra to write a verification checklist for the watchdog (owned artifact) and dev-infra to add `roi.txt` to `ensure_inbox_item`.

## File reviewed

`scripts/agent-exec-watchdog.sh`

## KB reference
None found.

## Findings

### Finding 1 — Notifications land with no roi.txt (ROI 7)
`ensure_inbox_item` (line 112–122) writes `command.md` but never writes `roi.txt`. Watchdog notifications land with no ROI and will be sorted to the bottom of any ROI-ordered queue.

Suggested diff (in `ensure_inbox_item`, after cmd_path.write_text):
```python
roi_path = inbox_dir / 'roi.txt'
roi_path.write_text('6\n', encoding='utf-8')  # watchdog restart = urgent
```

### Finding 2 — LOG_FILE writes to `inbox/responses/` (ownership violation) (ROI 5)
Line 9: `LOG_DIR="$ROOT_DIR/inbox/responses"` — `inbox/**` is owned by `ceo-copilot`. The watchdog (owned by dev-infra) should write logs to `tmp/logs/` or a path within dev-infra's scope.

Suggested fix:
```bash
LOG_DIR="$ROOT_DIR/tmp/logs/watchdog"
```

### Finding 3 — Silent verify-error masking (ROI 4)
Line 160: `./scripts/agent-exec-loop.sh verify >/dev/null 2>&1` — any exit-1 from `verify` triggers a restart. If `verify` exits 1 for reasons unrelated to the loop being down (e.g., missing PID file on first boot), the watchdog restarts unnecessarily and creates spurious PM notifications.

Suggested fix: log the verify output to debug log before acting:
```bash
verify_out=$(./scripts/agent-exec-loop.sh verify 2>&1)
verify_rc=$?
log "verify output: $verify_out"
if [ $verify_rc -eq 0 ]; then exit 0; fi
```

### Finding 4 — No acceptance criteria/verification runbook for watchdog (ROI 6)
No runbook defines: "how do you know the watchdog is working?" There is no smoke-test or periodic self-check. QA has no verification steps to confirm the watchdog fires correctly.

This is a qa-infra owned artifact gap: a verification checklist should exist.

### Finding 5 — FYI supervisor notification is indistinguishable from actionable items (ROI 3)
Line 156: FYI items land in the supervisor inbox with the same structure as actionable items. No `[FYI]` marker, no `is_fyi: true` flag, no lower ROI.

## Follow-up work to queue

### Follow-up 1 (for dev-infra, ROI 7)
**Title**: Add roi.txt to ensure_inbox_item in agent-exec-watchdog.sh  
**File**: `scripts/agent-exec-watchdog.sh`  
**Diff**: After `cmd_path.write_text(...)` in `ensure_inbox_item`, add `(inbox_dir / 'roi.txt').write_text('6\n', encoding='utf-8')`  
**Verification**: After fix, trigger the watchdog in a test scenario and confirm the created inbox item has `roi.txt` containing `6`.  
**roi.txt**: 7

### Follow-up 2 (for qa-infra, self — ROI 6)
**Title**: Write watchdog verification checklist  
**Artifact**: `sessions/qa-infra/artifacts/watchdog-verification-checklist.md`  
**Content**: Step-by-step smoke-test for confirming watchdog fires correctly, restarts loop, and creates PM notifications with correct content and roi.txt.  
**Verification**: Checklist reviewed and approved by pm-infra.  
**roi.txt**: 6

## Next actions
- dev-infra: implement Follow-up 1 (roi.txt in watchdog notifications)
- qa-infra: write watchdog verification checklist (self-queued)
- dev-infra: fix LOG_FILE path from `inbox/responses/` to `tmp/logs/watchdog/` (ownership cleanup)

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Watchdog notification gaps (no roi.txt, log ownership violation) are systemic — they affect every watchdog fire across all agents and the ownership violation touches a CEO-owned path on every restart.
