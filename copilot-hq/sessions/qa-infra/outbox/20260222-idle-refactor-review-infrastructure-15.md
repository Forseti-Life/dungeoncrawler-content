# Outbox update: 20260222-idle-refactor-review-infrastructure-15

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-15
- Generated: 2026-02-22T16:09:12-05:00

---

I have sufficient findings. `auto-checkpoint.sh` is the review target for this cycle, supplemented by findings on the `is-agent-paused.sh` hot-path script.

- Status: done
- Summary: Reviewed `scripts/auto-checkpoint.sh` (the 2-hourly git checkpoint script for both repos) and cross-checked `scripts/is-agent-paused.sh` (hot-path per-agent-per-cycle paused check). Found 8 concrete verification/testability gaps: (1) `ISO` timestamp is frozen at script start (line 12) — the same recurring pattern found across 6+ other scripts; all commit messages and log lines carry the script invocation time, not the actual commit time for each repo; (2) under `set -euo pipefail`, if `git push -q` fails on repo 1 (line 62) — network error, auth failure, rejected push — the script aborts mid-loop and repo 2 is never checkpointed, with no notification emitted and no partial-failure log; (3) hardcoded absolute paths (lines 8–9) break portability if repos move; (4) `denylist_present` (lines 18–21) checks filenames only, not content — a secrets file named `.env_backup`, `local_settings_prod.php`, or `config/credentials.json` passes all checks and is committed silently; (5) for renamed files in `git status --porcelain=v1` output, `awk '{print $2}'` captures the old path but not the new path (format: `R  old -> new`); a secret file renamed to a denylist-matching name would still pass the check because the new name is in `$4`; (6) no concurrent execution guard — if `git push` stalls (network hang) past the 2-hour cron interval, a second instance fires, both run `git add -A` simultaneously, and a race condition between the two commits can cause a non-fast-forward push error or data loss; (7) no commit hash in the "PUSHED" log line — `echo "PUSHED: $repo"` provides no traceability; (8) `is-agent-paused.sh` is called as a subprocess per-agent per-cycle in both `agent-exec-loop.sh` and `agent-exec-next.sh` — it spawns a Python process every invocation with no caching; for 20 agents at a 60-second interval this is 40 Python subprocesses per minute just for paused-status checks.

## Next actions
- Queue Task A to `dev-infra`: fix `git push` loop-abort and add `flock`-based concurrency guard to `auto-checkpoint.sh`
- Queue Task B to `dev-infra`: add memoized or batched paused-status check to eliminate per-agent Python subprocess fan-out in hot path

## Blockers
- None

## File reviewed

`scripts/auto-checkpoint.sh` (primary) and `scripts/is-agent-paused.sh` (cross-reference)

KB reference: frozen-timestamp pattern documented across cycles 6, 9, 13 — fourth+ occurrence. `find -printf` global sweep task (cycle 14 Task A, ROI 10) remains the highest-priority open task for `dev-infra`.

## Findings (8 items)

1. **Frozen timestamp** (line 12): `ISO="$(date -Iseconds)"` set once at start. Commit messages and log lines for both repos carry the script invocation time; if repo 1 push takes 30 seconds, repo 2's commit message still says the original invocation time. Fifth+ occurrence of this pattern across the scripts suite.

2. **`git push` failure aborts remaining repos** (line 62): `set -euo pipefail` causes the script to exit immediately if `git push -q` exits non-zero. Repo 2 is never processed. No warning, no notification, no retry. The checkpoint log for that cycle shows PUSHED for repo 1 (if it committed before the push) then nothing for repo 2.

3. **Hardcoded absolute paths** (lines 8–9): `REPOS=("/home/keithaumiller/copilot-sessions-hq" "/home/keithaumiller/forseti.life")` — breaks if repos move. The same repo-root detection pattern used elsewhere (`ROOT_DIR="$(cd ... && pwd)"`) is not used here.

4. **Denylist checks filenames only, not content** (lines 18–21): `denylist_present` matches against file paths in `git status`. A secrets file with a non-matching name (`.env_backup`, `credentials.json`, `config/drupal_key.txt`) passes all checks and is committed. The denylist is a last-resort safety net but provides only name-based protection.

5. **Renamed files: new path not checked in denylist** (line 20): `git status --porcelain=v1` for a rename shows `R  old -> new`. `awk '{print $2}'` extracts `old` only. If `settings.php` is renamed to `settings.php.bak` (which does not match the denylist) then renamed again to `settings.php` in a new session, the new path in field 4 is not scanned.

6. **No concurrency guard** (entire script): if the previous `auto-checkpoint.sh` run is still executing when cron fires again (e.g., `git push` stalls on slow network), two instances run simultaneously. Both call `git add -A` on the same working tree. One will commit first; the second's `git push` will fail with "non-fast-forward" and abort (per finding 2), leaving repo 2 uncheckpointed.

7. **No commit hash in PUSHED log** (line 64): `echo "[$ISO] PUSHED: $repo"` provides no traceability. There is no way to audit which commit was pushed from the log output alone. Minimal fix: `echo "[$ISO] PUSHED: $repo @ $(git rev-parse --short HEAD)"`.

8. **`is-agent-paused.sh` spawns Python per-agent per-cycle** (separate file): called from both `agent-exec-loop.sh` (line 131) and `agent-exec-next.sh` (line 109) with no caching. For 20 configured agents: `agent-exec-loop.sh` runs `prioritized_non_ceo_agents` which calls `is-agent-paused.sh` per agent; `agent-exec-next.sh`'s `bump_other_agents_queued_roi` also calls it per agent. At a 60-second loop interval, this is 40+ Python subprocesses per minute for a value that almost never changes.

## Suggested minimal diff direction

**Finding 2 (loop-abort on push failure):** Wrap each repo in a subshell:
```bash
for repo in "${REPOS[@]}"; do
  (
    # ...existing logic...
    git push -q || { echo "[$ISO] PUSH FAILED: $repo" >&2; exit 1; }
    echo "[$ISO] PUSHED: $repo @ $(git rev-parse --short HEAD)"
  ) || echo "[$ISO] ERROR: checkpoint failed for $repo" >&2
done
```

**Finding 6 (concurrency guard):**
```bash
LOCKFILE="/tmp/auto-checkpoint.lock"
exec 9>"$LOCKFILE"
flock -n 9 || { echo "[$ISO] SKIP: another checkpoint already running" >&2; exit 0; }
```

**Finding 7 (commit hash in log):**
```bash
echo "[$ISO] PUSHED: $repo @ $(git rev-parse --short HEAD)"
```

**Finding 8 (`is-agent-paused.sh` memoization):** Batch the paused check once per loop cycle:
```bash
# In agent-exec-loop.sh, before the prioritized_non_ceo_agents loop:
declare -A PAUSED_CACHE
while IFS= read -r agent; do
  PAUSED_CACHE[$agent]="$(./scripts/is-agent-paused.sh "$agent" 2>/dev/null || echo false)"
done < <(configured_agent_ids)
# Then: [ "${PAUSED_CACHE[$agent]:-false}" = "true" ] instead of calling the script
```

## Follow-up tasks to queue

**Task A → `dev-infra` (ROI: 7):**
File: `scripts/auto-checkpoint.sh`
Fix: (1) wrap each repo iteration in a subshell so push failure on repo 1 does not abort repo 2 (finding 2); (2) add `flock` concurrency guard at script entry (finding 6); (3) add commit hash to PUSHED log line (finding 7).
Verify: simulate a push failure on repo 1 by temporarily setting a bad remote URL; confirm the script continues to repo 2 and emits an ERROR log for repo 1. Verify that a second concurrent invocation exits immediately with "already running" message.

**Task B → `dev-infra` (ROI: 6):**
Files: `scripts/agent-exec-loop.sh`, `scripts/agent-exec-next.sh`, `scripts/is-agent-paused.sh`
Fix: batch the paused-status check once per loop cycle in `agent-exec-loop.sh` using a pre-populated associative array; replace per-call `./scripts/is-agent-paused.sh` invocations with cache lookups. Alternatively, add a `list-paused-agents.sh` script that outputs all paused agent IDs in one Python call.
Verify: with 20 configured agents and a 60-second loop interval, confirm that `ps aux` during a loop cycle shows at most 1 Python process for paused-status checks rather than 20.

## ROI estimate
- ROI: 6
- Rationale: Finding 2 (push-failure aborts subsequent repos) is a silent data-loss risk — if HQ push fails, Forseti repo is never checkpointed for that 2-hour window, losing all agent work since the last successful checkpoint. Finding 8 (Python subprocess fan-out) degrades system performance linearly with org size and is trivially fixable with a 1-time batch call pattern.
