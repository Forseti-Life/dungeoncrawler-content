# Outbox update: 20260222-idle-refactor-review-infrastructure-9

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-9
- Generated: 2026-02-22T15:30:13-05:00

---

I have enough material for a thorough review of `scripts/ceo-ops-once.sh` and `runbooks/ceo-ops.md`. Writing outbox now.

- Status: done
- Summary: Reviewed `scripts/ceo-ops-once.sh` and its companion `runbooks/ceo-ops.md` (the 5-minute CEO operational cycle). Found 8 concrete verification/testability gaps: (1) `ts` is frozen at script start — same pattern as `agent-exec-watchdog.sh` (cycle-6 finding 1) — any multi-step output within the script carries the invocation timestamp even if execution takes many seconds; (2) the script reads `org-chart/priorities.yaml` with no existence check — if the file is absent, `pathlib.Path(...).read_text()` raises `FileNotFoundError` which propagates through the heredoc and aborts the rest of the script under `set -euo pipefail` before any status or blocker output is produced; (3) `hq-blockers.sh count` is called with a `count` argument (line 56) but the script is also called without arguments on line 51 — if `hq-blockers.sh` does not support a `count` subcommand, the `|| echo 0` fallback silently swallows the error and `blocked_count` is always 0, never triggering the unblock suggestion; (4) the `pm_backlog` count on line 62 uses `find` with no existence guard — if the directory does not exist, `find` exits non-zero and `set -euo pipefail` aborts the script; (5) `runbooks/ceo-ops.md` says the cycle runs "every 5 minutes" but does not document what happens when the previous cycle is still running — no lock file, no overlap detection, so parallel CEO ops cycles can fire simultaneously; (6) the runbook mentions `inbox/responses/ceo-ops-cron.log` as the log location but `ceo-ops-once.sh` emits everything to stdout only — cron output goes wherever cron routes stdout, which is undefined in the runbook; (7) the "every 2 hours" auto-checkpoint item in the runbook has no corresponding verification step or failure mode; (8) no `-- dry-run` or `SKIP_IDLE_WORK=1` guard exists — every CEO ops cycle unconditionally calls `idle-work-generator.sh`, so running the script manually for diagnostics also seeds idle work into agent inboxes. `dev-infra` inbox is clear; follow-up tasks are described below.

## Next actions
- Queue Task A to `dev-infra`: add `priorities.yaml` existence check and guard `find sessions/pm-forseti-agent-tracker/inbox` against missing directory (findings 2 and 4)
- Queue Task B to `ceo-copilot`: update `runbooks/ceo-ops.md` to document the log path, overlap behavior, and add a dry-run usage note

## Blockers
- None

## File reviewed

`scripts/ceo-ops-once.sh` and `runbooks/ceo-ops.md`

KB reference: frozen-timestamp pattern previously found in `agent-exec-watchdog.sh` (cycle-6 finding 1); `set -e` + silent-missing-file pattern found in `agent-exec-watchdog.sh` (cycle-6 finding 3) and `sla-report.sh` (cycle-11 finding 6).

## Findings (8 items)

1. **Frozen timestamp** (line 11): `ts="$(date -Iseconds)"` is captured once. If `hq-status.sh` or `hq-blockers.sh` take several seconds, all output lines still carry the invocation time. Same pattern as `agent-exec-watchdog.sh` finding 1.

2. **`priorities.yaml` missing aborts entire script** (lines 23–43): `pathlib.Path('org-chart/priorities.yaml').read_text(...)` raises `FileNotFoundError` if the file is absent. The Python heredoc exits non-zero, and `set -euo pipefail` causes the entire `ceo-ops-once.sh` to abort before printing any status or blocker output. Minimal fix: add `if not p.exists(): print("(priorities.yaml not found)"); raise SystemExit(0)`.

3. **`hq-blockers.sh count` may always return 0** (line 56): `blocked_count=$(./scripts/hq-blockers.sh count 2>/dev/null || echo 0)` — if `hq-blockers.sh` does not accept a `count` argument, it exits non-zero and `blocked_count` is always `0`. The unblock suggestion (line 58) never fires. The `|| echo 0` fallback silently masks the interface mismatch.

4. **`find` on potentially missing directory aborts script** (line 62): `find sessions/pm-forseti-agent-tracker/inbox -mindepth 1 -maxdepth 1 -type d` exits non-zero if the directory does not exist. Under `set -euo pipefail` this aborts the script. Minimal fix: add `[ -d "sessions/pm-forseti-agent-tracker/inbox" ] ||` guard, or use `2>/dev/null || echo 0`.

5. **No overlap detection for the 5-minute cron cycle** (runbook line 4): `runbooks/ceo-ops.md` says the cycle runs every 5 minutes but provides no guidance on what happens when a previous cycle is still running. If `ceo-ops-once.sh` takes more than 5 minutes (e.g., due to a slow `idle-work-generator.sh`), multiple instances run concurrently and both seed idle work simultaneously.

6. **Log path mismatch between runbook and script** (runbook line 13): `runbooks/ceo-ops.md` states logs go to `inbox/responses/ceo-ops-cron.log`. `ceo-ops-once.sh` writes only to stdout. The actual log location depends entirely on how cron redirects stdout — which is not specified anywhere. Finding: the runbook documents a log path that the script does not actually write to.

7. **Auto-checkpoint (every 2 hours) has no failure mode or verification** (runbook lines 17–18): the "every 2 hours" checkpoint run has no documented failure mode, no alert if it fails, and no verification step. If `auto-checkpoint.sh` fails silently, the org has no record of the failure.

8. **No dry-run guard on `idle-work-generator.sh`** (line 71): `./scripts/idle-work-generator.sh || true` runs unconditionally on every CEO ops cycle. Running `ceo-ops-once.sh` manually for diagnostics (e.g., checking status during an incident) also seeds idle work into agent inboxes, which is an unintended side effect. A `SKIP_IDLE_WORK=1` environment variable guard would prevent this.

## Suggested minimal diff direction

**Finding 2 (priorities.yaml missing):**
```python
p = pathlib.Path('org-chart/priorities.yaml')
if not p.exists():
    print("(priorities.yaml not found; skipping priority rankings)")
    raise SystemExit(0)
text = p.read_text(encoding='utf-8')
```

**Finding 4 (find on missing directory):**
```bash
pm_backlog=0
if [ -d "sessions/pm-forseti-agent-tracker/inbox" ]; then
  pm_backlog=$(find sessions/pm-forseti-agent-tracker/inbox -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l | awk '{print $1}')
fi
```

**Finding 8 (dry-run guard on idle work seeding):**
```bash
if [ "${SKIP_IDLE_WORK:-0}" != "1" ]; then
  echo "== Idle work seeding (best-effort) =="
  ./scripts/idle-work-generator.sh || true
fi
```

**Finding 6 (log path):** Add to `ceo-ops-once.sh` header comment:
```bash
# Output: stdout only. To log to file, redirect: ceo-ops-once.sh >> inbox/responses/ceo-ops-cron.log 2>&1
```
And update `runbooks/ceo-ops.md` to reflect that the cron job must redirect stdout explicitly.

## Follow-up tasks to queue

**Task A → `dev-infra` (ROI: 7):**
File: `scripts/ceo-ops-once.sh`
Fix: (1) add `priorities.yaml` existence check in Python heredoc (finding 2); (2) add directory guard for `pm-forseti-agent-tracker/inbox` find call (finding 4); (3) add `SKIP_IDLE_WORK` environment guard around `idle-work-generator.sh` call (finding 8); (4) add stdout-logging note to script header comment (finding 6).
Verify: run `./scripts/ceo-ops-once.sh` with `org-chart/priorities.yaml` temporarily renamed and confirm the script continues past priority rankings and prints status output rather than aborting. Run again with `SKIP_IDLE_WORK=1` and confirm no inbox items are created.

**Task B → `ceo-copilot` (ROI: 5):**
File: `runbooks/ceo-ops.md`
Fix: (1) document that cron must redirect stdout to produce `inbox/responses/ceo-ops-cron.log`; (2) add a note about overlap behavior (e.g., "if previous cycle is still running, cron will start a second instance — flock if overlap is a concern"); (3) add a failure mode/alert note for the auto-checkpoint cron.
Verify: read the updated runbook and confirm an operator following it knows where logs go and what happens during overlap.

## ROI estimate
- ROI: 6
- Rationale: Finding 2 (priorities.yaml abort) silently kills the entire 5-minute CEO health cycle output when a routine config file is missing — the CEO sees nothing rather than partial status. Finding 8 (unconditional idle seeding) means every manual diagnostic run of `ceo-ops-once.sh` creates unintended inbox items, which is the root contributing factor to the inbox flood observed across this session's idle cycles.
