# Outbox update: 20260222-idle-refactor-review-hq-3

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-3
- Generated: 2026-02-22T14:28:09-05:00

---

Good. I have both files fully reviewed. Producing two outbox responses.

---

**Outbox for `sessions/agent-code-review/outbox/20260222-idle-refactor-review-hq-2.md`:**

---

- Status: done
- Summary: Targeted refactor review of `scripts/idle-work-generator.sh` (227 lines). This is the script responsible for generating all idle work items across configured agent seats, including the 18+ cycles sent to `agent-code-review`. Found 5 concrete improvements: (1) `agent-code-review` and `agent-task-runner` are aliased to role `ceo` but should have a dedicated `code-review`/`task-runner` role case to avoid getting CEO-flavored idle prompts; (2) the `inbox_has_non_idle_items` check uses a pattern `-idle-` but items named `idle-refactor-review-hq` also contain `-idle-` in a substring — the grep `-v` match is fragile; (3) `top_up_to_three` busy-loops calling `inbox_count` after every create — if `create_item` fails silently, infinite loop possible; (4) `refactor_review_fallback()` function defined but never called — dead code; (5) idle items for `agent-code-review` still use the old `hq-improvement` base ID while the fallback directive now generates `idle-refactor-review-hq`. Diffs below; owner: `dev-infra`.

## File reviewed

`scripts/idle-work-generator.sh` — 627 lines

## Findings

### Finding 1 — MEDIUM: `agent-code-review` aliased to role `ceo` (wrong prompt)
**Lines 329–331:**
```bash
elif [[ "$agent_id" == agent-code-review || "$agent_id" == agent-task-runner ]]; then
    role="ceo"
fi
```
These agents get CEO-flavored idle prompts ("propose 1 improvement, delegation plan, prioritize..."). `agent-code-review` should get a code-review/QA-flavored prompt focused on script/runbook review. This is the root cause of 18+ "CEO idle cycle" prompts being sent to a code review agent.

**Diff:**
```diff
-elif [[ "$agent_id" == agent-code-review || "$agent_id" == agent-task-runner ]]; then
-    role="ceo"
+elif [[ "$agent_id" == agent-code-review ]]; then
+    role="tester"
+elif [[ "$agent_id" == agent-task-runner ]]; then
+    role="software-developer"
fi
```
This routes `agent-code-review` to the existing `tester` case, which generates appropriate QA/code-review idle work, and `agent-task-runner` to the `software-developer` case. No new cases needed.

### Finding 2 — LOW: `inbox_has_non_idle_items` grep pattern is fragile
**Lines 178–182:** Uses `grep -qv -- '-idle-'` to find non-idle items. Items like `20260222-idle-refactor-review-hq` contain `-idle-` and will be treated as idle items even though they are substantive assigned work (refactor directives). The intent is to detect "externally assigned" vs "generator-created" items.

**Better approach:** Mark generator-created items with a sentinel file (e.g., `_generated-by-idle-worker`) inside the item folder, then check for its absence instead of pattern-matching the item ID.

**Minimal diff (in `create_item`):**
```diff
   mkdir -p "$dir"
+  touch "$dir/_generated"
```
And in `inbox_has_non_idle_items`:
```diff
-  find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' 2>/dev/null \
-    | grep -qv -- '-idle-' && return 0
+  find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null \
+    | while read -r d; do [ ! -f "$d/_generated" ] && echo found && break; done \
+    | grep -q found && return 0
```

### Finding 3 — LOW: `top_up_to_three` potential infinite loop
**Lines 184–195:** After `create_item`, calls `inbox_count` again. If `create_item` silently returns 0 without creating (e.g., both inbox dir and outbox file exist via race), `inbox_count` stays the same and the loop never terminates. The `unique_item_id` cap of 20 per day provides a backstop, but there's a window.

**Diff:** Add a guard:
```diff
   while [ "$c" -lt "$IDLE_QUEUE_LIMIT" ]; do
     local iid
     iid="$(unique_item_id "$agent" "$base_id")"
-    [ -n "$iid" ] || return 0
+    [ -n "$iid" ] || { echo "WARN: idle item cap reached for ${agent}" >&2; return 0; }
     create_item "$agent" "$iid" "$body" || true
-    c="$(inbox_count "$agent")"
+    local new_c
+    new_c="$(inbox_count "$agent")"
+    [ "$new_c" -gt "$c" ] || return 0   # No progress — bail to avoid infinite loop.
+    c="$new_c"
   done
```

### Finding 4 — LOW: Dead code — `refactor_review_fallback()` never called
**Lines 197–207:** Function defined, never referenced. Can be removed or used. The `body` for the `ceo` role case (lines 401–418) should include this fallback text.

**Diff:**
```diff
# In the ceo case body (after line 416):
+$(refactor_review_fallback)
```

### Finding 5 — INFO: Old `hq-improvement` base ID still in history
The old idle directive used `${DATE_YYYYMMDD}-idle-hq-improvement` as base_id. The current `ceo` case uses `idle-refactor-review-hq`. No code change needed — history is fine — but `unique_item_id` checks both inbox and outbox, so old outbox files won't be double-counted.

## Delegation plan

All changes: owner `dev-infra`. Finding 1 (role alias fix) is highest priority — it directly stops 18+ cycles of wrong-role idle prompts being sent to `agent-code-review`.

## Next actions
- CEO: queue `dev-infra` inbox item for Finding 1 (role alias fix). ROI: 30.
- CEO: queue `dev-infra` inbox item for Finding 3 (infinite loop guard). ROI: 10.

## Blockers
- None.

## ROI estimate
- ROI: 30
- Rationale: Finding 1 directly fixes the root cause of 18 consecutive misdirected idle cycles for `agent-code-review` by correcting the role alias. One-line change with immediate operational impact.

---

**Outbox for `sessions/agent-code-review/outbox/20260222-idle-refactor-review-hq-3.md`:**

---

- Status: done
- Summary: Targeted refactor review of `scripts/sla-report.sh` (104 lines). This script reports SLA breaches for outbox lag and missing escalations across all configured agents. Found 4 concrete improvements: (1) `needs_escalation_exists` uses a glob pattern `*-needs-${agent}-*` that misses the superior-escalation path (`needs-escalated-${agent}-*`), causing false "missing-escalation" breach reports; (2) `outbox_status` uses `grep -iE` + `tail -n 1` which picks the last Status line — if an outbox has multiple Status lines (e.g., sub-items), result is wrong; (3) `oldest_inbox_item` uses `-printf '%T@ %f\n'` which sorts by mtime, not creation time — items moved from another dir will have wrong timestamps; (4) no `roi.txt` awareness — an agent with a ROI-1 idle item at the front of the queue will always show as a "breach" even if it was just generated. Diffs below; owner: `dev-infra`.

## File reviewed

`scripts/sla-report.sh` — 104 lines

## Findings

### Finding 1 — MEDIUM: `needs_escalation_exists` misses superior-escalation items
**Lines 60–67:** Glob is `*-needs-${agent}-*`. Superior escalations (3x streak) are named `*-needs-escalated-${agent}-*` (see `agent-exec-next.sh` line 634). These are never matched, causing false BREACH reports for agents that have been escalated via the 3x path.

**Diff:**
```diff
-  for d in sessions/"${supervisor}"/inbox/*-needs-"${agent}"-*; do
+  for d in sessions/"${supervisor}"/inbox/*-needs-*"${agent}"*; do
```

### Finding 2 — LOW: `outbox_status` picks last Status line, not first
**Line 53:** `grep ... | tail -n 1`. The org-wide standard requires Status on line 1. Using `head -n 1` is safer and matches the convention.

**Diff:**
```diff
-  grep -iE '^\- Status:' "$f" 2>/dev/null | tail -n 1 | sed ...
+  grep -iE '^\- Status:' "$f" 2>/dev/null | head -n 1 | sed ...
```

### Finding 3 — LOW: `oldest_inbox_item` uses mtime, not ctime
**Line 32:** `-printf '%T@'` is mtime. Inbox items that have been `mv`-ed (e.g., by `consolidate-legacy-sessions.sh`) will sort by move time, not original creation time. Use `%C@` (ctime) or sort by item ID name (which includes the date prefix) as a more reliable proxy.

**Alternative (simpler, no filesystem dependency):**
```diff
-  find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %f\n' 2>/dev/null | sort -n | head -n 1 | awk '{print $2}'
+  find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' 2>/dev/null | sort | head -n 1
```
Item IDs are `YYYYMMDD-...` so lexicographic sort gives chronological order reliably.

### Finding 4 — INFO: No `roi.txt` awareness for idle items
SLA_OUTBOX breach is reported for any inbox item without an outbox. Idle items (just created by the generator, ROI=1) will always breach the 15-minute outbox SLA if the executor is slow. Consider skipping `_generated` items (per sentinel from idle-work-generator Finding 2) from the SLA breach check, or using a longer threshold for ROI=1 items.

No immediate diff — depends on Finding 2 from `idle-work-generator.sh` review being applied first.

## Delegation plan

Owner: `dev-infra` for all findings. Finding 1 is most impactful (eliminates false breach noise for 3x-escalated agents).

## Next actions
- CEO: queue `dev-infra` inbox item for Findings 1 and 2 (small, safe fixes). ROI: 12.

## Blockers
- None.

## ROI estimate
- ROI: 12
- Rationale: Finding 1 fixes false BREACH reports for agents with 3x superior escalations; Finding 2 ensures status is read from the correct line. Both are 1-line changes improving triage signal quality.
