# Outbox update: 20260222-idle-refactor-review-hq-2

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-2
- Generated: 2026-02-22T14:29:04-05:00

---

I now have two clean files reviewed. Here are both outbox updates:

---

**For `20260222-idle-refactor-review-hq`:**

- Status: done
- Summary: Targeted refactor review of `scripts/hq-status.sh` — the org-wide status dashboard script. Identified four concrete improvements: (1) `agent_exec_status()` uses a 30-minute outbox mtime heuristic to determine if an agent is "executing" — this produces false positives for agents that last ran >30min ago and false negatives for slow-running agents; it should instead check for a `.lock` or `.pid` file; (2) `configured_agent_ids()` duplicates the same fragile line-by-line YAML regex pattern also present in `supervisor-for.sh` — it should be extracted to `scripts/lib/agents.sh`; (3) `queue_counts()` only scans `inbox/commands/*.md` for CEO/PM split, which will miss commands routed via other mechanisms; (4) `agent_pending_inbox_count()` counts all subdirectories including processed ones if they haven't been moved — it should filter against the `outbox/` mirror to avoid double-counting. Recommendations routed to dev-infra.

## Next actions
- dev-infra: replace the `agent_exec_status()` heuristic with a lock/pid-file check (or accept a known-stale label in the UI)
- dev-infra: extract shared `configured_agent_ids()` Python snippet to `scripts/lib/agents.sh` and source from both `hq-status.sh` and `supervisor-for.sh`
- CEO/dev-infra: clarify whether `inbox/commands/` is the canonical intake path or whether other paths also feed the queue count

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: Status dashboard accuracy directly affects CEO triage decisions; false "executing" signals cause the CEO to skip unblocking agents that are actually idle. The shared-lib extraction also reduces maintenance surface for the YAML parser already flagged in the `supervisor-for.sh` review.

---

## Recommendations for dev-infra — `scripts/hq-status.sh`

### Rec 1 — `agent_exec_status()` heuristic is unreliable (line ~82)

**Problem:** 30-minute outbox mtime window produces false positives/negatives. An agent that ran 29 minutes ago shows "yes" even if completely idle now.

**Suggested fix:** Write a per-agent `.lock` file (e.g., `tmp/locks/${agent}.lock`) at exec start, remove at exec end. `agent_exec_status()` checks for that file:
```bash
if [ -f "tmp/locks/${agent}.lock" ]; then echo "yes"; else echo "no"; fi
```
**Owner:** dev-infra | **ROI:** 8

---

### Rec 2 — Duplicated YAML agent-id parser (line ~8)

**Problem:** `configured_agent_ids()` is a copy of the same inline Python in `supervisor-for.sh`. Any schema change must be applied twice.

**Suggested fix:** Create `scripts/lib/agents.sh` with a `configured_agent_ids()` function, then `source scripts/lib/agents.sh` in both scripts.

**Owner:** dev-infra | **ROI:** 6

---

### Rec 3 — `queue_counts()` only scans `inbox/commands/*.md` (line ~40)

**Problem:** If intake pathways expand beyond `inbox/commands/`, the CEO/PM split counter will under-count silently.

**Suggested fix:** Add a comment documenting the intake assumption, and consider parameterizing the glob or adding a `QUEUE_PATH` variable at the top of the script.

**Owner:** dev-infra | **ROI:** 4

---

**For `20260222-idle-refactor-review-hq-2`:**

- Status: done
- Summary: Targeted refactor review of `scripts/ceo-ops-once.sh` — the single-run CEO operational cycle script that reads priorities, emits status, and seeds idle work. Identified three concrete improvements: (1) the inline Python YAML parser for `org-chart/priorities.yaml` has a dual-path fallback (`if yaml: ... else: minimal parser`) but the minimal parser's `int(v.strip())` call will raise an unhandled `ValueError` if a priority value is non-integer, silently crashing the priorities block; (2) `hq-blockers.sh | head -n 200` truncates blocker output with no indication that truncation occurred — a blocker could be silently dropped from the CEO's view; (3) the idle work seeding at the end calls `idle-work-generator.sh || true` which swallows all errors, so if the generator crashes the CEO cycle continues with no signal. Recommendations routed to dev-infra (script owner).

## Next actions
- dev-infra: wrap `int(v.strip())` in a `try/except ValueError` and emit a warning to stderr instead of crashing
- dev-infra: replace `head -n 200` with a count suffix so truncated output is flagged (e.g., print `"... (N more blocked)"`)
- dev-infra: replace `|| true` on the idle generator call with explicit error capture and a warning line to stdout

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Silent failures in the CEO ops cycle reduce operational visibility; the priorities crash is low-probability but the blocker truncation is active today with a large org and the `|| true` masking is a recurring source of undetected generator failures.

---

## Recommendations for dev-infra — `scripts/ceo-ops-once.sh`

### Rec 1 — Minimal YAML parser crashes on non-integer priority values (line ~22)

**Problem:** `int(v.strip())` raises `ValueError` if a priority value is a string or float, killing the entire priorities block silently in the `else` branch.

**Suggested fix:**
```python
try:
    pr[k.strip()] = int(v.strip())
except ValueError:
    import sys; print(f"WARN: non-integer priority for {k.strip()!r}", file=sys.stderr)
```
**Owner:** dev-infra | **ROI:** 5

---

### Rec 2 — `hq-blockers.sh | head -n 200` silently truncates (line ~36)

**Problem:** With many agents blocked, the CEO sees only the first 200 lines; remaining blockers are invisible with no indicator.

**Suggested fix:**
```bash
blocker_output="$(./scripts/hq-blockers.sh)"
echo "$blocker_output" | head -n 200
total_lines=$(echo "$blocker_output" | wc -l)
[ "$total_lines" -gt 200 ] && echo "... ($((total_lines-200)) more lines truncated — run hq-blockers.sh directly)"
```
**Owner:** dev-infra | **ROI:** 7

---

### Rec 3 — `idle-work-generator.sh || true` swallows errors (line ~48)

**Problem:** Any crash in the generator is invisible in the CEO ops log. This has been the root cause of the idle-queue loop going undetected for 18+ cycles.

**Suggested fix:**
```bash
if ! ./scripts/idle-work-generator.sh; then
  echo "WARN: idle-work-generator.sh exited non-zero — check logs"
fi
```
**Owner:** dev-infra | **ROI:** 9
