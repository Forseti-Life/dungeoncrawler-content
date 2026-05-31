# Outbox update: 20260222-idle-refactor-review-hq-18

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-18
- Generated: 2026-02-22T17:54:25-05:00

---

- Status: done
- Summary: Reviewed `scripts/sla-report.sh` (104 lines, full read via view tool). This script reports SLA breaches: outbox lag (inbox item older than 15m with no outbox) and missing escalation (blocked/needs-info outbox with no supervisor inbox item). Found 5 concrete issues: (1) `configured_agent_ids` is duplicated yet again — the 5th confirmed copy across the codebase; (2) `latest_outbox_file` uses `ls -t` (mtime sort) which is unreliable for dated filenames and duplicates the same bug found in `hq-blockers.sh`; (3) `needs_escalation_exists` uses a glob `*-needs-${agent}-*` that can fail or produce unexpected results if `agent` contains special characters — no quoting guard; (4) SLA outbox-lag check evaluates only the *oldest* inbox item per agent, so a flooded agent with 15 idle items shows only one breach entry, masking the true queue depth; (5) the `breach` variable is set to `1` on any breach but the script has no non-zero exit code path — `notify-pending.sh` calls `sla-report.sh | grep -c '^BREACH'` which works, but a caller using the exit code gets 0 even on breaches.

## File reviewed
`scripts/sla-report.sh` — SLA breach reporter; checks outbox lag and missing escalation items for all configured agents; output is consumed by `notify-pending.sh` and operators.

## Findings

### Finding 1 (HIGH) — `configured_agent_ids` duplicated (5th copy confirmed)
Lines 7–19: identical Python inline to `agent-exec-next.sh`, `agent-exec-loop.sh`, `hq-blockers.sh`, `hq-status.sh`. Any change to `agents.yaml` parsing must be applied to 5 locations or they drift.

**Owner:** `dev-infra`

**Fix:** Extract to `scripts/lib/agent-ids.sh` and source it. This is the highest-leverage deduplication across the entire `scripts/` directory.

**Verification:** After extraction, run `sla-report.sh`, `hq-blockers.sh`, `hq-status.sh`, and `agent-exec-loop.sh` and confirm identical agent list output.

### Finding 2 (HIGH) — `latest_outbox_file` uses `ls -t` mtime sort (line 43)
```bash
ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1
```
Same reliability bug as `hq-blockers.sh` Finding 4 (hq-11 review). Copied outbox files get wrong "latest" based on filesystem mtime, not filename date. The SLA missing-escalation check could evaluate the wrong outbox.

**Owner:** `dev-infra`

**Fix:**
```bash
-  ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true
+  ls "sessions/${agent}/outbox"/*.md 2>/dev/null | sort | tail -n 1 || true
```

### Finding 3 (MEDIUM) — `needs_escalation_exists` glob unquoted special chars (lines 60–67)
```bash
for d in sessions/"${supervisor}"/inbox/*-needs-"${agent}"-*; do
```
If `$agent` or `$supervisor` contains shell special characters (`.`, `+`, spaces), the glob expands unexpectedly. While current agent IDs are safe, adding validation or quoting defense is good hygiene.

**Owner:** `dev-infra`

**Fix:** Add a guard at the top of the function:
```bash
[[ "$agent" =~ ^[A-Za-z0-9_-]+$ ]] || { return 1; }
[[ "$supervisor" =~ ^[A-Za-z0-9_-]+$ ]] || { return 1; }
```

### Finding 4 (MEDIUM) — Outbox-lag check only evaluates oldest item; full queue depth hidden
Lines 76–84: reports breach only for the single oldest inbox item. An agent with 15 idle items queued shows one `BREACH outbox-lag` line. Operators cannot tell whether it's 1 stale item or 15.

**Owner:** `dev-infra`

**Fix:** Loop over all inbox items (or report count):
```bash
  local stale_count=0
  while IFS= read -r item; do
    [ -n "$item" ] || continue
    if ! outbox_for_item_exists "$agent" "$item"; then
      t=$(inbox_item_epoch "$agent" "$item")
      age=$((now - t))
      if [ "$age" -gt "$SLA_OUTBOX" ]; then
        stale_count=$((stale_count + 1))
      fi
    fi
  done < <(find "sessions/${agent}/inbox" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' 2>/dev/null)
  if [ "$stale_count" -gt 0 ]; then
    echo "BREACH outbox-lag: ${agent} stale_items=${stale_count}"
    breach=1
  fi
```

### Finding 5 (LOW) — Script exits 0 regardless of breaches
Lines 101–103: `breach` variable is set but never used as exit code. Callers checking `$?` get 0 even with 20 BREACH lines. `notify-pending.sh` works around this via `grep -c`, but it's an unexpected interface.

**Owner:** `dev-infra`

**Fix (line 103):**
```bash
-if [ "$breach" -eq 0 ]; then
-  echo "OK: no SLA breaches"
-fi
+if [ "$breach" -eq 0 ]; then
+  echo "OK: no SLA breaches"
+else
+  exit 1
+fi
```

## Next actions
- [ ] dev-infra: extract `configured_agent_ids` to `scripts/lib/agent-ids.sh` — eliminates all 5 duplicate copies (Finding 1, ROI=15)
- [ ] dev-infra: fix `latest_outbox_file` to use name sort instead of `ls -t` (Finding 2, ROI=10)
- [ ] dev-infra: report full stale-item count per agent in outbox-lag breach (Finding 4, ROI=8)
- [ ] dev-infra: add non-zero exit code on breach (Finding 5, ROI=5)

## Blockers
- None.

## ROI estimate
- ROI: 15
- Rationale: Finding 1 (5th duplicate of `configured_agent_ids`) is the highest-leverage single fix available — one shared lib eliminates a maintenance liability across 5 scripts simultaneously. Finding 2 is the same mtime-sort reliability bug confirmed in two scripts now; fixing in the shared pattern prevents a third occurrence. Both are purely additive with no behavior change for correct inputs.
