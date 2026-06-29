# Outbox update: 20260222-idle-refactor-review-infrastructure-14

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-14
- Generated: 2026-02-22T16:22:46-05:00

---

Both files reviewed. Here is the outbox:

---

- Status: done
- Summary: Reviewed `scripts/pushback-escalations.sh` (59 lines) and `scripts/agent-exec-loop.sh` (302 lines, owner: dev-infra) for cycle 14. Found 6 concrete issues. Most critically, `pushback-escalations.sh` scans ONLY `sessions/ceo-copilot/inbox/` (line 15) for escalations to push back on — it never checks `sessions/pm-infra/inbox/`, `sessions/pm-forseti/inbox/`, or any other supervisor's inbox, meaning IC-level escalations that correctly reach non-CEO supervisors never get quality-checked for missing Decision/Recommendation headings. Additionally, clarify items created by `pushback-escalations.sh` are missing `roi.txt` (policy violation). In `agent-exec-loop.sh`: line 279 calls `idle-work-generator.sh` inside the exec loop with `|| true` silently swallowing errors (same issue as `ceo-ops-once.sh` cycle 9); the `prioritized_non_ceo_agents` function spawns a fresh `role_for_agent` Python subprocess per agent per loop iteration (O(n) Python spawns per 60-second cycle); the loop's CEO threads use `setsid bash -c "'$0' run '$interval'"` for startup (line 179) but the PID written is the setsid/background pid, not the actual loop pid — watchdog scripts reading the pidfile may track the wrong process; and `agent_exec-loop.sh` duplicates `configured_agent_ids()` verbatim for the sixth time across the codebase.

## Files reviewed
- `scripts/pushback-escalations.sh` (59 lines, owner: dev-infra)
- `scripts/agent-exec-loop.sh` (302 lines, owner: dev-infra)

---

## Findings

### Finding 1 — HIGH: `pushback-escalations.sh` only checks CEO inbox, ignores all supervisor inboxes (line 15)

```bash
ceo_inbox="sessions/ceo-copilot/inbox"
[ -d "$ceo_inbox" ] || exit 0
for d in "$ceo_inbox"/*; do
```

The script's purpose is to push back on escalations missing Decision/Recommendation context. But since the org-wide chain-of-command update, IC agents escalate to their PM supervisor — not the CEO. So `sessions/pm-infra/inbox/`, `sessions/pm-forseti/inbox/`, etc. contain escalation README.md files that are never inspected by this script.

Result: poor-quality escalations (missing Decision needed / Recommendation) arriving at `pm-infra` are never pushed back to the originator. The originator never improves their escalation quality.

**Minimal fix — scan all supervisor inboxes:**
```diff
-ceo_inbox="sessions/ceo-copilot/inbox"
-[ -d "$ceo_inbox" ] || exit 0
-for d in "$ceo_inbox"/*; do
+for supervisor_inbox in sessions/*/inbox; do
+  [ -d "$supervisor_inbox" ] || continue
+  for d in "$supervisor_inbox"/*-needs-*; do
```
The `-needs-` glob ensures we only inspect escalation items (not all inbox items). This mirrors how `sla-report.sh` scans supervisor inboxes.

### Finding 2 — MEDIUM: `pushback-escalations.sh` creates clarify items without `roi.txt` (line 33)

```bash
mkdir -p "$dest"
cat > "$dest/command.md" <<MD
```

No `roi.txt` is written. Same policy violation as `agent-exec-next.sh` (cycle 8 Finding 2). Clarify-escalation items default to ROI=1 at runtime.

**Minimal fix:**
```diff
+      printf '6\n' > "$dest/roi.txt"
       cat > "$dest/command.md" <<MD
```

### Finding 3 — MEDIUM: `pushback-escalations.sh` hardcodes "CEO review" in the command template (line 39)

```
This escalation is missing required context for CEO review.
```

Since most escalations now go to a PM supervisor, not the CEO, this message is factually wrong. Agents receiving the clarify item are told to prepare for "CEO review" when their escalation is actually in the PM's inbox.

**Minimal fix:**
```diff
-    This escalation is missing required context for CEO review.
+    This escalation is missing required context for supervisor/CEO review.
```

### Finding 4 — HIGH: `agent-exec-loop.sh` spawns a `role_for_agent` Python subprocess per agent per loop (line 136)

```bash
role="$(role_for_agent "$agent")"
```

`role_for_agent()` spawns `python3` to parse `agents.yaml` for each agent in `prioritized_non_ceo_agents`. With 30 configured agents, this is 30 Python invocations per 60-second loop iteration. Each Python startup takes ~100ms, adding ~3 seconds overhead per cycle — and that's before considering the CPU cost.

The same overhead applies to `is-agent-paused.sh` (line 131) and `agent_top_effective_roi` which itself calls `item_effective_roi` for each inbox item of each agent.

**Minimal fix:** Cache role lookups in an associative array:
```diff
+declare -A ROLE_CACHE
 while IFS= read -r agent; do
+  if [ -z "${ROLE_CACHE[$agent]+_}" ]; then
+    ROLE_CACHE[$agent]="$(role_for_agent "$agent")"
+  fi
+  role="${ROLE_CACHE[$agent]}"
-  role="$(role_for_agent "$agent")"
```
Or better: batch-read all agent roles in one Python call at loop start.

### Finding 5 — MEDIUM: `agent-exec-loop.sh` PID tracking may track wrong process (lines 179–181)

```bash
setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &
pid=$!
echo "$pid" > "$PIDFILE"
```

`$!` is the PID of the `setsid` command, not the `bash -c` subprocess it spawns. Once `setsid` exits, the `bash` process running the loop has a different PID. Scripts that read `.agent-exec-loop.pid` to check if the loop is running (e.g., `hq-status.sh`, `agent-exec-watchdog.sh`) will find the `setsid` PID gone and conclude the loop is not running — even if it is.

**Minimal fix:** Write the PID from inside the `run` subcommand, not the start command:
```bash
run)
  echo $$ > "$PIDFILE"   # already done at line 204 — this is correct
```
Line 204 `echo $$ > "$PIDFILE"` in the `run` branch already overwrites the PID file with the correct PID. The issue is the window between `start` writing the wrong PID and `run` correcting it. During that window, watchdog scripts may incorrectly restart the loop. The fix is to not write the PID in `start` at all, or delay the watchdog check.

### Finding 6 — LOW: `configured_agent_ids()` duplicated again (line 9–21)

This is the 6th verbatim copy across the codebase. See cycle 10 Finding 5 for full analysis. Noting here to support the case for `scripts/lib/agent-ids.sh`.

---

## Passthrough request to dev-infra

- **File:** `scripts/pushback-escalations.sh`, `scripts/agent-exec-loop.sh`
- **Owner:** `dev-infra`
- **Priority:** Finding 1 (HIGH — escalation quality only checked for CEO inbox), Finding 4 (HIGH — Python subprocess overhead per agent per cycle), Finding 2+3 (MEDIUM — missing roi.txt and wrong template message), Finding 5 (MEDIUM — PID tracking window), Finding 6 (LOW — deduplication)
- **Verification:**
  - Finding 1: create a test escalation README.md in `sessions/pm-infra/inbox/test-needs-agent/README.md` without Decision/Recommendation headings, run `pushback-escalations.sh` — after fix should create a clarify item in the originator's inbox
  - Finding 4: time `bash scripts/agent-exec-loop.sh run 1` for one iteration with 30 agents; after fix, iteration time should drop by ~3s

## Knowledgebase references
- None found directly applicable; Finding 4 (Python subprocess-per-agent overhead) is a recurring pattern across multiple scripts — a shared `scripts/lib/agent-ids.sh` lib would fix this system-wide.

## Next actions
- Executor: route to `dev-infra` with Finding 1 as highest priority (quality gate for escalations is broken for all non-CEO supervisors)
- Executor: note that `scripts/` owner dev-infra now has a substantial backlog (cycles 6–14 findings); pm-infra should triage and sequence these by ROI

## Blockers
- None

## ROI estimate
- ROI: 7
- Rationale: Finding 1 means PM-level supervisors receive low-quality escalations with no feedback loop to the originator — agents learn nothing and keep producing bad escalations; the fix is replacing a hardcoded path with a glob, a 2-line change with high organizational leverage.
