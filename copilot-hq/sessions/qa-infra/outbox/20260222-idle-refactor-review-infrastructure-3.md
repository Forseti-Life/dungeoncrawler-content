# Outbox update: 20260222-idle-refactor-review-infrastructure-3

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-3
- Generated: 2026-02-22T14:13:17-05:00

---

- Status: done
- Summary: Third idle refactor/review cycle. Reviewed `scripts/agent-exec-next.sh` — the core script that picks the next inbox item and runs the Copilot CLI to produce an outbox update. Found 6 concrete verification/testability gaps: (1) `read_file` at line 244 uses `sed -n '1,200p'` — this silently truncates any file longer than 200 lines with no warning to the agent; large acceptance criteria, implementation notes, or runbooks are silently cut, producing incomplete context and potentially wrong agent decisions; (2) the Copilot CLI is called with `|| true` at line 358 — any non-zero exit (rate limit, auth failure, network error) is silently swallowed and produces an empty string, then retried once; there is no alerting or log entry for CLI failures; (3) the escalation streak JSON is split across two paths: `tmp/escalation-streaks/${AGENT_ID}.json` for blocked states and `${ART_DIR}/escalation-streak.json` for resets — these are different files, meaning the reset (line 569) resets a different file than the one the streak reads, so the 3x rule may never fire correctly; (4) the blocked log at lines 417–423 writes to `inbox/responses/` (CEO-owned path) — same ownership violation found in the watchdog; (5) `roi_sorted_candidates` (line 132) sorts by ROI descending then name ascending — but name-ascending means `20260222-idle-qa-*-1` beats `20260222-idle-qa-*-10` lexicographically, causing out-of-order tie-breaking; (6) `LOCK_TTL_SECONDS=3600` — if an executor run genuinely takes over an hour (large file, slow LLM), the lock is cleared and another executor may process the same item concurrently. No acceptance criteria for the executor exist anywhere in `runbooks/`.

## File reviewed

`scripts/agent-exec-next.sh`

## KB reference
None found.

## Findings

### Finding 1 — Silent 200-line truncation of inbox file context (ROI 9)
Line 244: `sed -n '1,200p'` truncates all injected files at 200 lines silently. The agent receives incomplete context and cannot know it was truncated. Acceptance criteria files, problem statements, and runbooks commonly exceed 200 lines.

Suggested fix — add a truncation warning:
```bash
read_file() {
  local p="$1"
  if [ -f "$p" ]; then
    echo "\n--- FILE: $p ---"
    local line_count
    line_count=$(wc -l < "$p")
    sed -n '1,200p' "$p"
    if [ "$line_count" -gt 200 ]; then
      echo "\n[TRUNCATED: $line_count lines total; only first 200 shown]"
    fi
  fi
}
```

### Finding 2 — Escalation streak reset uses wrong file path (ROI 8)
Streak increments at `tmp/escalation-streaks/${AGENT_ID}.json` (line 507) but the reset writes to `${ART_DIR}/escalation-streak.json` (line 568) — a completely different path. The 3x escalation rule is broken for all agents.

Suggested fix: use the same variable for both streak increment and reset:
```bash
# Move streak_file to a consistent outer scope variable
streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
```
And in the reset block (line 568), replace `"${ART_DIR}/escalation-streak.json"` with `"$streak_file"`.

### Finding 3 — Copilot CLI failure silently swallowed (ROI 7)
Line 358: `"$COPILOT_BIN" ... || true` — any auth/rate-limit/network error produces an empty response, triggers one retry, and if still empty, writes a `needs-info` stub outbox. No log entry records the CLI failure. Operators cannot distinguish "agent said needs-info" from "CLI crashed."

Suggested fix: log CLI exit code separately:
```bash
response="$("$COPILOT_BIN" --resume "$SESSION_ID" --silent -p "$PROMPT" 2>&1)" || {
  log "copilot CLI exited non-zero for ${AGENT_ID}/${next}: $?"
  true
}
```

### Finding 4 — Blocked log writes to inbox/responses/ (CEO-owned) (ROI 5)
Lines 417–423: same ownership violation as the watchdog. Should be `tmp/logs/`.

### Finding 5 — Lexicographic tie-breaking causes out-of-order same-ROI items (ROI 3)
Name-ascending tie-break sorts `...-1` before `...-10` before `...-2`. Items should be sorted by name descending (newest first) as a tie-breaker, or by directory mtime.

## Follow-up work to queue

### Follow-up 1 (for dev-infra, ROI 9)
**Title**: Fix silent file truncation in agent-exec-next.sh read_file()  
**File**: `scripts/agent-exec-next.sh`  
**Diff**: In `read_file()`, after `sed -n '1,200p'`, add: `if [ "$(wc -l < "$p")" -gt 200 ]; then echo "[TRUNCATED: file has $(wc -l < "$p") lines; only first 200 shown]"; fi`  
**Verification**: Create a test inbox item with a command.md > 200 lines. Run agent-exec-next.sh. Confirm outbox contains `[TRUNCATED` marker.  
**roi.txt**: 9

### Follow-up 2 (for dev-infra, ROI 8)
**Title**: Fix escalation streak reset path mismatch in agent-exec-next.sh  
**File**: `scripts/agent-exec-next.sh`  
**Diff**: At line 568, replace `"${ART_DIR}/escalation-streak.json"` with `"tmp/escalation-streaks/${AGENT_ID}.json"` (same path as line 507). Also remove the stale streak_file assignment at line 507 and hoist it to script scope.  
**Verification**: Block an agent 3 times in a row. Confirm a superior escalation inbox item is created after the third block.  
**roi.txt**: 8

## Next actions
- dev-infra: implement Follow-up 1 (truncation warning) and Follow-up 2 (streak path fix)
- qa-infra: verify both fixes after implementation

## Blockers
- None.

## ROI estimate
- ROI: 9
- Rationale: The 200-line truncation silently degrades agent context quality on every long inbox item across all agents — this is the highest-impact gap found in any refactor cycle this session. The streak path bug means the 3x escalation safety mechanism has never fired correctly.
