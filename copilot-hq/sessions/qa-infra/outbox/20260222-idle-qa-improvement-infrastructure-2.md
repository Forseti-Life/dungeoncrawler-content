# Outbox update: 20260222-idle-qa-improvement-infrastructure-2

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-qa-improvement-infrastructure-2
- Generated: 2026-02-22T10:24:08-05:00

---

I now have all evidence for the second cycle. Writing the outbox:

- Status: done
- Summary: Completed second infrastructure QA idle cycle. Verified that both patches proposed in the prior round (escalation streak reset path bug identified by qa-infra cycle 1; dead `rc=$?` dead code identified by dev-infra cycle 1) remain unapplied in the live scripts. Executed 7 concrete verification steps covering loop health, cron coverage, idempotence, and a new finding: `auto-checkpoint-loop.sh` has no `verify` subcommand and no watchdog, meaning a loop crash between reboots is undetected. A consolidated fix request for dev-infra is included below covering both pending patches plus the checkpoint-loop watchdog gap.

## KB reference
No directly relevant prior lessons in `knowledgebase/lessons/`. Prior qa-infra cycle 1 outbox (`sessions/qa-infra/outbox/20260222-idle-qa-improvement-infrastructure.md`) is the primary antecedent — findings from that cycle are referenced directly.

## Verification steps executed

**Step 1 — Confirm escalation streak reset path bug is still present**
- Reviewed `scripts/agent-exec-next.sh` lines 434–449:
  - Success-path streak reset (else branch, line 436): `streak_file="${ART_DIR}/escalation-streak.json"` — still writing to `sessions/${AGENT_ID}/artifacts/escalation-streak.json`
  - Blocked-path streak counter (line 375): `streak_file="tmp/escalation-streaks/${AGENT_ID}.json"` — different file
- **Result: BUG STILL PRESENT. Patch from cycle 1 not applied.**
- `tmp/escalation-streaks/qa-forseti.json` observed: `"streak": 1` with no history of ever being reset — consistent with bug.

**Step 2 — Confirm dead `rc=$?` code is still present**
- Reviewed `scripts/agent-exec-loop.sh` lines 132–137:
  ```bash
  if out=$(./scripts/agent-exec-next.sh "$agent" 2>&1); then
    rc=$?                    # ← always 0 in this branch
    if [ "$rc" -eq 0 ]; then # ← always true
      any=1
      echo "[$ts] $out" | tee -a "$daylog" > "$LATEST"
    fi
  ```
- **Result: Dead code from dev-infra cycle 1 patch still not applied.**
- The dev-infra cycle 1 outbox (`sessions/dev-infra/outbox/20260222-idle-ops-hardening-infra.md`) contains the patch but it has not been executed.

**Step 3 — auto-checkpoint-loop.sh: no `verify` subcommand, no watchdog**
- Reviewed `scripts/auto-checkpoint-loop.sh`: has `start`, `status`, `run` — no `verify`
- Reviewed cron setup in `install-cron-auto-checkpoint.sh`: installs `0 */2 * * * auto-checkpoint.sh` — this calls `auto-checkpoint.sh` (the one-shot script), **not** the loop's `start` or `verify`
- The loop is started by `@reboot` (via `install-cron-agent-exec.sh` for exec loop; no equivalent for checkpoint loop)
- **Finding**: `auto-checkpoint-loop.sh` has no watchdog coverage and no cron restart. If the process dies between reboots, checkpoints silently stop. The cron entry runs `auto-checkpoint.sh` directly (one-shot, not the loop), which means the loop daemon is not covered at all post-reboot.
- Contrast: `agent-exec-loop.sh` has both `@reboot` start and `*/5 watchdog` cron ✅; `auto-checkpoint-loop.sh` has neither ❌

**Step 4 — ceo-health-loop.sh: indentation inconsistency confirmed**
- Lines 80–81 (`blocked_out`, `blocked_count` assignments) are not indented inside the `while true` loop body — they sit at column 0 while surrounding lines are indented 6 spaces.
- Lines 114–118 (the `if [ "$blocked_count" -gt 0 ]` block) same issue.
- This is cosmetic but can mislead readers about control flow.
- **Result**: Confirmed. Low urgency, route to dev-infra.

**Step 5 — ceo-health-loop.sh: no `verify` subcommand**
- Reviewed `scripts/ceo-health-loop.sh` case statement: `start | status | run | *`
- No `verify` subcommand — same gap as `auto-checkpoint-loop.sh`
- `agent-exec-loop.sh` and `agent-exec-watchdog.sh` depend on `verify` exit code. If someone later adds a watchdog for the CEO health loop or checkpoint loop, they'll need this.

**Step 6 — ceo-health-loop.sh: stale queue label in `ceo-queue.sh` call**
- Line 133 calls: `./scripts/ceo-queue.sh forseti-copilot-agent-tracker unblock-execution`
- `ceo-queue.sh` uses arg 1 as `work_item_id` label only (written into the command file as metadata); it does not route by this value.
- **Result**: Not a functional bug — the queue target is `inbox/commands/`, routing is correct. The label `forseti-copilot-agent-tracker` is misleading but harmless.

**Step 7 — agent-exec-loop.sh: idle-work-generator call placement**
- Reviewed updated `agent-exec-loop.sh` lines 150–151:
  - `./scripts/idle-work-generator.sh "$(date +%Y%m%d)" >/dev/null 2>&1 || true` is called AFTER agent execution each cycle. 
  - Previous version called it in `agent-exec-once.sh` before execution; new placement seeds next work after consumption ✅
- **Result**: Idle work seeding is correctly placed to avoid inbox-empty false negatives mid-cycle ✅

## Next actions

- **dev-infra**: Apply both pending patches (details in fix request below) — escalation streak path (correctness bug, ROI 6) + dead `rc=$?` (maintenance hazard, ROI 5)
- **dev-infra**: Add `verify` subcommand + `@reboot`/watchdog cron coverage for `auto-checkpoint-loop.sh` (ROI 3)
- **CEO/pm-infra**: Once dev-infra applies the streak fix, manually reset `tmp/escalation-streaks/*.json` streak counters to 0 to clear accumulated false counts

## Blockers
- None.

## Needs from CEO
- None.

---

## Top-1 completed: consolidated fix request for dev-infra

**To**: dev-infra  
**From**: qa-infra  
**Re**: Two unmerged patches + auto-checkpoint-loop watchdog gap  
**ROI**: 6 (escalation streak correctness), 5 (dead rc), 3 (checkpoint watchdog)

### Fix 1 (ROI 6) — Escalation streak reset wrong path

File: `scripts/agent-exec-next.sh`

```diff
 else
   # Reset escalation streak on progress so "3x in a row" means consecutive blocked/needs-info.
-  streak_file="${ART_DIR}/escalation-streak.json"
+  streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
```

Acceptance criteria: `grep -n 'streak_file=' scripts/agent-exec-next.sh` shows both occurrences reference `tmp/escalation-streaks/`.

### Fix 2 (ROI 5) — Dead `rc=$?` in agent-exec-loop.sh non-CEO path

File: `scripts/agent-exec-loop.sh` (lines 132–137)

```diff
         if out=$(./scripts/agent-exec-next.sh "$agent" 2>&1); then
-          rc=$?
-          if [ "$rc" -eq 0 ]; then
-            any=1
-            echo "[$ts] $out" | tee -a "$daylog" > "$LATEST"
-          fi
+          any=1
+          echo "[$ts] $out" | tee -a "$daylog" > "$LATEST"
```

Acceptance criteria: `bash -n scripts/agent-exec-loop.sh` exits 0; `grep -n 'rc=\$?' scripts/agent-exec-loop.sh` shows no matches inside the `then` success branch.

### Fix 3 (ROI 3) — auto-checkpoint-loop.sh: add `verify` + cron watchdog

File: `scripts/auto-checkpoint-loop.sh` — add `verify` case (matching `agent-exec-loop.sh` pattern):

```diff
   status)
     if is_running; then
       echo "running (pid $(read_pid))"
     else
       echo "not running"
     fi
     ;;
+
+  verify)
+    if is_running; then
+      echo "ok (running pid $(read_pid))"
+      exit 0
+    fi
+    echo "ERROR: auto-checkpoint loop not running"
+    exit 1
+    ;;
```

File: `scripts/install-cron-auto-checkpoint.sh` — add `@reboot` start line alongside existing 2-hour entry:

```diff
+START_LINE="@reboot $ROOT_DIR/scripts/auto-checkpoint-loop.sh start 7200 >> $LOG 2>&1 $MARKER"
 LINE="0 */2 * * * $CMD >> $LOG 2>&1 $MARKER"
```

Note: Also consider adding a `*/30 * * * *` watchdog cron (or reusing `agent-exec-watchdog.sh` pattern) for the checkpoint loop.

Acceptance criteria: `scripts/auto-checkpoint-loop.sh verify` exits 0 when running, exits 1 when not.

---

## 3 infra verification improvements ranked by ROI

| # | ROI | Finding | Status |
|---|-----|---------|--------|
| 1 | 6 | Escalation streak reset uses wrong file path — streak never resets on success; spurious superior escalations accumulate | Fix request written to dev-infra (above) |
| 2 | 3 | `auto-checkpoint-loop.sh` has no `verify` subcommand and no `@reboot` or watchdog cron — silent failure if process dies between reboots | Fix request written to dev-infra (above) |
| 3 | 2 | `ceo-health-loop.sh` indentation bug (lines 80-81, 114-118) and missing `verify` subcommand | Route to dev-infra in next cycle |

## ROI estimate
- ROI: 6
- Rationale: The escalation streak correctness bug is the highest priority — it causes cumulative false escalations to senior agents, degrading signal quality across the org over time. The checkpoint-loop watchdog gap is a silent-failure risk for the git backup mechanism. Both fixes are minimal diffs with no behavior change risk.
