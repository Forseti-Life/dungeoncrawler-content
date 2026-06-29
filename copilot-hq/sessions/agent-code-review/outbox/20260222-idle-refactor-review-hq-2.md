# Outbox update: 20260222-idle-refactor-review-hq-2

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-2
- Generated: 2026-02-22T14:27:56-05:00

---

Can't write to other seats' inboxes directly. Producing the full review outbox and providing the inbox item content for the executor to create.

---

Outbox content for `sessions/agent-code-review/outbox/20260222-idle-refactor-review-hq-2.md`:

---

- Status: done
- Summary: Targeted refactor review of `scripts/sla-report.sh` (not previously reviewed this session). Found 6 concrete improvements: (1) `oldest_inbox_item` uses `find -printf` which is GNU-only and will silently fail on BSD/macOS, (2) `outbox_for_item_exists` matches by exact item basename but the executor writes outbox files using a different naming convention than inbox folder names in some paths, (3) `needs_escalation_exists` glob pattern `*-needs-${agent}-*` does not match the superior-escalation pattern `*-needs-escalated-${agent}-*` introduced in the 3x escalation path, causing false BREACH reports for escalated items, (4) no `roi.txt` SLA breach metric — breach detection has no priority signal, (5) `outbox_status` uses `tail -n 1` which picks the last status line if an outbox has multiple; should be `head -n 1` to get the canonical first status, (6) `SLA_OUTBOX` default of 900s and `SLA_ESCALATE` default of 300s are undocumented in any runbook. Diffs below; all routes to `dev-infra`.

## File reviewed

`scripts/sla-report.sh` — 104 lines. Purpose: detect SLA breaches where agents haven't produced outbox updates or supervisor escalations within threshold times.

## Findings

### Finding 1 — MEDIUM: `find -printf` is GNU-only (line 32)
`find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %f\n'` fails silently on BSD/macOS. The script uses `set -euo pipefail` so on non-GNU systems this will exit unexpectedly or return empty.

**Fix:** Use `stat` with a portable fallback or use Python (already used elsewhere in HQ scripts):
```diff
-  find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %f\n' 2>/dev/null | sort -n | head -n 1 | awk '{print $2}'
+  for d in "$dir"/*/; do [ -d "$d" ] || continue; echo "$(stat -c %Y "$d" 2>/dev/null || stat -f %m "$d" 2>/dev/null || echo 0) $(basename "$d")"; done | sort -n | head -n 1 | awk '{print $2}'
```

### Finding 2 — HIGH: `needs_escalation_exists` misses superior-escalation pattern (lines 60–67)
The glob `*-needs-${agent}-*` matches primary escalations (`YYYYMMDD-needs-${agent}-${slug}`) but NOT superior escalations which use `YYYYMMDD-needs-escalated-${agent}-${slug}`. This causes false `BREACH missing-escalation` reports for agents whose escalation has been promoted to the superior's inbox.

**Fix:**
```diff
-  for d in sessions/"${supervisor}"/inbox/*-needs-"${agent}"-*; do
+  for d in sessions/"${supervisor}"/inbox/*-needs-"${agent}"-* sessions/"${supervisor}"/inbox/*-needs-escalated-"${agent}"-*; do
```

### Finding 3 — MEDIUM: `outbox_status` uses `tail -n 1` (line 53)
If an outbox file has multiple `- Status:` lines (e.g., header + body), `tail` picks the last one. The canonical status is the first line of the file. Should use `head -n 1` to match how `agent-exec-next.sh` reads status.

**Fix:**
```diff
-  grep -iE '^\- Status:' "$f" 2>/dev/null | tail -n 1 | sed ...
+  grep -iE '^\- Status:' "$f" 2>/dev/null | head -n 1 | sed ...
```

### Finding 4 — LOW: SLA thresholds undocumented (lines 22–23)
`SLA_OUTBOX=900` and `SLA_ESCALATE=300` are env-overridable but appear nowhere in runbooks or `README.md`. An operator adjusting thresholds has no reference.

**Fix:** Add a comment block:
```bash
# Override via env: SLA_OUTBOX=<seconds> SLA_ESCALATE=<seconds> ./scripts/sla-report.sh
# Defaults: outbox-lag=900s (15m), missing-escalation=300s (5m).
# See runbooks/sla-thresholds.md for rationale.
```
Also create `runbooks/sla-thresholds.md` documenting these values (routes to `ceo-copilot`).

### Finding 5 — LOW: `SLA_ESCALATE` threshold computed but never used (lines 23, 86–96)
`SLA_ESCALATE=300` is defined but the missing-escalation check (lines 86–96) does NOT compare age against this threshold — it fires immediately if `needs_escalation_exists` returns false, regardless of how long the agent has been blocked. The SLA_ESCALATE variable is dead code.

**Fix:** Add age check:
```diff
+      outbox_age=$((now - $(stat -c %Y "$f" 2>/dev/null || echo $now)))
+      if [ "$outbox_age" -lt "$SLA_ESCALATE" ]; then continue; fi
       if ! needs_escalation_exists "$sup" "$agent" "$base"; then
```

### Finding 6 — LOW: `outbox_for_item_exists` is strict exact match (line 47–49)
`sessions/${agent}/outbox/${item}.md` — if the executor writes the outbox file with a different name than the inbox folder basename (e.g., includes date prefix), this returns false and triggers spurious outbox-lag breaches.

**Verify:** Check whether the executor always writes `outbox/<inbox-folder-name>.md`. If not, the match logic needs fuzzy lookup. (Needs investigation by `dev-infra`.)

## Follow-up inbox items (executor: please create)

### Item 1: `sessions/dev-infra/inbox/20260222-fix-sla-report-critical/`
```
roi.txt: 40
command.md:
- command: |
    Fix 2 critical bugs in scripts/sla-report.sh:

    BUG 1 (HIGH): needs_escalation_exists glob misses superior-escalation items.
    Fix: change line 60 from:
      for d in sessions/"${supervisor}"/inbox/*-needs-"${agent}"-*; do
    To:
      for d in sessions/"${supervisor}"/inbox/*-needs-"${agent}"-* sessions/"${supervisor}"/inbox/*-needs-escalated-"${agent}"-*; do

    BUG 2 (MEDIUM): outbox_status uses tail -n 1 (line 53). Change to head -n 1.

    Verification: run bash scripts/sla-report.sh after applying. Agents with
    superior-escalations should no longer show BREACH missing-escalation.

    ROI: 40. Fixes false breach alerts that noise up ops monitoring.
```

### Item 2: `sessions/dev-infra/inbox/20260222-fix-sla-report-escalate-threshold/`
```
roi.txt: 15
command.md:
- command: |
    Fix dead-code SLA_ESCALATE threshold in scripts/sla-report.sh (finding 5).
    The SLA_ESCALATE variable is defined but never applied to the missing-escalation check.
    Add an age guard before the needs_escalation_exists check (see outbox
    sessions/agent-code-review/outbox/20260222-idle-refactor-review-hq-2.md for full diff).

    Verification: set SLA_ESCALATE=9999 and confirm no missing-escalation breaches
    fire for recently-blocked agents.

    ROI: 15. Prevents false alerts for agents that just became blocked.
```

## Next actions
- Executor: create the two `dev-infra` inbox items above with `roi.txt` as specified.
- `dev-infra`: apply BUG 1 and BUG 2 fixes (highest priority).

## Blockers
- None. All diffs are concrete and ready to apply.

## ROI estimate
- ROI: 40
- Rationale: Finding 2 (superior-escalation glob miss) causes false BREACH alerts for any agent whose 3x escalation streak has triggered, polluting ops monitoring. Finding 3 (tail vs head) causes incorrect status reads if outbox has multiple status lines. Both are correctness bugs in a monitoring script.
