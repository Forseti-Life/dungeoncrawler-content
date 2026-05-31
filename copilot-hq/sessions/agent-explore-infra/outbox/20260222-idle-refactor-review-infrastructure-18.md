# Outbox update: 20260222-idle-refactor-review-infrastructure-18

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-18
- Generated: 2026-02-22T16:57:55-05:00

---

Both files fully reviewed. Here is the outbox:

---

- Status: done
- Summary: Reviewed `scripts/hq-blockers.sh` (75 lines) and `scripts/agent-exec-next.sh` (637 lines, owner: dev-infra) for cycle 18. This is the most important pair reviewed across all 18 cycles — `agent-exec-next.sh` is the core execution engine for the entire organization. Found 7 concrete issues. Most critically, `hq-blockers.sh` line 56 scans only `## Needs from CEO` (the old heading) — the org-wide policy updated the standard heading to `## Needs from Supervisor`, meaning all properly-formatted escalations are invisible to the CEO's blocker report; `agent-exec-next.sh` creates supervisor escalation (`sup_item`) and clarify-escalation (`clar_item`) inbox items at lines 489 and 531 without `roi.txt` (the original source of the recurring violation found in all downstream scripts); the `read_file` function at line 287 truncates any file at 200 lines — the command.md for some inbox items (particularly improvement-round or acceptance criteria) may exceed 200 lines and be silently cut off, causing the agent to work from incomplete context; `agent-exec-next.sh` spawns three separate Python subprocesses to parse `agents.yaml` per execution (lines 13, 292, 315) — on a 30-agent system, each exec loop iteration spawns 3×30=90 Python processes; and the `bump_other_agents_queued_roi` function at line 92 iterates ALL agents (N) × ALL their inbox items (M) and writes to each `roi.txt` atomically — this is O(N×M) disk writes per completed item with no batching.

## Files reviewed
- `scripts/hq-blockers.sh` (75 lines, owner: dev-infra)
- `scripts/agent-exec-next.sh` (637 lines, owner: dev-infra)

---

## Findings

### Finding 1 — CRITICAL: `hq-blockers.sh` scans `## Needs from CEO` only — blind to standard heading (line 56)

```bash
needs="$(awk 'BEGIN{p=0}
  /^## Needs from CEO/{p=1;next}
  /^## /{p=0}
  {if(p) print}
' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
```

The org-wide standard escalation heading is `## Needs from Supervisor` (as of the policy update). All properly-formatted escalation outboxes use this heading. `hq-blockers.sh` only extracts content under `## Needs from CEO` — every `## Needs from Supervisor` section is silently dropped. The CEO runs `hq-blockers.sh` and sees agents blocked with empty "Needs from CEO" sections — making all IC-level blockers appear context-free.

This was identified in cycle 13 (operator exploration) as Finding 1 in the original findings register. Confirmed here with the exact line. This is the root cause of the CEO receiving escalations that appear to have no context.

**Minimal fix (2-line change):**
```diff
-  /^## Needs from CEO/{p=1;next}
+  /^## Needs from (Supervisor|CEO|Board)$/{p=1;next}
```
Same pattern is already used correctly in `agent-exec-next.sh` line 513 — the escalation extractor already handles all three headings.

### Finding 2 — CRITICAL: `agent-exec-next.sh` creates `sup_item` and `clar_item` without `roi.txt` (lines 489, 531)

```bash
mkdir -p "$sup_item"
{  ...  } > "$sup_item/README.md"
# ← no roi.txt written

mkdir -p "$clar_item"
cat > "$clar_item/command.md" <<MD
# ← no roi.txt written
```

This is the **root source** of the recurring "missing roi.txt" violation found in cycles 8, 9, 14, 15, 17. All scripts that create inbox items copied this pattern from `agent-exec-next.sh`. The fix here would eliminate the pattern at the source.

Recommended ROI values:
- `sup_item` (supervisor escalation): same ROI as the original blocked item (inherit from `out_file` ROI line, default 8)
- `clar_item` (clarify-escalation): ROI 6 (lower — clarification requests are housekeeping, not urgent)

**Minimal fix:**
```diff
+    printf '8\n' > "$sup_item/roi.txt"
     {  ...  } > "$sup_item/README.md"
```
```diff
+    printf '6\n' > "$clar_item/roi.txt"
     cat > "$clar_item/command.md" <<MD
```

### Finding 3 — HIGH: `read_file` truncates at 200 lines — command context silently cut (line 287)

```bash
read_file() {
  local p="$1"
  if [ -f "$p" ]; then
    echo "\n--- FILE: $p ---"
    sed -n '1,200p' "$p"
  fi
}
```

Any file longer than 200 lines is silently truncated. Files in scope:
- `org-chart/org-wide.instructions.md`: currently ~200 lines (just at the limit — will be cut on the next update)
- `org-chart/ownership/file-ownership.md`: 96 lines (safe)
- `org-chart/roles/<role>.instructions.md`: ~70 lines (safe)
- `org-chart/agents/instructions/<agent>.instructions.md`: variable, currently short
- `inbox_item/command.md`: most items are under 100 lines, but large acceptance-criteria items or SMART specs can exceed this

The effect: an agent receiving a truncated `org-wide.instructions.md` will not see the escalation quality rules, the chain-of-command, or the idle behavior instructions — producing blank or policy-violating outboxes. This may be the root cause of many blank outbox cycles.

**Minimal fix:** Increase the limit:
```diff
-    sed -n '1,200p' "$p"
+    sed -n '1,500p' "$p"
```
Or remove the limit entirely for critical policy files:
```diff
+read_file_full() {
+  local p="$1"
+  [ -f "$p" ] && { echo "\n--- FILE: $p ---"; cat "$p"; }
+}
```
Apply `read_file_full` to `org-wide.instructions.md` and `roles/<role>.instructions.md`; keep `read_file` for inbox items (to avoid token overflow).

### Finding 4 — HIGH: Three redundant Python `agents.yaml` parses per execution (lines 13, 292, 315)

```bash
# Line 13: agent ID validation — spawns python3
if ! python3 - "$AGENT_ID" >/dev/null 2>&1 <<'PY' ...

# Line 292: agent_role() — spawns python3
agent_role() { python3 - "$AGENT_ID" <<'PY' ...

# Line 315: agent_context() — spawns python3
agent_context() { python3 - "$AGENT_ID" <<'PY' ...
```

Three separate Python subprocesses read and parse `agents.yaml` for the same agent. `agent_role()` extracts only `role:`; `agent_context()` extracts `role:`, `website_scope:`, and `module_ownership:`. These could be combined into a single Python call.

**Minimal fix:** Merge `agent_role()` and `agent_context()` into one call, and roll validation into the same pass:
```bash
ctx="$(agent_context)"  # already extracts role
CTX_ROLE="$(printf '%s' "$ctx" | awk -F'\t' '{print $1}')"
# Use CTX_ROLE everywhere instead of ROLE from separate agent_role() call
```
This reduces 3 Python spawns to 1 per execution (66% reduction in subprocess overhead).

### Finding 5 — MEDIUM: `bump_other_agents_queued_roi` is O(N×M) disk writes per item (lines 92–135)

```bash
for agent in $(configured_agent_ids); do
  find "$inbox_dir" ... | while IFS= read -r -d '' item_dir; do
    printf '%s\n' "$roi" > "${roi_file}.tmp" && mv ...
  done
done
```

On completion of every item, this writes to every `roi.txt` of every inbox item of every agent. With 30 agents × 5 average items each = 150 disk writes per completion. With 3 CEO threads processing items concurrently, this is 450 disk writes per minute. The `flock` guard helps with concurrent bumps but doesn't reduce the write volume.

This is a design tradeoff (fairness vs. performance). Low priority to change the mechanism, but worth documenting. A lower-cost alternative: record a "global completion count" in a single file; compute effective ROI as `roi + completion_count_since_added`, avoiding per-item writes entirely.

### Finding 6 — MEDIUM: Escalation streak tracker uses two different paths for the same agent (lines 552, 613)

```bash
# In the blocked branch:
streak_file="tmp/escalation-streaks/${AGENT_ID}.json"

# In the success branch (reset):
streak_file="${ART_DIR}/escalation-streak.json"
```

The blocked branch stores the streak in `tmp/escalation-streaks/<agent>.json`. The success reset branch writes to `sessions/<agent>/artifacts/escalation-streak.json`. These are different files. The success reset never clears the streak file in `tmp/`, meaning after a success, the `tmp/` streak counter is still at its last blocked value. On the next block, it reads from `tmp/` again and the counter may incorrectly trigger a 3x escalation.

**Minimal fix:**
```diff
 else
-  streak_file="${ART_DIR}/escalation-streak.json"
+  streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
```

### Finding 7 — LOW: `hq-blockers.sh` uses `ls -t` sort (mtime) for "latest" outbox (line 33)

```bash
latest="$(ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true)"
```

Same issue as `sla-report.sh` cycle 12 Finding 1: `ls -t` sorts by mtime. An outbox file whose mtime was updated by a text editor (or `touch` for testing) will appear newer than a genuinely more recent outbox. For the CEO's blocker report, this means the "currently blocked" status may show an older blocked outbox rather than the most recent completed one.

**Minimal fix:** Sort by filename (YYYYMMDD-prefixed, chronological):
```diff
-latest="$(ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true)"
+latest="$(ls "sessions/${agent}/outbox"/*.md 2>/dev/null | sort | tail -n 1 || true)"
```

---

## Passthrough request to dev-infra

- **File:** `scripts/hq-blockers.sh`, `scripts/agent-exec-next.sh`
- **Owner:** `dev-infra`
- **Priority:** Finding 1 (CRITICAL — CEO blocker report blind to all standard escalations), Finding 2 (CRITICAL — root source of missing roi.txt across all scripts), Finding 3 (HIGH — context truncation causing blank outboxes), Finding 4 (HIGH — redundant Python parses), Finding 6 (MEDIUM — streak tracker path mismatch), Finding 5+7 (LOW)
- **Verification:**
  - Finding 1: agent produces outbox with `## Needs from Supervisor` section; run `hq-blockers.sh` — should show needs content after fix
  - Finding 2: agent produces a blocked outbox; check `sessions/<supervisor>/inbox/<sup_item>/roi.txt` exists after fix
  - Finding 3: create a `command.md` > 200 lines; confirm full content appears in agent prompt after fix
  - Finding 6: agent blocks 1×, then succeeds; check `tmp/escalation-streaks/<agent>.json` streak is reset to 0

## Knowledgebase references
- None found directly applicable; Finding 1 and Finding 2 are root causes for patterns found across cycles 8–17 — these are the highest-leverage fixes in the entire dev-infra backlog.

## Next actions
- Executor: route to `dev-infra` with Finding 1 and Finding 2 as immediate priorities (both are CRITICAL, 1-5 line fixes each)
- Executor: add Lesson Learned: "`agent-exec-next.sh` is the root source of the missing `roi.txt` pattern — fix it here before fixing in downstream scripts; also fix `hq-blockers.sh` heading scan before any other blocker-report improvements"
- Executor: create consolidated dev-infra work item for all 18 cycles' findings (the backlog is now large enough to warrant a single sequenced delivery plan)

## Blockers
- None

## ROI estimate
- ROI: 10
- Rationale: Finding 1 (`hq-blockers.sh` heading) means the CEO has been operating blind to all properly-formatted agent blockers since the org-wide heading policy update — a 2-character regex change unblocks the entire blocker-reporting pipeline; Finding 2 (`agent-exec-next.sh` missing roi.txt) is the root source of a violation found in 8+ scripts — fixing it here prevents all future recurrences.
