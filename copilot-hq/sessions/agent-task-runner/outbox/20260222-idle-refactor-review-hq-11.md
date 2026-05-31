# Outbox update: 20260222-idle-refactor-review-hq-11

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-11
- Generated: 2026-02-22T17:17:41-05:00

---

I now have enough content. Here is the outbox for `20260222-idle-refactor-review-hq-11`:

---

- Status: done
- Summary: Reviewed `scripts/hq-blockers.sh` (75 lines, full read via view tool). Found 4 concrete issues: (1) `configured_agent_ids` function is duplicated verbatim across at least `agent-exec-next.sh`, `idle-work-generator.sh`, and `hq-blockers.sh` — a shared lib function would eliminate drift; (2) "currently blocked" uses only the latest outbox file by mtime, so an old done outbox followed by a stale blocked outbox in alphabetical order is silently misreported; (3) `## Needs from CEO` is hardcoded but the org-wide escalation headings now support three variants (Supervisor/CEO/Board), so Board/Supervisor escalations are invisible to this script; (4) no streak count shown, making it impossible to distinguish a new block from a 15-streak runaway without manual file inspection. Diffs produced; queued to dev-infra.

## File reviewed
`scripts/hq-blockers.sh` — reports currently-blocked agents by scanning latest outbox files. Used by operators to identify stalled agents at a glance.

## Findings

### Finding 1 (HIGH) — `configured_agent_ids` duplicated across ≥3 scripts
`hq-blockers.sh` lines 7–19 are an exact copy of the same function in `agent-exec-next.sh` and `idle-work-generator.sh`. When `agents.yaml` parsing logic changes (e.g., new field or format), all copies must be updated in sync or they drift silently.

**Owner:** `dev-infra`

**Fix:** Extract to `scripts/lib/agent-ids.sh`:
```bash
# scripts/lib/agent-ids.sh
configured_agent_ids() {
  python3 - <<'PY'
import re
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
    if m:
        print(m.group(1))
PY
}
```
Then replace inline copies with `. "$ROOT_DIR/scripts/lib/agent-ids.sh"`.

**Verification:** Run `hq-blockers.sh` and `idle-work-generator.sh` after extraction; confirm same output as before.

### Finding 2 (HIGH) — Escalation "Needs from" heading scan is hardcoded to `## Needs from CEO`
Lines 55–59: only scans for `## Needs from CEO`. The org-wide template now requires one of: `## Needs from Supervisor`, `## Needs from CEO`, or `## Needs from Board`. Supervisor and Board escalations are silently dropped from the blocker report, making the dashboard incomplete.

**Owner:** `dev-infra`

**Fix (lines 55–58):**
```bash
-  needs="$(awk 'BEGIN{p=0}
-    /^## Needs from CEO/{p=1;next}
+  needs="$(awk 'BEGIN{p=0}
+    /^## Needs from (Supervisor|CEO|Board)$/{p=1;next}
```

**Verification:** Create a test outbox with `## Needs from Supervisor` content; confirm it appears in `hq-blockers.sh` output.

### Finding 3 (MEDIUM) — No escalation streak shown
The report shows `[status=blocked]` but not the streak count. Operators cannot distinguish a fresh block (streak=1) from a runaway loop (streak=15). Streak file is at `tmp/escalation-streaks/${agent}.json`.

**Owner:** `dev-infra`

**Fix (after line 49, add streak read):**
```bash
  streak=0
  streak_file="tmp/escalation-streaks/${agent}.json"
  if [ -f "$streak_file" ]; then
    streak="$(python3 -c "import json,sys; print(json.load(open(sys.argv[1])).get('streak',0))" "$streak_file" 2>/dev/null || echo 0)"
  fi
  echo "- ${agent}: $(basename "$latest") [status=${status}] [streak=${streak}]"
```

### Finding 4 (LOW) — Latest-outbox selection uses `ls -t` (mtime), not name sort
Line 33: `ls -t` picks by modification time, which is unreliable if files are copied or touch'd. Name sort (alphabetical/lexicographic) is more deterministic for dated filenames.

**Fix:** Replace `ls -t ... | head -n 1` with `ls ... | sort | tail -n 1`.

**Owner:** `dev-infra`

## Next actions
- [ ] dev-infra: fix `## Needs from` heading scan to cover all 3 variants (Finding 2, ROI=20)
- [ ] dev-infra: add streak count to blocker report (Finding 3, ROI=15)
- [ ] dev-infra: extract `configured_agent_ids` to shared lib (Finding 1, ROI=10)
- [ ] dev-infra: fix latest-outbox sort (Finding 4, ROI=5)

## Blockers
- None.

## ROI estimate
- ROI: 20
- Rationale: Finding 2 causes Supervisor/Board escalations to be invisible to operators using `hq-blockers.sh`, meaning real blocks go unnoticed. Finding 3 makes streak-runaway agents indistinguishable from new blocks. Together these two fixes make the dashboard actionable for operators managing the current escalation flood.
