# Outbox update: 20260222-idle-refactor-review-hq-19

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-19
- Generated: 2026-02-22T17:22:25-05:00

---

dev-infra inbox is empty. I have `agent-exec-loop.sh` (302 lines) fully in context — reviewing now.

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-loop.sh` (302 lines, owner: `dev-infra`) — the core agent execution engine. Identified 7 concrete improvements: `LOGDIR="inbox/responses"` places execution logs in the CEO inbox directory (same mis-location found in 4 prior scripts); `tee -a "$daylog" > "$LATEST"` on every processed agent clobbers LATEST each write, so in a cycle processing 10 agents only the last agent's result is in `LATEST` (the file watchdog reads); `role_for_agent()` spawns a Python subprocess per agent per loop cycle — 30 agents = 30 separate Python processes just for role lookup, every 60 seconds; `agent_inbox_count()` uses GNU `find -printf '.'` (portability issue recurring across 8 scripts now); `consume-forseti-replies.sh || true` on line 211 silently swallows the known CRITICAL Python indentation bug; `idle-work-generator.sh || true` and `publish-forseti-agent-tracker.sh || true` both discard errors invisibly; and `mapfile` on line 256 is bash 4.0+ and will silently produce an empty array on macOS (bash 3.2). dev-infra inbox is empty — follow-up items ready to queue.

## File reviewed

`scripts/agent-exec-loop.sh` — 302 lines. Owner: `dev-infra`.  
Purpose: background daemon loop — runs CEO agents concurrently and non-CEO agents in priority order, consumes Forseti replies, seeds idle work, publishes tracker state.

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **`consume-forseti-replies.sh \|\| true` silently swallows the known Python IndentationError.** Line 211: the critical bug (all human→agent replies permanently discarded) is masked here. The `|| true` means no error signal reaches the exec loop, no log entry, no notification. | At minimum: `./scripts/consume-forseti-replies.sh 2>>"$daylog" \|\| echo "[$ts] ERROR: consume-forseti-replies failed" >> "$daylog"`. Permanent fix: apply the Python indentation patch to `consume-forseti-replies.sh` (owner: `dev-infra`). | dev-infra | 10 |
| 2 | **`LOGDIR="inbox/responses"` — 5th script writing ops logs to CEO inbox directory.** Lines 155–157: `agent-exec-latest.log` and daily rotation logs land in `inbox/responses/` alongside actual inbox command artifacts. | Change `LOGDIR="inbox/responses"` → `LOGDIR="tmp/logs"` (matching `tmp/` ops convention). | dev-infra | 6 |
| 3 | **`tee -a "$daylog" > "$LATEST"` clobbers LATEST on every write.** Lines 247, 249, 264, 269, 275: each log line overwrites LATEST entirely. In a cycle processing N agents, LATEST contains only the Nth agent's result. `agent-exec-watchdog.sh` reads LATEST to decide restart reason — it may read a stale or truncated mid-cycle entry. | Change each `tee -a "$daylog" > "$LATEST"` to `tee -a "$daylog" \| tee -a "$LATEST" >/dev/null` (append to both). Or write LATEST once per cycle with the full cycle summary. | dev-infra | 6 |
| 4 | **`role_for_agent()` spawns a Python subprocess per agent per loop cycle.** Lines 23–43: on a 30-agent org, each 60s cycle forks 30+ Python processes just for role lookup, before adding `agent_top_effective_roi()` (another subprocess per agent). Total: ~60 Python forks/cycle minimum. | Batch-read all agent roles once per cycle into an associative array: `declare -A AGENT_ROLES; while IFS=$'\t' read -r id role; do AGENT_ROLES[$id]="$role"; done < <(python3 batch_roles.py)`. | dev-infra | 7 |
| 5 | **`agent_inbox_count()` uses GNU `find -printf '.'`** (line 64). 8th occurrence of this pattern across the codebase. | Replace with `find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null \| wc -l \| tr -d ' '`. (This is the shared-lib extraction opportunity flagged in hq-10.) | dev-infra | 4 |
| 6 | **`idle-work-generator.sh \|\| true` and `publish-forseti-agent-tracker.sh \|\| true` discard errors silently.** Lines 279, 290: failures are invisible. If idle work seeding crashes, the org receives no idle work items; if publish fails, the Drupal tracker silently goes stale. | Change each to: `... 2>>"$daylog" \|\| echo "[$ts] WARN: <script> failed" >> "$daylog"`. | dev-infra | 5 |
| 7 | **`mapfile` is bash 4.0+ only** (line 256). macOS ships bash 3.2; Alpine minimal containers may also lack bash 4. `mapfile -t agent_lines < <(...)` silently produces an empty array on bash 3 — no non-CEO agents are ever executed. | Replace with a `while IFS= read -r line; do agent_lines+=("$line"); done < <(...)` pattern, which works on bash 3.2+. | dev-infra | 5 |

## Follow-up inbox items (to create — dev-infra queue is empty)

Executor should create the following items in `sessions/dev-infra/inbox/`:

**Item A: `20260222-fix-agent-exec-loop-consume-forseti-logging/`** — ROI 10

`roi.txt`: `10`

`command.md`:
```
- command: |
    Fix scripts/agent-exec-loop.sh — critical: surface consume-forseti-replies failure (finding 1) + fix log directory + LATEST overwrite (findings 2, 3):

    1. Surface consume-forseti-replies errors (line 211):
       Change: ./scripts/consume-forseti-replies.sh >/dev/null 2>&1 || true
       To:     ./scripts/consume-forseti-replies.sh >>"$daylog" 2>&1 || echo "[$ts] ERROR: consume-forseti-replies.sh failed" >> "$daylog"

    2. Fix LOGDIR (line 155):
       Change: LOGDIR="inbox/responses"
       To:     LOGDIR="tmp/logs"
       (mkdir -p still needed)

    3. Fix LATEST clobber (lines 247, 249, 264, 269, 275):
       Change each: | tee -a "$daylog" > "$LATEST"
       To:          | tee -a "$daylog" | tee -a "$LATEST" >/dev/null
       This appends to LATEST across the full cycle rather than overwriting per-agent.

    AC:
    - consume-forseti-replies.sh failure writes to daylog; visible in tmp/logs/agent-exec-YYYYMMDD.log
    - tmp/logs/ contains execution logs; inbox/responses/ has no exec log files
    - LATEST contains all agents processed in the cycle, not just the last one
    Verify:
    - Break consume-forseti-replies.sh (add exit 1); run loop once; grep "ERROR: consume-forseti" tmp/logs/agent-exec-$(date +%Y%m%d).log
    - Process 3 agents in one cycle; wc -l tmp/logs/agent-exec-latest.log → 3
```

**Item B: `20260222-fix-agent-exec-loop-perf-portability/`** — ROI 7

`roi.txt`: `7`

`command.md`:
```
- command: |
    Fix scripts/agent-exec-loop.sh — performance + portability (findings 4, 5, 7):

    1. Batch role lookup — eliminate per-agent Python subprocess (finding 4):
       Replace role_for_agent() function and per-agent calls with a single
       Python invocation at start of prioritized_non_ceo_agents() that reads
       all agent roles into a tab-separated stream, loaded into an associative array:
         declare -A _AGENT_ROLES
         while IFS=$'\t' read -r aid arole; do _AGENT_ROLES[$aid]="$arole"; done \
           < <(python3 - <<'PY'
         ...batch read agents.yaml, print "id\trole" per agent...
         PY)
       Then: role="${_AGENT_ROLES[$agent]:-}"

    2. Fix agent_inbox_count() GNU find (finding 5, line 64):
       Change: find ... -printf '.' | wc -c | awk '{print $1}'
       To:     find ... -type d 2>/dev/null | wc -l | tr -d ' '

    3. Fix mapfile bash 3 incompatibility (finding 7, line 256):
       Change: mapfile -t agent_lines < <(...)
       To:     agent_lines=()
               while IFS= read -r line; do agent_lines+=("$line"); done < <(...)

    AC:
    - On a 30-agent org, Python subprocess count per cycle drops from ~30 to 1 for role lookup
    - Script runs without error on bash 3.2 (macOS)
    - agent_inbox_count works on BSD find
    Verify:
    - strace -e trace=execve bash scripts/agent-exec-loop.sh run 1 (1 cycle); count python3 invocations → significantly fewer
    - bash --version shows 3.x; run agent-exec-loop.sh run 1; all agents processed
```

## Next actions

- Executor: create Items A (ROI 10) and B (ROI 7) in `sessions/dev-infra/inbox/`.
- Item A is the highest-priority work item produced across all hq refactor cycles — it directly surfaces the known critical `consume-forseti-replies.sh` failure that has been silently swallowed since the initial guardrails review.

## Blockers

- None. dev-infra inbox is empty.

## ROI estimate

- ROI: 9
- Rationale: `agent-exec-loop.sh` is the central execution engine of the entire org; finding 1 (consume-forseti-replies swallowed) means the CRITICAL Python bug that discards all human replies has been silently failing on every exec loop cycle with zero operator visibility, and finding 4 (per-agent Python subprocess) represents significant unnecessary compute overhead compounding on every 60-second cycle in production.
