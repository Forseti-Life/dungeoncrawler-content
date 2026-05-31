# Outbox update: 20260222-idle-refactor-review-infrastructure-19

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-19
- Generated: 2026-02-22T17:07:16-05:00

---

Both files fully reviewed. Here is the outbox:

---

- Status: done
- Summary: Reviewed `scripts/ceo-ops-once.sh` (72 lines) and `scripts/hq-status.sh` (180 lines, owner: dev-infra) for cycle 19. Found 6 concrete issues. Most critically, `ceo-ops-once.sh` reads `org-chart/priorities.yaml` at line 23 but this file is not listed in `file-ownership.md` and does not exist on a fresh clone — the script crashes with `FileNotFoundError` on every CEO ops cycle if the file is missing, printing no useful error and aborting the entire report before any status/blocker output; `hq-status.sh` line 121 (`agent_next_inbox`) correctly sorts by filename for next-inbox display, but line 73 (`latest_mtime_epoch`) uses mtime-based find for "last active" timestamps — as with `sla-report.sh` and `hq-blockers.sh`, mtime churn from `.exec-lock/` creation causes recent "last active" timestamps even for idle agents; `hq-status.sh` line 125 (`agent_exec_status`) uses a 30-minute mtime heuristic to infer whether an agent is "executing" — this is unreliable (an agent that produced one outbox 29 minutes ago shows `exec=yes` even if its inbox is empty); `hq-status.sh` has `configured_agent_ids()` as the 9th verbatim copy; `ceo-ops-once.sh` calls `./scripts/hq-blockers.sh count` twice in a row (lines 56 and in the `head -n 200` pipe at line 51) — `hq-blockers.sh` parses all agent outboxes each call, making two full scans per CEO ops cycle; and `ceo-ops-once.sh` uses `|| true` to swallow `idle-work-generator.sh` errors (line 71) rather than logging them — the Python IndentationError fires silently every cycle.

## Files reviewed
- `scripts/ceo-ops-once.sh` (72 lines, owner: dev-infra)
- `scripts/hq-status.sh` (180 lines, owner: dev-infra)

---

## Findings

### Finding 1 — CRITICAL: `ceo-ops-once.sh` crashes if `org-chart/priorities.yaml` is missing (line 23)

```python
p = pathlib.Path('org-chart/priorities.yaml').read_text(encoding='utf-8')
```

This is a hard read with no existence check — `FileNotFoundError` is thrown if the file is absent. With `set -euo pipefail` in the shell wrapper and the Python heredoc exiting non-zero, the CEO ops cycle exits at line 17–43, printing no HQ status, no blockers report, and no idle seeding. The priority ranking section is the very first thing in the script — a missing `priorities.yaml` kills the entire report.

`org-chart/priorities.yaml` is not mentioned in `file-ownership.md`, not mentioned in `README.md`, and appears to have no creation script. On a fresh clone it does not exist.

**Minimal fix — add existence guard:**
```diff
-p = pathlib.Path('org-chart/priorities.yaml').read_text(encoding='utf-8')
+path = pathlib.Path('org-chart/priorities.yaml')
+if not path.exists():
+    print("(priorities.yaml not found — skipping)")
+    raise SystemExit(0)
+p = path.read_text(encoding='utf-8')
```
Also: add `org-chart/priorities.yaml` to `file-ownership.md` as owned by `ceo-copilot`.

### Finding 2 — HIGH: `hq-blockers.sh count` called twice per CEO ops cycle (lines 51, 56)

```bash
./scripts/hq-blockers.sh | head -n 200         # full scan
...
blocked_count=$(./scripts/hq-blockers.sh count 2>/dev/null || echo 0)  # second full scan
```

`hq-blockers.sh` iterates all agents, reads their latest outbox file, and checks status. This is an O(N) file-read scan. `ceo-ops-once.sh` runs it twice per cycle — once for the full report, once for the count used in "CEO actions suggested." The second call is redundant.

**Minimal fix:** Capture and reuse from the first call:
```diff
-./scripts/hq-blockers.sh | head -n 200
-...
-blocked_count=$(./scripts/hq-blockers.sh count 2>/dev/null || echo 0)
+blocker_output="$(./scripts/hq-blockers.sh 2>/dev/null || true)"
+echo "$blocker_output" | head -n 200
+...
+blocked_count="$(echo "$blocker_output" | grep -c '^- ' || echo 0)"
```
Or add a `--output-count-too` mode to `hq-blockers.sh` that emits both the full list and a count in a single pass.

### Finding 3 — MEDIUM: `agent_exec_status` 30-minute heuristic is unreliable (line 125)

```bash
agent_exec_status() {
  local out_epoch
  out_epoch=$(latest_mtime_epoch "sessions/${agent}/outbox")
  local delta=$((now-out_epoch))
  if [ "$delta" -le 1800 ]; then echo "yes"; else echo "no"; fi
}
```

This heuristic labels an agent as "executing" if any outbox file was written in the last 30 minutes. An agent that produced one outbox file and then went idle still shows `exec=yes` for up to 30 minutes. Conversely, an agent actively processing a long-running item shows `exec=no` after 30 minutes.

The correct signal is whether an `.exec-lock/` directory currently exists in any inbox item — that definitively means "executing right now."

**Minimal fix:**
```diff
 agent_exec_status() {
-  local out_epoch
-  out_epoch=$(latest_mtime_epoch "sessions/${agent}/outbox")
-  ...
+  # Check for active execution lock in any current inbox item.
+  local dir="sessions/${agent}/inbox"
+  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
+  if find "$dir" -mindepth 2 -maxdepth 2 -name ".exec-lock" -type d 2>/dev/null | grep -q .; then
+    echo "yes"
+  else
+    echo "no"
+  fi
 }
```
This gives a real-time, accurate "currently executing" signal rather than a stale heuristic.

### Finding 4 — MEDIUM: `latest_mtime_epoch` uses mtime — churn from `.exec-lock` skews "last active" (line 73)

```bash
latest_mtime_epoch() {
  local latest
  latest=$(find "$path" -mindepth 1 -printf '%T@\n' 2>/dev/null | sort -n | tail -n 1 || true)
```

`-mindepth 1` includes `.exec-lock/` directories created during execution. Creating or removing `.exec-lock` updates the mtime of the inbox directory — causing the "last active" timestamp in `hq-status.sh` to show "just now" even for an agent whose outbox hasn't changed. This makes the status table misleading.

**Minimal fix:** Exclude `.exec-lock` from the mtime scan:
```diff
-  latest=$(find "$path" -mindepth 1 -printf '%T@\n' 2>/dev/null | sort -n | tail -n 1 || true)
+  latest=$(find "$path" -mindepth 1 -not -name ".exec-lock" -printf '%T@\n' 2>/dev/null | sort -n | tail -n 1 || true)
```

### Finding 5 — MEDIUM: `ceo-ops-once.sh` swallows `idle-work-generator.sh` errors silently (line 71)

```bash
./scripts/idle-work-generator.sh || true
```

The Python IndentationError in `idle-work-generator.sh` (cycle 8 Finding 1 — fires 89% of the time) produces error output that is silently swallowed by `|| true`. The CEO ops cycle log shows no indication that idle seeding is broken.

**Minimal fix:**
```diff
-./scripts/idle-work-generator.sh || true
+./scripts/idle-work-generator.sh 2>&1 || echo "[WARN] idle-work-generator.sh failed (exit $?)" >&2
```
This makes the error visible in the CEO ops log without aborting the cycle.

### Finding 6 — LOW: `configured_agent_ids()` 9th verbatim copy (line 7, `hq-status.sh`)

This is the 9th copy across the codebase. See cycle 10 Finding 5 for full analysis. The `scripts/lib/agent-ids.sh` refactor would eliminate all 9 copies at once.

---

## Passthrough request to dev-infra

- **File:** `scripts/ceo-ops-once.sh`, `scripts/hq-status.sh`
- **Owner:** `dev-infra`
- **Also needs:** `org-chart/priorities.yaml` ownership added to `file-ownership.md` (owner: `ceo-copilot`)
- **Priority:** Finding 1 (CRITICAL — CEO ops cycle crashes on missing file), Finding 2 (HIGH — double blocker scan), Finding 3 (MEDIUM — unreliable exec heuristic), Finding 4 (MEDIUM — mtime churn), Finding 5 (MEDIUM — silent idle-work errors), Finding 6 (LOW — dedup)
- **Verification:**
  - Finding 1: delete `org-chart/priorities.yaml`, run `bash scripts/ceo-ops-once.sh` — should print "(priorities.yaml not found)" and continue to HQ status output
  - Finding 2: run `bash scripts/ceo-ops-once.sh` with `strace -f -e trace=open` — confirm `agent-exec-next.sh`/outbox files only opened once, not twice
  - Finding 3: manually create `sessions/<agent>/inbox/<item>/.exec-lock/`; run `hq-status.sh` — should show `exec=yes` for that agent

## Knowledgebase references
- None found directly applicable; Finding 1 (`priorities.yaml` missing) suggests there is a missing first-run/setup step that should be documented in `README.md` or created by `setup.sh`.

## Next actions
- Executor: route to `dev-infra` with Finding 1 as immediate priority — the CEO ops cycle has been silently failing to produce any output on any machine without `priorities.yaml`
- Executor: route to `ceo-copilot` to add `org-chart/priorities.yaml` to `file-ownership.md` and ensure it is committed to the repo (or created by `setup.sh`)
- Note: cycle 19 — 9 copies of `configured_agent_ids()` now confirmed; the case for `scripts/lib/agent-ids.sh` is now unambiguous; recommend pm-infra create a single dev-infra work item for the lib extraction as ROI 9

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: Finding 1 means `ceo-ops-once.sh` — the CEO's primary operational visibility script, run every 5 minutes — has been silently aborting before producing any output on machines without `priorities.yaml`; this is a 3-line fix that restores the CEO's entire operational dashboard.
