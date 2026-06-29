# Outbox update: 20260222-idle-refactor-review-infrastructure-8

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-8
- Generated: 2026-02-22T15:32:08-05:00

---

Now I have the complete picture of `agent-exec-next.sh`. This is a rich target — found 5 concrete issues.

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-next.sh` (637 lines, owner: dev-infra) as the target for refactor cycle 8. Found 5 concrete issues of varying severity, with one critical bug: the escalation streak file path is inconsistent between the "blocked" path (reads/writes `tmp/escalation-streaks/<agent>.json`, line 552) and the "success" path (resets `sessions/<agent>/artifacts/escalation-streak.json`, line 613) — these are different files, so the streak counter in `tmp/` is NEVER reset by successful completions, meaning once an agent hits 3 consecutive blocked responses, every future blocked response will trigger a superior escalation regardless of how many successes occurred in between. Additional findings: sup_item and clar_item folders are created without `roi.txt` (org-wide policy violation); the PROMPT template at line 357 still says `## Needs from CEO` while the escalation heading rule appended at line 359 says use `## Needs from Supervisor` — creating contradictory instructions; and `read_file()` silently truncates files at 200 lines with no warning to the agent.

## File reviewed
`scripts/agent-exec-next.sh` (owner: dev-infra, 637 lines)

## Findings

### Finding 1 — CRITICAL: Streak file path mismatch (lines 552 vs 613)

**Blocked path (line 552):**
```bash
streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
```

**Success path (line 613):**
```bash
streak_file="${ART_DIR}/escalation-streak.json"
# expands to: sessions/<agent>/artifacts/escalation-streak.json
```

These are **two completely different files**. The streak counter is incremented in `tmp/escalation-streaks/<agent>.json` on every blocked response and only reset when a superior escalation fires (line 602-609). The success path writes `streak: 0` to the artifacts file, which is never read by the streak logic. Net effect: once any agent accumulates 3 blocked responses, the `tmp/` streak file stays ≥3 forever (never decremented by successes), and every subsequent blocked response will trigger a superior escalation indefinitely.

Confirmed: my own `sessions/agent-explore-infra/artifacts/escalation-streak.json` shows `streak: 0` — that's the artifacts file being reset correctly. But `tmp/escalation-streaks/agent-explore-infra.json` (the one that actually controls the decision) may have a different value.

**Minimal fix:**
```diff
--- a/scripts/agent-exec-next.sh
+++ b/scripts/agent-exec-next.sh
@@ -612,7 +612,7 @@ else
   # Reset escalation streak on progress so "3x in a row" means consecutive blocked/needs-info.
-  streak_file="${ART_DIR}/escalation-streak.json"
+  streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
   python3 - "$streak_file" <<'PY'
```

### Finding 2 — HIGH: Missing `roi.txt` on `sup_item` and `clar_item` creation (lines 487-548)

**`sup_item` (lines 487-524):** creates folder + `README.md` but no `roi.txt`.
**`clar_item` (lines 529-548):** creates folder + `command.md` but no `roi.txt`.

Both violate org-wide policy: "Any inbox item folder you create MUST include a `roi.txt` file." The `roi_for_item_dir()` fallback yields ROI=1 at runtime, but the missing file means `bump_other_agents_queued_roi` never increments these items and dashboard tooling may not surface them correctly.

**Minimal fix (after each `mkdir -p` block):**
```diff
# After line 489 (sup_item mkdir):
+   printf '5\n' > "$sup_item/roi.txt"   # escalations are medium-high priority

# After line 531 (clar_item mkdir):
+   printf '6\n' > "$clar_item/roi.txt"  # clarify needed before supervisor can act
```
ROI values are estimates; the creating agent should use context-aware values but a non-zero default is far better than missing.

### Finding 3 — HIGH: Conflicting prompt instructions for escalation heading (lines 357, 359)

Line 357 embeds `## Needs from CEO` in the task template:
```
## Needs from CEO
- If blocked/needs-info, list exactly what you need...
```

Line 359 then appends the corrective escalation heading rule:
```
Escalation heading rule: when blocked/needs-info, put your ask under ONE of these headings (pick the closest):
- ## Needs from Supervisor  ← should be default
- ## Needs from CEO
- ## Needs from Board
```

The agent sees a task description requiring `## Needs from CEO` but then an override saying use `## Needs from Supervisor` by default. This creates the exact ambiguity that caused 12+ cycles of blank or wrong-heading outboxes. Some agents follow the template (→ `## Needs from CEO`), some follow the rule (→ `## Needs from Supervisor`), and `hq-blockers.sh` only scanned one of the two headings.

**Minimal fix:** Replace the hardcoded `## Needs from CEO` in the PROMPT template (line 357) with the generic heading:
```diff
-## Needs from CEO\n- If blocked/needs-info, list exactly what you need...
+## Needs from Supervisor\n- If blocked/needs-info, list exactly what you need (normal up-chain); use ## Needs from CEO only if your supervisor IS the CEO...
```

### Finding 4 — MEDIUM: `read_file()` silently truncates at 200 lines (line 287)

```bash
read_file() {
  local p="$1"
  if [ -f "$p" ]; then
    echo "\n--- FILE: $p ---"
    sed -n '1,200p' "$p"   # ← silent truncation
  fi
}
```

Files longer than 200 lines are truncated with no marker to the agent. The agent may reason from incomplete context. Files currently at risk: `org-chart/org-wide.instructions.md` (~200 lines), `org-chart/roles/*.instructions.md` (some are long), and large `command.md` files.

**Minimal fix:** Add a truncation warning:
```diff
-    sed -n '1,200p' "$p"
+    if [ "$(wc -l < "$p")" -gt 200 ]; then
+      sed -n '1,200p' "$p"
+      echo "... [TRUNCATED: file exceeds 200 lines]"
+    else
+      cat "$p"
+    fi
```

### Finding 5 — LOW: `|| true` swallows Copilot CLI errors (line 403)

```bash
response="$("$COPILOT_BIN" --resume "$SESSION_ID" --silent -p "$PROMPT" || true)"
```

If Copilot CLI exits non-zero (rate limit, auth error, network failure), the error is suppressed and `response` is empty. The retry at line 406 also uses `|| true`. The fallback then writes a `needs-info` outbox citing "Missing required status header" — misattributing a tool failure as an agent formatting error.

**Minimal fix:** Capture stderr and include it in the fallback message:
```diff
-response="$("$COPILOT_BIN" --resume "$SESSION_ID" --silent -p "$PROMPT" || true)"
+response="$("$COPILOT_BIN" --resume "$SESSION_ID" --silent -p "$PROMPT" 2>/tmp/copilot-err-$$.txt || true)"
+cli_err="$(cat /tmp/copilot-err-$$.txt 2>/dev/null); rm -f /tmp/copilot-err-$$.txt
```
(Or log to a proper error file per agent/item.)

## Passthrough request to dev-infra

- **File:** `scripts/agent-exec-next.sh`
- **Owner:** `dev-infra`
- **Priority order:** Finding 1 (CRITICAL, 1-line fix), Finding 2 (HIGH, 2 insertions), Finding 3 (HIGH, template edit), Finding 4 (MEDIUM), Finding 5 (LOW)
- **Verification after Finding 1 fix:** Run an agent through 3 blocked items, then 1 success, then check `tmp/escalation-streaks/<agent>.json` shows `streak: 0`; run another blocked item and confirm no superior escalation fires.
- **ROI:** 9 for Finding 1 (streak never resets = spurious superior escalations forever); 8 for Findings 2-3.

## Verification steps
1. Check `tmp/escalation-streaks/` for existing JSON files — any with `streak >= 3` confirms the bug is active
2. After Finding 1 fix: `grep -r '"streak"' tmp/escalation-streaks/` — should reset to 0 after next successful completion
3. After Finding 2 fix: `ls sessions/*/inbox/*/roi.txt` — should include newly created sup_item and clar_item folders
4. After Finding 3 fix: spot-check next 3 agent outboxes — should consistently use `## Needs from Supervisor`

## Next actions
- Executor: route passthrough request to `dev-infra` with all 5 findings, prioritized by ROI (Finding 1 first)
- Executor: check `tmp/escalation-streaks/` to determine if spurious superior escalations are currently firing

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: Finding 1 is a silent bug that makes the 3x escalation rule fire perpetually for any agent that has ever been blocked 3 times — this creates noise in supervisor inboxes indefinitely and the fix is a single line change. Findings 2-3 are recurring policy violations that affect every blocked outbox generated by the system.
