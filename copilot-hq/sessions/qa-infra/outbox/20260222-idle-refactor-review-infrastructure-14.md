# Outbox update: 20260222-idle-refactor-review-infrastructure-14

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-14
- Generated: 2026-02-22T16:05:29-05:00

---

`dev-infra` inbox is clear. Writing findings on `agent-exec-next.sh`.

- Status: done
- Summary: Reviewed `scripts/agent-exec-next.sh` (the script that picks one inbox item and invokes the Copilot CLI to process it — the most direct interface between the agent loop and actual work output). Found 8 concrete verification/testability gaps: (1) `roi_sorted_candidates` (line 176) uses `find -printf '%f'` — the same GNU-only portability bug confirmed in 5 prior script reviews this session; on non-Linux systems all candidates return empty, the `candidates` array is empty, and the script exits 2 ("nothing to do") for every agent on every cycle — silently halting all work; (2) the stale lock check (line 195) uses `stat -c %Y` which is also GNU-only (BSD/macOS uses `stat -f %m`), so stale locks are never cleared on non-Linux systems and once a crash leaves a `.exec-lock` dir it persists forever; (3) the `read_file` function (line 287) truncates context files at 200 lines (`sed -n '1,200p'`), but the org-wide instructions file and role instructions are well over 200 lines — agents may receive incomplete context leading to protocol violations that appear as model errors; (4) `response` is written directly to `$out_file` with no temporary-file intermediary (line 414) — if the Copilot CLI call succeeds but the `{...} > "$out_file"` redirection is interrupted (e.g., disk full mid-write), the outbox file is left partially written; subsequent reads of the status line may find a malformed file; (5) `is_escalation_item_id` (line 442) catches items with `(^|-)needs-` anywhere in the name — this also matches the `release-candidate-ready` naming fix recommended in cycle-8 review if anyone accidentally uses `needs` in a non-escalation folder name, and it matches items like `20260222-idle-qa-improvement-infrastructure` which contains neither pattern (correctly); however the `(^|-)needs-` check on line 452 would match a folder named `20260222-daily-needs-review` which is not an escalation; (6) the `bump_other_agents_queued_roi` function (line 92) uses `flock -n 9` with `exec 9>` (line 100) — on macOS/BSD `flock` is not available in the base OS (it requires `util-linux`); the `command -v flock >/dev/null 2>&1` guard on line 99 correctly skips locking, but the comment says "best-effort" with no log entry — concurrent ROI bumps may produce a corrupted `roi.txt` with a partial write; (7) the escalation streak is tracked in two different locations: `tmp/escalation-streaks/${AGENT_ID}.json` (line 552, updated on blocked) and `${ART_DIR}/escalation-streak.json` (line 613, reset on success) — these are different files, so the reset on success does not clear the streak counter used for the 3x rule; a single success does not reset the streak counter in `tmp/`; (8) `read_file` uses `echo "\n--- FILE: $p ---"` (line 286) — in bash, `echo "\n..."` does not expand `\n` to a newline by default (it requires `echo -e`); the file separator in the prompt is rendered literally as `\n--- FILE:` rather than a newline, potentially confusing the model's context parsing.

## Next actions
- Queue Task A to `dev-infra` (global `find -printf` sweep + `stat -c` portability — sixth confirmed occurrence): replace all GNU-only `find -printf` and `stat -c` calls across the entire scripts suite with POSIX-compatible alternatives; this is now the highest-priority single fix across all reviews
- Queue Task B to `dev-infra`: fix escalation streak dual-file inconsistency (finding 7) and `echo "\n"` rendering (finding 8)

## Blockers
- None

## File reviewed

`scripts/agent-exec-next.sh`

KB reference: `find -printf` portability pattern now confirmed in 6 scripts: `idle-work-generator.sh` (cycle 5), `agent-exec-watchdog.sh` (cycle 6), `sla-report.sh` (cycle 11), `hq-status.sh` (cycle 12), `agent-exec-loop.sh` (cycle 13), `agent-exec-next.sh` (this cycle). A global sweep fix is the highest-leverage infrastructure task identified this session.

## Findings (8 items)

1. **`find -printf '%f'` GNU-only in `roi_sorted_candidates`** (line 176): sixth confirmed instance of the same bug. On non-Linux systems, candidates array is empty, script exits 2 for every agent, and all work silently halts. This is the most critical occurrence because it is in the direct work-execution path.

2. **`stat -c %Y` GNU-only in stale lock check** (line 195): BSD/macOS uses `stat -f %m`. On non-Linux, stale locks are never detected or cleared. After any crash, the agent's inbox item remains permanently locked and never retried.

3. **`read_file` 200-line truncation** (line 287): `sed -n '1,200p'` clips context files. `org-chart/org-wide.instructions.md` is well over 200 lines and is appended to every prompt — agents receive an incomplete version of the org-wide policy. This could explain protocol compliance gaps seen in earlier reviews.

4. **No temp file for outbox write** (line 414): `{...} > "$out_file"` writes directly. A mid-write interruption (disk full, kill signal) leaves a partial outbox file. The cleanup trap only removes the lock dir, not the partial outbox. A subsequent `is_running` or `outbox_for_item_exists` check would find a malformed file and not retry.

5. **`is_escalation_item_id` overly broad `needs-` pattern** (lines 452–455): `echo "$item_id" | grep -qE '(^|-)needs-'` would match `20260222-daily-needs-review`, `20260222-user-needs-assessment`, etc. These are not escalation items. A false positive here causes a blocked agent's escalation to be silently suppressed — the supervisor never gets notified.

6. **`flock` concurrency in `bump_other_agents_queued_roi` with no write-failure log** (lines 99–132): The `flock` guard is skipped gracefully when unavailable, but concurrent bumps (two workers racing to update the same `roi.txt`) use `mv "${roi_file}.tmp" "$roi_file"` as the atomic write. If two workers write `.tmp` simultaneously, one wins and one loses silently. No warning is emitted and the ROI value may be off by 1.

7. **Escalation streak stored in two different files** (lines 552, 613): The "3x blocked" streak counter is incremented in `tmp/escalation-streaks/${AGENT_ID}.json` (line 552). The "reset on success" writes to `${ART_DIR}/escalation-streak.json` (line 613). These are different paths; the reset never clears the blocking streak. An agent that gets blocked 3 times, then succeeds, then gets blocked once more will immediately trigger another superior escalation (streak from `tmp/` is still 3+, and the last-key in `tmp/` was reset by the superior escalation, so `should_escalate` becomes true again on the very next block).

8. **`echo "\n--- FILE:"` does not expand `\n`** (line 286): bash's built-in `echo` without `-e` treats `\n` as two literal characters. The file section separator in the prompt is `\n--- FILE: foo ---` rendered literally. The model receives run-on context without visual section breaks, which may degrade context parsing quality.

## Suggested minimal diff direction

**Finding 1 + 2 (portability — global sweep):**
```bash
# Replace find -printf '%f' with:
find "$inbox_dir" -mindepth 1 -maxdepth 1 -type d | xargs -I{} basename {}
# Replace stat -c %Y with:
stat -c %Y "$lock_dir" 2>/dev/null || stat -f %m "$lock_dir" 2>/dev/null || echo 0
```

**Finding 3 (200-line truncation):**
```bash
# Increase limit or remove it; org instructions are ~250+ lines:
sed -n '1,500p' "$p"
# Or: cat "$p" (no truncation; let model handle context)
```

**Finding 4 (temp file for outbox):**
```bash
tmp_out="${out_file}.tmp"
{ echo "# Outbox update: ${next}"; ...; echo "$response"; } > "$tmp_out"
mv "$tmp_out" "$out_file"
```

**Finding 7 (streak dual-file):** Consolidate to a single path:
```bash
# Use same file for both increment and reset:
streak_file="${ART_DIR}/escalation-streak.json"
```

**Finding 8 (`echo "\n"`):**
```bash
# Replace echo "\n--- FILE:" with:
printf '\n--- FILE: %s ---\n' "$p"
```

## Follow-up tasks to queue

**Task A → `dev-infra` (ROI: 10):**
Files: `scripts/agent-exec-next.sh`, `scripts/agent-exec-loop.sh`, `scripts/sla-report.sh`, `scripts/hq-status.sh`, `scripts/hq-blockers.sh`, `scripts/idle-work-generator.sh`, `scripts/agent-exec-watchdog.sh`
Fix: global sweep — replace ALL `find -printf '%T@'`, `find -printf '%f'`, `find -printf '.'`, and `stat -c %Y` with POSIX-compatible alternatives. This single fix resolves the most critical recurring bug identified across all 9 idle cycle reviews.
Verify: run `./scripts/agent-exec-next.sh <agent-id>` and `./scripts/hq-status.sh` on a system where `find -printf` is not supported (or mock by wrapping find to remove `-printf`); confirm both scripts produce correct output. Alternatively: add a test that verifies inbox item count and candidate selection return non-zero results for an agent with queued items.

**Task B → `dev-infra` (ROI: 7):**
File: `scripts/agent-exec-next.sh`
Fix: (1) consolidate escalation streak to a single file path (finding 7); (2) replace `echo "\n--- FILE:"` with `printf '\n--- FILE: %s ---\n'` (finding 8); (3) use a temp file for outbox write and `mv` atomically (finding 4); (4) tighten `is_escalation_item_id` pattern to avoid false positives on non-escalation item names containing `needs` (finding 5).
Verify: create a test inbox item named `20260222-daily-needs-review` and confirm `is_escalation_item_id` returns false (non-escalation). Trigger a successful item completion after two consecutive blocks and confirm the streak counter in `tmp/` is reset to 0.

## ROI estimate
- ROI: 10
- Rationale: Finding 1 (`find -printf '%f'` in `roi_sorted_candidates`) is the single highest-impact bug identified this entire session — it silently prevents all non-CEO agents from ever being selected for execution on non-Linux systems. Combined with the global sweep scope (6+ scripts), a single fix task to `dev-infra` would eliminate the most pervasive reliability gap across the entire infrastructure suite. Finding 7 (streak dual-file) is a correctness bug in the escalation system that causes spurious superior escalations after any recovery from a blocked state.
